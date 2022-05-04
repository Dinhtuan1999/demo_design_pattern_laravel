<?php

namespace App\Services;

use App\Models\AttachmentFile;
use App\Repositories\AttachmentFileRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\TaskGroupRepository;
use App\Repositories\TrashRepository;
use App\Repositories\TaskRepository;
use App\Services\AppService;
use App\Services\BaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AttachmentFileService extends BaseService
{
    private $attachmentFileRepo;
    private $trashRepo;
    private $taskRepo;
    private $projectRepo;
    private $taskGroupRepo;

    public function __construct(
        AttachmentFileRepository $attachmentFileRepo,
        TrashRepository $trashRepo,
        TaskRepository $taskRepo,
        ProjectRepository $projectRepo,
        TaskGroupRepository $taskGroupRepo
    ) {
        $this->attachmentFileRepo = $attachmentFileRepo;
        $this->trashRepo          = $trashRepo;
        $this->taskRepo           = $taskRepo;
        $this->projectRepo        = $projectRepo;
        $this->taskGroupRepo      = $taskGroupRepo;
    }

    /**
     * Store attachment file
     *
     * @param file $attachmentFile
     * @param string $taskID
     * @param string $userID
     * @return mixed
     */
    public function store($attachmentFile, $taskID, $userID)
    {
        // store file
        $attachmentFilePath = Storage::put(AttachmentFile::PATH_STORAGE_FILE . $taskID, $attachmentFile, 'public');

        // create record AttachmentFile
        $attachmentFile = $this->attachmentFileRepo->getModel()::create([
            'attachment_file_id'   => AppService::generateUUID(),
            'task_id'              => $taskID,
            'attachment_file_name' => $attachmentFile->getClientOriginalName(),
            'attachment_file_path' => $attachmentFilePath,
            'file_size'            => $attachmentFile->getSize(),
            'create_user_id'       => $userID,
        ]);

        if ($attachmentFile->exists) {
            return self::sendResponse([], $attachmentFile);
        }
        return self::sendError([
            trans('message.ERR_COM_0030', [ 'attribute' => $attachmentFile->getClientOriginalName() ]),
        ]);
    }

    /**
     * F040 Store multiple attachment file
     *
     * @param array $attachmentFiles
     * @param string $taskID
     * @param string $userID
     * @return array
     */
    public function storeMultiFile($attachmentFiles, $taskID, $userID)
    {
        try {
            $dataFiles = [];
            foreach ($attachmentFiles as $key => $attachmentFile) {
                // store file
                $attachmentFilePath = Storage::put(AttachmentFile::PATH_STORAGE_FILE . $taskID, $attachmentFile, 'public');
                $dataFiles[] = [
                    'attachment_file_id'   => AppService::generateUUID(),
                    'task_id'              => $taskID,
                    'attachment_file_name' => $attachmentFile->getClientOriginalName(),
                    'attachment_file_path' => $attachmentFilePath,
                    'file_size'            => $attachmentFile->getSize(),
                    'create_user_id'       => $userID,
                    'create_datetime'      => date('Y-m-d H:i:s'),
                    'update_datetime'      => date('Y-m-d H:i:s'),
                ];
            }

            // create records
            $this->attachmentFileRepo->getModel()::insert($dataFiles);

            return self::sendResponse([], $dataFiles);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return self::sendError([ trans('message.ERR_EXCEPTION') ]);
        }
    }

    public function getListFile($projectId, $filter, $paginate = true)
    {
        $response = [
            'status'     => config('apps.general.success'),
            'message'    => [ trans('message.SUCCESS') ]
        ];

        try {
            $response['data'] = $this->getListFileByProjectV2($projectId, $filter, $paginate);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response['status']     = config('apps.general.error');
            $response['message']    = [ trans('message.ERR_EXCEPTION') ];
            $response['error_code'] = config('apps.general.error_code');
        }

        return $response;
    }

    public function validateGetListFile(Request $request)
    {
        return Validator::make(request()->all(), [
            'project_id' => 'required',
        ], [
            'project_id.required' => trans(
                'validation.required',
                [ 'attribute' => trans('validation_attribute.project_id') ]
            ),
        ]);
    }

    public function moveFileToTrash($attachmentFileId, $currentUser)
    {
        try {
            DB::beginTransaction();
            $attachmentFile = $this->attachmentFileRepo->getModel()::where('attachment_file_id', $attachmentFileId)
                ->first(['attachment_file_id', 'attachment_file_path', 'attachment_file_name', 'task_id', 'delete_flg']);
            if (!$attachmentFile) {
                return self::sendError([ trans('message.ERR_COM_0011', ['attribute' => trans('label.task.attachment_file')]) ]);
            }
            //check exist file in trash
            $trash = $this->trashRepo->getByCols([
                'attachment_file_id' => $attachmentFile->attachment_file_id,
                'identyfying_code'   => config('apps.trash.identyfying_code.file'),
            ]);

            if ($trash) {
                return self::sendError([ trans('message.ERR_COM_0011', [ 'attribute' =>  $attachmentFile->attachment_file_name ]) ], [], config('apps.general.error_code', 600));
            }
            //check task or attachment file is deleted or not
            $task = $this->taskRepo->getByCols([
                'task_id' => $attachmentFile->task_id,
                'delete_flg' => config('apps.general.not_deleted')
            ]);
            if ($attachmentFile->delete_flg != config('apps.general.not_deleted') || is_null($task)) {
                return self::sendError([ trans('message.ERR_COM_0011', ['attribute' => $attachmentFile->attachment_file_name ])]);
            }
            //check project or task group of attachment file is deleted or not
            $project = $this->projectRepo->getByCols([
                'project_id' => $task->project_id,
                'delete_flg' => config('apps.general.not_deleted')
            ]);
            $taskGroup = null;
            if (!is_null($task->task_group_id)) {
                $taskGroup = $this->taskGroupRepo->getByCols([
                    'task_group_id' => $task->task_group_id,
                    'delete_flg' => config('apps.general.not_deleted')
                ]);
            }
            if (is_null($project) || (!is_null($task->task_group_id) && is_null($taskGroup))) {
                return self::sendError([ trans('message.ERR_COM_0011', ['attribute' => $attachmentFile->attachment_file_name ])]);
            }

            if (empty($attachmentFile->task->task_id)) {
                $task_id       = null;
                $project_id    = null;
                $task_group_id = null;
            } else {
                $task_id       = !empty($task->task_id) ? $task->task_id : null;
                $project_id    = !empty($task->project_id) ? $task->project_id : null;
                $task_group_id = !empty($task->task_group_id) ? $task->task_group_id : null;
            }

            $newTrash = [
                'trash_id'           => AppService::generateUUID(),
                'identyfying_code'   => config('apps.trash.identyfying_code.file'),
                'attachment_file_id' => $attachmentFile->attachment_file_id,
                'project_id'         => $project_id,
                'task_group_id'      => $task_group_id,
                'task_id'            => $task_id,
                'delete_date'        => date('Y-m-d'),
                'delete_user_id'     => $currentUser->user_id,
                'create_user_id'     => $currentUser->user_id,
                'update_user_id'     => $currentUser->user_id,
                'create_datetime'    => date('Y-m-d H:i:s'),
                'update_datetime'    => date('Y-m-d H:i:s'),
            ];

            $trash = $this->trashRepo->store($newTrash);

            $attachmentFile->delete_flg     = config('apps.general.is_deleted');
            $attachmentFile->update_user_id = $currentUser->user_id;
            $attachmentFile->save();

            $dataResponse['trash_id'] = $trash->trash_id;

            DB::commit();
            return self::sendResponse([ trans('message.SUCCESS') ], $dataResponse);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return self::sendError([ trans('message.ERR_EXCEPTION') ], [], config('apps.general.error_code', 600));
        }
    }

    public function validateMoveFileToTrash(Request $request)
    {
        return Validator::make(
            $request->all(),
            [
                'attachment_file_id' => [
                    'required',
                    Rule::exists('t_attachment_file', 'attachment_file_id')->where(function ($query) {
                        return $query->where('delete_flg', config('apps.general.not_deleted'));
                    }),
                ],
            ],
            [
                'attachment_file_id.required' => trans('message.ERR_COM_0001', [ 'attribute' => trans('label.task.attachment_file') ]),
                'attachment_file_id.exists'   => trans('message.ERR_COM_0011', [ 'attribute' => trans('label.task.attachment_file') ]),
            ]
        );
    }

    public function getListFileByProjectV2($projectId, $filter, $paginate = true)
    {
        $data = AttachmentFile::query()->select([
            't_attachment_file.attachment_file_id',
            't_attachment_file.attachment_file_name',
            't_attachment_file.file_size',
            't_attachment_file.create_datetime',
            't_attachment_file.attachment_file_path',
            't_task.task_id',
            't_task.task_name',
            't_task_group.group_name',
            't_user.user_id',
            't_user.disp_name',
        ])
            ->join('t_task', 't_attachment_file.task_id', '=', 't_task.task_id')
            ->join('t_task_group', 't_task.task_group_id', '=', 't_task_group.task_group_id')
            ->join('t_user', 't_user.user_id', '=', 't_attachment_file.create_user_id')
            ->join('t_project', 't_task.project_id', '=', 't_project.project_id')
            ->where('t_attachment_file.delete_flg', config('apps.general.not_deleted'))
            ->where('t_task.delete_flg', config('apps.general.not_deleted'))
            ->where('t_task_group.delete_flg', config('apps.general.not_deleted'))
            ->where('t_project.delete_flg', config('apps.general.not_deleted'))
            ->where('t_task.project_id', $projectId);

        $totalAll = $data->count();
        if (!is_null($filter['search'])) {
            $data = $data->where(function ($query) use ($filter) {
                $data = $query->where('t_task_group.group_name', 'LIKE', '%'.$filter['search'].'%')
                    ->orWhere('t_task.task_name', 'LIKE', '%'.$filter['search'].'%')
                    ->orWhere('t_attachment_file.attachment_file_name', 'LIKE', '%'.$filter['search'].'%')
                    ->orWhere('t_user.disp_name', 'LIKE', '%'.$filter['search'].'%')
                    ->orWhere('t_attachment_file.file_size', 'LIKE', '%'.$filter['search'].'%');
            });
        }
        if (!is_null($filter['author']) && count($filter['author'])) {
            $data = $data->whereIn('t_attachment_file.create_user_id', $filter['author']);
        }
        if (!is_null($filter['group']) && count($filter['group'])) {
            $data = $data->whereIn('t_task_group.task_group_id', $filter['group']);
        }

        if (isset($filter['order']) && isset($filter['sort'])) {
            $data = $data->orderBy($filter['order'], $filter['sort']);
        } else {
            $data = $data->orderBy('t_attachment_file.create_datetime', 'DESC');
        }
        if ($paginate) {
            $data = $data->paginate(config('apps.notification.record_per_page'));
        } else {
            $data = $data->get();
        }

        $data->totalAll = $totalAll;
        return $data;
    }

    /**
     * S.F014.2 Download attachment file by Id
     *
     * @param  string $attachmentFileId
     * @return mixed
     */
    public function download($attachmentFileId)
    {
        $attachmentFile = $this->attachmentFileRepo->getModel()::where('attachment_file_id', $attachmentFileId)
            ->first(['attachment_file_path', 'attachment_file_name', 'task_id', 'delete_flg']);
        if (!$attachmentFile) {
            return self::sendError([ trans('message.ERR_COM_0011', ['attribute' => trans('label.task.attachment_file')]) ]);
        }
        //check task or attachment file is deleted or not
        $task = $this->taskRepo->getByCols([
            'task_id' => $attachmentFile->task_id,
            'delete_flg' => config('apps.general.not_deleted')
        ]);
        if ($attachmentFile->delete_flg != config('apps.general.not_deleted') || is_null($task)) {
            return self::sendError([ trans('message.ERR_COM_0011', ['attribute' => $attachmentFile->attachment_file_name ])]);
        }
        //check project or task group of attachment file is deleted or not
        $project = $this->projectRepo->getByCols([
            'project_id' => $task->project_id,
            'delete_flg' => config('apps.general.not_deleted')
        ]);
        $taskGroup = null;
        if (!is_null($task->task_group_id)) {
            $taskGroup = $this->taskGroupRepo->getByCols([
                'task_group_id' => $task->task_group_id,
                'delete_flg' => config('apps.general.not_deleted')
            ]);
        }
        if (is_null($project) || (!is_null($task->task_group_id) && is_null($taskGroup))) {
            return self::sendError([ trans('message.ERR_COM_0011', ['attribute' => $attachmentFile->attachment_file_name ])]);
        }

        if (!Storage::exists($attachmentFile['attachment_file_path'])) {
            return self::sendError([ trans('message.ERR_COM_0032', ['attribute' => $attachmentFile['attachment_file_name']]) ]);
        }

        return self::sendResponse(
            [ trans('message.SUCCESS') ],
            ['attachment_file_path' => $attachmentFile['attachment_file_path']]
        );
    }

    public function getAuthors($projectId)
    {
        try {
            $authors = $this->attachmentFileRepo->getAuthors($projectId);
            return self::sendResponse(
                [ trans('message.SUCCESS') ],
                $authors
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return self::sendError([ trans('message.ERR_EXCEPTION') ]);
        }
    }
}
