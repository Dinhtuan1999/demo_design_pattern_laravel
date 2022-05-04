<?php

namespace App\Http\Controllers\PC;

use App\Http\Controllers\Controller;
use App\Services\CommentService;
use App\Http\Requests\Comment\AddCommentRequest;
use App\Http\Requests\Comment\DeleteCommentRequest;
use App\Http\Requests\Comment\EditCommentRequest;

/**
 * Handle comments in screen F040
 */
class CommentController extends Controller
{
    private $commentService;

    public function __construct(CommentService $commentService)
    {
        $this->commentService = $commentService;
    }

    /**
     * F040 add comment
     *
     * @param  mixed $request
     * @return view
     */
    public function addComment(AddCommentRequest $request)
    {
        $currentUser = auth()->user();
        $result = $this->commentService->addComment(
            $currentUser->user_id,
            $request->get('task_id'),
            $request->get('comment'),
            $request->file('attachment_file')
        );

        if ($result['status'] === config('apps.general.error')) {
            return '';
        }

        return view('project.response.task_comment')->with("comment", $result['data']);
    }

    /**
     * F040 Delete comment
     *
     * @param  mixed $request
     * @return mixed
     */
    public function deleteComment(DeleteCommentRequest $request)
    {
        $currentUser = auth()->user();
        $result = $this->commentService->deleteComment($request->get('comment_id'), $currentUser->user_id);

        return response()->json($result);
    }

    /**
     * F040 Edit comment
     *
     * @param  Request $request
     * @return json
     */
    public function editComment(EditCommentRequest $request)
    {
        $currentUser = auth()->user();
        $result = $this->commentService->editComment(
            $currentUser->user_id,
            $request->get('comment_id'),
            $request->get('comment')
        );

        return response()->json($result);
    }
}
