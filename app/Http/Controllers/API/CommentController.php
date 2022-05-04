<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Controller;
use App\Services\CommentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    private $commentService;

    public function __construct(CommentService $commentService)
    {
        $this->commentService = $commentService;
    }

    /**
     * A.F040.1 Get list comment
     *
     * @param  Request $request
     * @return json
     */
    public function getListComment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'task_id' => 'required|exists:t_task',
            'page'=> 'integer'
        ]);
        if ($validator->fails()) {
            return $this->respondWithError($validator->messages()->all());
        }

        $result = $this->commentService->getListComment($request->get('task_id'), $request->get('page'));

        return response()->json([
            'status'  => config('apps.general.success'),
            'message' => $result['message'],
            'data' => $result['data'] ?? []
        ]);
    }

    /**
     * A.F040.2 Add comment
     *
     * @param  Request $request
     * @return json
     */
    public function addComment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'task_id' => 'required|exists:t_task',
            'comment'=> 'required|string|max:12000',
            'attachment_file' => 'mimes:jpg,jpeg,bmp,png,doc,docx,pdf,txt,xls,xlsx'
        ]);
        if ($validator->fails()) {
            return $this->respondWithError($validator->messages()->all());
        }

        $currentUser = auth('api')->user();
        $result = $this->commentService->addComment(
            $currentUser->user_id,
            $request->get('task_id'),
            $request->get('comment'),
            $request->file('attachment_file')
        );

        return response()->json([
            'status'  => $result['status'],
            'message' => $result['message'] ?? [],
            'data' => $result['data'] ?? []
        ]);
    }

    /**
     * A.F040.3 Edit comment
     *
     * @param  Request $request
     * @return json
     */
    public function editComment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'comment_id' => 'required|exists:t_comment',
            'comment'=> 'required|string|max:12000'
        ]);
        if ($validator->fails()) {
            return $this->respondWithError($validator->messages()->all());
        }

        $currentUser = auth('api')->user();
        $result = $this->commentService->editComment(
            $currentUser->user_id,
            $request->get('comment_id'),
            $request->get('comment')
        );

        return response()->json([
            'status'  => $result['status'],
            'message' => $result['message'] ?? [],
            'data' => $result['data'] ?? []
        ]);
    }

    /**
     * Delete comment
     *
     * @param  String $commentId
     * @return json
     */
    public function deleteComment($commentId)
    {
        $currentUser = auth('api')->user();
        $result = $this->commentService->deleteComment($commentId, $currentUser->user_id);

        return response()->json($result);
    }
}
