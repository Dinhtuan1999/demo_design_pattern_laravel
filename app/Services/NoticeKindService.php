<?php

namespace App\Services;

use App\Repositories\NoticeKindRepository;

class NoticeKindService
{
    protected $noticeKindRepository;

    public function __construct(NoticeKindRepository $noticeKindRepository)
    {
        $this->noticeKindRepository = $noticeKindRepository;
    }

    public function getListNoticeKinds()
    {
        return $this->noticeKindRepository->all([], ['by' => 'notice_kinds', 'asc'], [], ['*']);
    }
}
