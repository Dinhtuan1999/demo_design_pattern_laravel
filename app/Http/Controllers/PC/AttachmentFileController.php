<?php

namespace App\Http\Controllers\PC;

use App\Http\Controllers\PC\Controller;
use App\Http\Requests\AttachmentFile\MoveFileToTrashFormRequest;
use App\Services\AttachmentFileService;
use App\Http\Requests\AttachmentFile\StoreAttachmentRequest;
use App\Http\Requests\AttachmentFile\StoreMultipleAttachmentRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AttachmentFileController extends Controller
{
    protected $attachmentFileService;

    public function __construct(
        AttachmentFileService $attachmentFileService
    ) {
        $this->attachmentFileService = $attachmentFileService;
    }


    /**
     * Move file to trash
     *
     * @param  MoveFileToTrashFormRequest $request
     * @return mixed
     */
    public function moveFileToTrash(MoveFileToTrashFormRequest $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            $this->setSessionFlashError(trans('message.FAIL'));
            return;
        }

        $attachmentFileId = $request->input('attachment_file_id');
        $result = $this->attachmentFileService->moveFileToTrash($attachmentFileId, $currentUser);

        return $result;
    }

    /**
     * Download attachmentFile by attachmentFileId
     *
     * @param  string $attachmentFileId
     * @return file
     */
    public function download($attachmentFileId)
    {
        $result = $this->attachmentFileService->download($attachmentFileId);

        if ($result['status'] == config('apps.general.error')) {
            $this->setSessionFlashError($result['message'][0]);
            return redirect()->back();
        }

        return Storage::download($result['data']['attachment_file_path']);
    }

    /**
     * Store attachment
     *
     * @param  StoreAttachmentRequest $request
     * @return mixed
     */
    public function storeAttachment(StoreAttachmentRequest $request)
    {
        $currentUser = Auth::user();

        $fileStored = $this->attachmentFileService->store($request->file('attachment_file'), $request->get('task_id'), $currentUser->user_id);
        if ($fileStored['status'] == config('apps.general.error')) {
            return self::sendError($fileStored['message']);
        }
        $attachmentFileId = $fileStored['data']['attachment_file_id'];

        return self::sendResponse([], ['attachment_file_id' => $attachmentFileId]);
    }

    /**
     * Store multiple attachment
     *
     * @param  StoreMultipleAttachmentRequest $request
     * @return mixed
     */
    public function storeMultiFile(StoreMultipleAttachmentRequest $request)
    {
        $currentUser = Auth::user();
        return $this->attachmentFileService->storeMultiFile($request->file('attachment_file'), $request->get('task_id'), $currentUser->user_id);
    }
}
