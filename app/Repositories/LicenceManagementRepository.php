<?php

namespace App\Repositories;

use App\Models\LicenceManagement;

class LicenceManagementRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(LicenceManagement::class);

        $this->fields = $this->getInstance()->getFillable();
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

    public function getLicenceManagements($queryParams = [])
    {
        return $this->getInstance()::where($queryParams)->get();
    }

    public function addLicence($data = [])
    {
        return $this->getInstance()::insert($data);
    }

    public function deleteLicence($data = [])
    {
        return $this->getInstance()::insert($data);
    }
}
