<?php

namespace App\Http\Controllers\PC\Chat;

use App\Events\SendMessageEvent;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Task;
use App\Services\AppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat.index');
    }


    public function getMesssageAjax()
    {
        $currentUser = Auth::user();

        $messages = Comment::join('t_user', 't_user.user_id', 't_comment.contributor_id')
                            ->select('t_comment.comment_id', 't_comment.contributor_id', 't_comment.comment', 't_comment.create_datetime')
                            ->addSelect('t_user.disp_name', 't_user.icon_image_path', 't_user.user_id')
                            ->addSelect(DB::raw(
                                '(CASE 
                                    WHEN t_user.icon_image_path = "images/user-acount.png" THEN  "' .asset('images/user-acount.png').'" 
                                    ELSE CONCAT(?,t_user.icon_image_path)
                                    END)  AS icon_url'
                            ))
                            ->setBindings([Storage::url('/')])
                            ->orderBy('t_comment.create_datetime', 'asc')
                            ->skip(Comment::count()-10)
                            ->take(10)
                            ->get();

        return $this->respondSuccess('success', ["data" => $messages]);
    }

    public function storeMessage(Request $request)
    {
        $data = $request->only(['comment']);
        $data['comment_id'] = AppService::generateUUID();
        $data['task_id'] = Task::first()->task_id;
        $data['contributor_id'] = Auth::user()->user_id;

        $comment = Comment::create($data);

        $message = Comment::join('t_user', 't_user.user_id', 't_comment.contributor_id')
                ->select('t_comment.comment_id', 't_comment.contributor_id', 't_comment.comment', 't_comment.create_datetime')
                ->addSelect('t_user.disp_name', 't_user.icon_image_path', 't_user.user_id')
                ->addSelect(DB::raw(
                    '(CASE 
                        WHEN t_user.icon_image_path = "images/user-acount.png" THEN  "' .asset('images/user-acount.png').'" 
                        ELSE CONCAT(?,t_user.icon_image_path)
                        END)  AS icon_url'
                ))
                ->setBindings([Storage::url('/')])
                ->where('t_comment.comment_id', '=', $data['comment_id'])
                ->first();

        // send broadcast event
        broadcast(new SendMessageEvent($message, Auth::user()));
        if (!$message) {
            return $this->respondWithError('Faild !');
        }

        return $this->respondSuccess('Ok !', $message);
    }

    public function dropIndex()
    {
        return view('chat.drop-index');
    }
}
