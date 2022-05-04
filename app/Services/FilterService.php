<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FilterService
{
    public function __construct()
    {
    }

    public function prepareFilter($status, $priority, $manager, $author, $taskGroups = null, $authorFile = null, $members = null)
    {
        $response = [
            'status'        => config('apps.general.success'),
            'message'       => []
        ];

        try {
            if (
                $status['status'] == config('apps.general.error')
                ||
                $priority['status'] == config('apps.general.error')
                ||
                $manager['status'] == config('apps.general.error')
                ||
                $author['status'] == config('apps.general.error')
                ||
                $taskGroups['status'] == config('apps.general.error')
                ||
                $authorFile['status'] == config('apps.general.error')
                ||
                $members['status'] == config('apps.general.error')
            ) {
                throw new \Exception(trans('message.ERR_EXCEPTION'));
            }

            $response['data']['status'] = $status['data'];
            $response['data']['priority'] = $priority['data'];
            $response['data']['manager'] = $manager['data'];
            $response['data']['author'] = $author['data'];
            $response['data']['task_group'] = $taskGroups['data'];
            $response['data']['author_file'] = $authorFile['data'] ?? [];
            $response['data']['members'] = $members['data'] ?? [];

            $response['message']    = [trans('message.SUCCESS')];
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response['status']     = config('apps.general.error');
            $response['message'] = [trans('message.ERR_EXCEPTION') ];
            $response['error_code'] = config('apps.general.error_code');
        }

        return $response;
    }

    /**
     * get list projects and member filter in screen G030
     * @param $projects
     * @param $managers
     * @return array
     */
    public function projectsAndMangersFilter($projects, $managers)
    {
        $response = [
            'status'        => config('apps.general.success'),
            'message'       => []
        ];

        try {
            if (
                $projects['status'] == config('apps.general.error')
                ||
                $managers['status'] == config('apps.general.error')
            ) {
                throw new \Exception(trans('message.ERR_EXCEPTION'));
            }

            $response['data']['projects'] = $projects['data'];
            $response['data']['managers'] = $managers['data'] ?? [];

            $response['message']    = [trans('message.SUCCESS')];
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response['status']     = config('apps.general.error');
            $response['message'] = [trans('message.ERR_EXCEPTION') ];
            $response['error_code'] = config('apps.general.error_code');
        }

        return $response;
    }
}
