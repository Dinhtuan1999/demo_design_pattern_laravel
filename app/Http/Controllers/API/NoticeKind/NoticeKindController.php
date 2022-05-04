<?php

namespace App\Http\Controllers\API\NoticeKind;

use App\Helpers\Transformer;
use App\Http\Controllers\API\Controller;
use App\Services\GraphProjectService;
use App\Services\NoticeKindService;
use App\Services\NoticeKindsService;
use App\Transformers\NoticeKind\GetListNoticeKinds;
use Illuminate\Http\Request;

class NoticeKindController extends Controller
{
    private $noticeKindService;

    public function __construct(NoticeKindService $noticeKindService)
    {
        $this->noticeKindService = $noticeKindService;
    }


    public function getListNoticeKinds()
    {
        $noticeKinds = $this->noticeKindService->getListNoticeKinds();
        return $this->respondSuccess('', Transformer::collection(new GetListNoticeKinds(), $noticeKinds));
    }
}
