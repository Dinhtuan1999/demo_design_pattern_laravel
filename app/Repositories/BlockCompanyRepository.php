<?php

namespace App\Repositories;

use App\Models\BlockCompany;

class BlockCompanyRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(BlockCompany::class);
        $this->fields = BlockCompany::FIELDS;
    }

    public function deleteMissingBlockKeywords($blockKeywordIdsNotDelete = [])
    {
        if (empty($blockKeywordIdsNotDelete)) {
            return true;
        }

        return $this->getInstance()::whereNotIn('block_keyword_id', $blockKeywordIdsNotDelete)->delete();
    }
}
