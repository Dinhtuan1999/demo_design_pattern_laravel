<?php

namespace App\Repositories;

use App\Models\ProjectOwnedAttribute;

class ProjectOwnedAttributeRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(ProjectOwnedAttribute::class);
        $this->fields = ProjectOwnedAttribute::FIELDS;
    }

    /**
     * Get project attributes
     * TODO: S.E030.1
     * 2022-02-24
     *
     * @param $projectId
     * @return void
     */
    public function getProjectAttributes($projectId)
    {
        $model = $this->getModel();
        $model = $model::select('m_project_attribute.project_attribute_id', 'm_project_attribute.project_attribute_name', 'm_project_attribute.kinds_id');
        $model = $model->join('m_project_attribute', 'm_project_attribute.project_attribute_id', '=', 't_project_owned_attribute.project_attribute_id');
        $model = $model->where('t_project_owned_attribute.project_id', $projectId);
        $model = $model->orderBy('m_project_attribute.project_attribute_name', 'ASC');
        return $model->get();
    }
}
