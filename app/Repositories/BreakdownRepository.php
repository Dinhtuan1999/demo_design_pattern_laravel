<?php

namespace App\Repositories;

use App\Models\Breakdown;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class BreakdownRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Breakdown::class);
        $this->fields = Breakdown::FIELDS;
    }

    public function getFollowBreakdown($breakdownId, $userId)
    {
        $model = $this->getModel();

        // Filter
        $model = $model::where('breakdown_id', $breakdownId);

        // Relationship
        $model = $model->withCount('followups');

        $model = $model->withCount(['followups as follow' => function ($query3) use ($userId) {
            $query3->where('followup_user_id', $userId);
        }]);
        $model = $model->with(Breakdown::FOLLOWUPS);

        $model = $model->first();

        // response
        return $model;
    }

    public function updateReporteeUserIds($request, $userId)
    {
        $model = $this->getModel();

        $model = $model::where('task_id', $request->task_id)->whereIn('breakdown_id', $request->list_breakdown_id)->update([
            'reportee_user_id' => $request->reportee_user_id,
            'update_user_id'   => $userId,
            'update_datetime'  => date('Y-m-d H:i:s')
        ]);
        return $model;
    }
}
