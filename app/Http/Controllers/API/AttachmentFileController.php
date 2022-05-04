<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\ProjectExistsRequest;
use App\Services\AttachmentFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Services\BaseService;
use App\Http\Requests\AttachmentFile\MoveFileToTrashFormRequest;

class AttachmentFileController extends Controller
{
    protected $attachmentFileService;
    protected $baseService;
    public function __construct(
        AttachmentFileService $attachmentFileService,
        BaseService $baseService
    ) {
        $this->attachmentFileService = $attachmentFileService;
        $this->baseService = $baseService;
    }

    public function getListFile(ProjectExistsRequest $request)
    {
        $filter = [
            'search'  => null,
            'group'  => null,
            'author'  => null,
        ];

        if ($request->has('search') && !empty($request->input('search'))) {
            $filter['search'] = $request->input('search');
        }

        if ($request->has('group') && is_array($request->input('group'))) {
            $filter['group'] = $request->input('group');
        }

        if ($request->has('author') && is_array($request->input('author'))) {
            $filter['author'] = $request->input('author');
        }

        $result = $this->attachmentFileService->getListFile(
            $request->input('project_id'),
            $filter,
            false
        );

        return response()->json($result);
    }

    public function moveFileToTrash(MoveFileToTrashFormRequest $request)
    {
        $currentUser = Auth::guard('api')->user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }

        $attachmentFileId = $request->input('attachment_file_id');
        $result = $this->attachmentFileService->moveFileToTrash($attachmentFileId, $currentUser);

        return response()->json($result);
    }

    public function download($attachmentFileId)
    {
        $result = $this->attachmentFileService->download($attachmentFileId);

        if ($result['status'] == config('apps.general.success')) {
            return Storage::download($result['data']['attachment_file_path']);
        }

        return response()->json($result, config('apps.general.file_not_found'));
    }
}
