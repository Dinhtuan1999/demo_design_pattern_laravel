<?php

namespace App\Services;

use App\Services\BaseService;
use App\Services\AppService;
use App\Services\AttachmentFileService;
use App\Repositories\CommentRepository;
use Illuminate\Support\Facades\Log;

class CommentService extends BaseService
{
    protected $commentRepo;
    protected $attachmentFileService;

    public function __construct(CommentRepository $commentRepo, AttachmentFileService $attachmentFileService)
    {
        $this->commentRepo = $commentRepo;
        $this->attachmentFileService = $attachmentFileService;
    }

    /**
     * S.F040.1 Get list comment
     *
     * @param  string $taskId
     * @param  integer $page
     * @return mixed
     */
    public function getListComment($taskId, $page = 1)
    {
        $commentPaginate = $this->commentRepo->getModel()::join(
            't_user',
            't_user.user_id',
            '=',
            't_comment.contributor_id'
        )->leftJoin('t_attachment_file', function ($join) {
            $join->on('t_attachment_file.attachment_file_id', '=', 't_comment.attachment_file_id')
                ->where('t_attachment_file.delete_flg', '<>', config('apps.general.is_deleted'));
        })
            ->where('t_comment.task_id', $taskId)
            ->where('t_comment.delete_flg', '<>', config('apps.general.is_deleted'))
            ->orderBy('t_comment.create_datetime', 'asc')
            ->offset($page)
            ->paginate(
                config('apps.general.comments.per_page'),
                [
                    't_comment.task_id',
                    't_comment.comment_id',
                    't_comment.comment',
                    't_comment.create_datetime',
                    't_attachment_file.attachment_file_id',
                    't_attachment_file.attachment_file_name',
                    't_attachment_file.attachment_file_path',
                    't_comment.contributor_id',
                    't_user.disp_name',
                    't_user.icon_image_path'
                ]
            );
        $commentPaginate->withPath('/comments?task_id='.$taskId);
        $comments = $commentPaginate->toArray();

        return self::sendResponse([], $comments);
    }

    /**
     * S.F040.2 Add comment task
     *
     * @param  string $userId
     * @param  string $taskId
     * @param  string $comment
     * @param  Illuminate\Http\UploadedFile $attachment_file
     * @return mixed
     */
    public function addComment($userId, $taskId, $comment, $attachment_file = null)
    {
        try {
            // store attachment file
            $attachmentFileId = null;
            if ($attachment_file && $attachment_file->isValid()) {
                $fileStored = $this->attachmentFileService->store($attachment_file, $taskId, $userId);
                if ($fileStored['status'] == config('apps.general.error')) {
                    return self::sendError($fileStored['message']);
                }
                $attachmentFileId = $fileStored['data']['attachment_file_id'];
            }

            // create comment record
            $comment = $this->commentRepo->getModel()::create([
                'comment_id' => AppService::generateUUID(),
                'task_id' => $taskId,
                'contributor_id' => $userId,
                'comment' => $comment,
                'attachment_file_id' => $attachmentFileId,
                'create_user_id' => $userId,
                'update_user_id' => $userId
            ]);
            if ($comment->exists) {
                $commentDetail = $this->detailComment($comment->comment_id);

                return self::sendResponse([trans('message.SUCCESS')], $commentDetail);
            }

            return self::sendError([trans('message.ERR_F04_0001')]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return self::sendError([trans('message.ERR_F04_0001')]);
        }
    }

    /**
     * S.F040.3 edit comment
     *
     * @param  string $userId
     * @param  string $commentId
     * @param  string $comment
     * @return mixed
     */
    public function editComment($userId, $commentId, $comment, $taskId = null, $attachment_file = null)
    {
        // store new attachment file
        $attachmentFileId = null;
        if ($attachment_file && $attachment_file->isValid()) {
            $fileStored = $this->attachmentFileService->store($attachment_file, $taskId, $userId);
            if ($fileStored['status'] == config('apps.general.error')) {
                return self::sendError($fileStored['message']);
            }
            $attachmentFileId = $fileStored['data']['attachment_file_id'];
        }

        $dataCommentUpdate = [
            'comment' => $comment,
            'update_user_id' => $userId
        ];

        if ($attachmentFileId != null) {
            $dataCommentUpdate['attachment_file_id'] = $attachmentFileId;
        }

        $result = $this->commentRepo->getModel()::find($commentId)->update($dataCommentUpdate);

        $commentDetail = $this->detailComment($commentId);
        if ($result) {
            return self::sendResponse([trans('message.SUCCESS')], $commentDetail);
        }

        return self::sendError([trans('message.FAIL')]);
    }

    /**
     * Delete comment
     *
     * @param  string $commentId
     * @param  string $currentUserID
     * @return mixed
     */
    public function deleteComment($commentId, $currentUserID)
    {
        // check exists comment
        $comment = $this->commentRepo->getByCols([
            'comment_id' => $commentId,
            'delete_flg' => config('apps.general.not_deleted')
        ]);

        if (!$comment) {
            return self::sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('label.general.comment') ])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        $dataComment = [
            'delete_flg'        => config('apps.general.is_deleted'),
            'update_datetime'   => date('Y-m-d H:i:s'),
            'update_user_id'    => $currentUserID
        ];
        $this->commentRepo->updateByField('comment_id', $commentId, $dataComment);

        return self::sendResponse([trans('message.SUCCESS')]);
    }

    /**
     * Get detail comment
     *
     * @param  string $commentId
     * @return mixed
     */
    protected function detailComment($commentId)
    {
        return $this->commentRepo->getModel()::join(
            't_user',
            't_user.user_id',
            '=',
            't_comment.contributor_id'
        )->leftJoin('t_attachment_file', function ($join) {
            $join->on('t_attachment_file.attachment_file_id', '=', 't_comment.attachment_file_id')
                ->where('t_attachment_file.delete_flg', '<>', config('apps.general.is_deleted'));
        })
            ->where('t_comment.comment_id', $commentId)
            ->where('t_comment.delete_flg', config('apps.general.not_deleted'))
            ->first([
                't_comment.task_id',
                't_comment.comment_id',
                't_comment.comment',
                't_comment.create_datetime',
                't_attachment_file.attachment_file_id',
                't_attachment_file.attachment_file_name',
                't_attachment_file.attachment_file_path',
                't_comment.contributor_id',
                't_user.disp_name',
                't_user.icon_image_path'
            ]);
    }
}
