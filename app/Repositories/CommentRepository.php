<?php

namespace App\Repositories;

use App\Models\Comment;

class CommentRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Comment::class);
        $this->fields = Comment::FIELDS;
    }

    public function formatAllRecord($records)
    {
        if (!empty($records)) {
            foreach ($records as &$record) {
                $record = $this->formatRecord($record);
            }
        }
        return $records;
    }

    public function formatRecord($record)
    {
        return $record;
    }
}
