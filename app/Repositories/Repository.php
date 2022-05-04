<?php

namespace App\Repositories;

use App\Scopes\DeleteFlgNotDeleteScope;

class Repository
{
    private $model;
    protected $fields;

    public const NOT_DELETED = 'not_deleted';

    public function __construct($model)
    {
        $this->model = $model;
        $this->fields = defined("$model::FIELDS") ? $model::FIELDS : [];
    }

    public function getModel()
    {
        return $this->model;
    }

    public function getInstance()
    {
        return new $this->model();
    }

    public function store($data)
    {
        if (empty($this->fields) || empty($data)) {
            return false;
        }

        $object = new $this->model();
        foreach ($this->fields as $field) {
            if (array_key_exists($field, $data)) {
                $object->$field = $data[$field];
            }
        }
        if ($object->save()) {
            return $object;
        }
        return false;
    }

    public function update($object, $data)
    {
        if (empty($this->fields) || empty($data)) {
            return false;
        }

        foreach ($this->fields as $field) {
            if (array_key_exists($field, $data)) {
                $object->$field = $data[$field];
            }
        }

        if ($object->save()) {
            return $object;
        }
        return false;
    }

    public function get($filters = [], $take = 30, $sort = [], $relations = [], $cols = ['*'])
    {
        $data = new $this->model();

        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                if ($value === "NOT_FILTER") {
                    continue;
                }
                if (in_array($key, $this->fields, true)) {
                    if ($value === "NULL") {
                        $data = $data->whereNull($key);
                    } elseif ($value === "NOT_NULL") {
                        $data = $data->whereNotNull($key);
                    } elseif (is_array($value) && count($value) == 2) {
                        $columnName = $key;
                        $startDate = date($value[0] . " 00:00:00");
                        $endDate = date($value[1] . " 23:59:59");
                        $arrDate = [$startDate, $endDate];
                        $data = $data->whereBetween($columnName, $arrDate);
                    } else {
                        $data = $data->where($key, $value);
                    }
                }
                // Search Where not like
                // nhận mảng key notlike, bên trong là các mảng con có dạng: ['columnname' => 'columnvalue']
                elseif ($key === 'notlike') {
                    foreach ($value as $keyV => $item) {
                        $data = $data->where($keyV, 'NOT LIKE', "%$item%");
                    }
                }
                // Search Where like
                // nhận mảng key like, bên trong là các mảng con có dạng: ['columnname' => 'columnvalue']
                elseif ($key === 'like') {
                    foreach ($value as $keyV => $item) {
                        $data = $data->where($keyV, 'LIKE', "%$item%");
                    }
                } // search where in
                elseif ($key == 'wherein') {
                    $colName = $filters[$key]['col'];
                    $arrayValue = $filters[$key]['array_value'];
                    $data = $data->whereIn($colName, $arrayValue);
                } //
                elseif ($key == 'wherenotin') {
                    foreach ($value as $colName => $item) {
                        $data = $data->whereNotIn($colName, $item);
                    }
                }
                // where record not deleted
                elseif ($value === self::NOT_DELETED) {
                    $data = $data->where(function ($query) use ($key) {
                        $query->where($key.'.delete_flg', '<>', config('apps.general.is_deleted'))
                            ->orWhereNull($key.'.delete_flg');
                    });
                }
                // Search theo month:
                // Mang month co dang: MONTH => [$columnName, $month]
                elseif ($key == 'MONTH') {
                    $data = $data->whereMonth($value[0], $value[1]);
                }

                // Search theo year:
                // Mang month co dang: YEAR => [$columnName, $year]
                elseif ($key == 'YEAR') {
                    $data = $data->whereYear($value[0], $value[1]);
                }
            }
        }

        if (is_array($sort) && !empty($sort['by']) && !empty($sort['type'])) {
            $data = $data->orderBy($sort['by'], $sort['type']);
        } else {
            $data = $data->orderBy('id', 'desc');
        }
        // return $data->toSql();
        if (count($relations) > 0) {
            return $data->with($relations)->paginate($take);
        }
        return $data->paginate($take, $cols);
    }

    public function all($filters = [], $sort = [], $relations = [], $cols = ['*'])
    {
        $data = new $this->model();

        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                if (in_array($key, $this->fields, true)) {
                    if ($value === "NULL") {
                        $data = $data->whereNull($key);
                    } elseif ($value === "NOT_NULL") {
                        $data = $data->whereNotNull($key);
                    } elseif (is_array($value) && count($value) == 2) {
                        $columnName = $key;
                        $data = $data->where($columnName, '>=', $value[0]);
                        $data = $data->where($columnName, '<=', $value[1]);
                    } else {
                        $data = $data->where($key, $value);
                    }
                } else {
                    if ($key == 'wherein') {
                        foreach ($value as $index => $item) {
                            $data = $data->whereIn($index, $item);
                        }
                    } elseif ($key == 'wherenotin') {
                        foreach ($value as $colName => $item) {
                            $data = $data->whereNotIn($colName, $item);
                        }
                    }
                    // Search theo month:
                    // Mang month co dang: MONTH => [$columnName, $month]
                    elseif ($key == 'MONTH') {
                        $data = $data->whereMonth($value[0], $value[1]);
                    }

                    // Search theo year:
                    // Mang month co dang: YEAR => [$columnName, $year]
                    elseif ($key == 'YEAR') {
                        $data = $data->whereYear($value[0], $value[1]);
                    }
                }
            }
        }

        if (is_array($sort) && !empty($sort['by']) && !empty($sort['type'])) {
            $data = $data->orderBy($sort['by'], $sort['type']);
        }
        if (count($relations) > 0) {
            return $data->with($relations)->get();
        }
        return $data->get($cols);
    }

    public function count($filters)
    {
        $data = new $this->model();

        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                if (in_array($key, $this->fields, true)) {
                    if ($value === "NULL") {
                        $data = $data->whereNull($key);
                    } elseif ($value === "NOT_NULL") {
                        $data = $data->whereNotNull($key);
                    } elseif (is_array($value) && count($value) == 2) {
                        $columnName = $key;
                        $data = $data->where($columnName, '>=', $value[0]);
                        $data = $data->where($columnName, '<=', $value[1]);
                    } else {
                        $data = $data->where($key, $value);
                    }
                } else {
                    if ($key == 'wherein') {
                        foreach ($value as $index => $item) {
                            $data = $data->whereIn($index, $item);
                        }
                    } elseif ($key == 'wherenotin') {
                        foreach ($value as $colName => $item) {
                            $data = $data->whereNotIn($colName, $item);
                        }
                    }
                    // Search theo month:
                    // Mang month co dang: MONTH => [$columnName, $month]
                    elseif ($key == 'MONTH') {
                        $data = $data->whereMonth($value[0], $value[1]);
                    }

                    // Search theo year:
                    // Mang month co dang: YEAR => [$columnName, $year]
                    elseif ($key == 'YEAR') {
                        $data = $data->whereYear($value[0], $value[1]);
                    }
                }
            }
        }
        return $data->count();
    }

    public function sum($filters, $column)
    {
        $data = new $this->model();

        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                if (in_array($key, $this->fields, true)) {
                    $data = $data->where($key, $value);
                }
            }
        }

        return $data->sum($column);
    }

    public function sumInIds($filters, $column)
    {
        $data = new $this->model();
        $sum = 0;
        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                $data = $data->orWhere(['id' => $value]);
            }
        } else {
            return 0;
        }

        return $data->sum($column);
    }

    public function getById($id)
    {
        if (!$id) {
            return false;
        }
        return $this->model::find($id);
    }

    // Thêm phương thức getBySlug
    public function getBySlug($slug, $filters = [])
    {
        if (!$slug) {
            return false;
        }
        if (empty($filters)) {
            return $this->model::where('slug', $slug)->first();
        } else {
            $filters['slug'] = $slug;
            return $this->model::where($filters)->first();
        }
    }

    public function insertMultiRecord($data)
    {
        return $this->model::insert($data);
    }

    public function pluckCol($col, $filters = [])
    {
        if (count($filters) > 0) {
            return $this->model::where($filters)->pluck($col);
        }
        return $this->model::pluck($col);
    }

    public function getByCol($col, $value, $relations = [], $sort = [])
    {
        if (count($relations) > 0) {
            if (is_array($sort) && !empty($sort['by']) && !empty($sort['type'])) {
                return $this->model::where($col, $value)->orderBy($sort['by'], $sort['type'])->with($relations)->first();
            }
            return $this->model::where($col, $value)->with($relations)->first();
        }
        if (is_array($sort) && !empty($sort['by']) && !empty($sort['type'])) {
            return $this->model::where($col, $value)->orderBy($sort['by'], $sort['type'])->first();
        }
        return $this->model::where($col, $value)->first();
    }

    public function updateById($id, array $data)
    {
        return $this->model::where('id', $id)->update($data);
    }

    public function getByCols($filters, $relations = [], $sort = [])
    {
        if (count($relations) > 0) {
            if (is_array($sort) && !empty($sort['by']) && !empty($sort['type'])) {
                return $this->model::where($filters)->orderBy($sort['by'], $sort['type'])->with($relations)->first();
            }
            return $this->model::where($filters)->with($relations)->first();
        }
        if (is_array($sort) && !empty($sort['by']) && !empty($sort['type'])) {
            return $this->model::where($filters)->orderBy($sort['by'], $sort['type'])->first();
        }
        return $this->model::where($filters)->first();
    }

    public function insertOrUpdate($id, array $data)
    {
        if (!empty($id)) {
            $id = intval($id);
            return $this->updateById($id, $data);
        } else {
            return $this->store($data);
        }
    }

    public function getMax($conditions, $col)
    {
        if (!empty($conditions) && is_array($conditions)) {
            return $this->model::where($conditions)->max($col);
        }
        return $this->model::max($col);
    }

    public function updateByField(string $filed, $fieldValue, array $data): bool
    {
        try {
            $result = $this->getInstance()->where($filed, $fieldValue)->update($this->checkDataWithField($data));
            if (!$result) {
                return false;
            }
            return true;
        } catch (\Throwable $th) {
        }
        return false;
    }

    /**
     * check field in data has $this->field
     */

    private function checkDataWithField(array $data)
    {
        return array_filter($data, function ($item) {
            return in_array($item, $this->fields, true);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Find model by column
     *
     * @param [type] $column
     * @param [type] $valueColumn
     * @param array $relations
     * @return model
     */
    public function findByField($column, $valueColumn, $relations = [])
    {
        try {
            return $this->getInstance()->where($column, $valueColumn)->with($relations)->first();
        } catch (\Throwable $th) {
            set_log_error('findByField', $th->getMessage());
        }
        return null;
    }
    /**
     * delete model by column
     *
     * @param [type] $column
     * @param [type] $valueColumn
     * @return bool
     */
    public function deleteByField($column, $valueColumn): bool
    {
        try {
            return $this->getInstance()->where($column, $valueColumn)->delete();
        } catch (\Throwable $th) {
            set_log_error('deleteByField', $th->getMessage());
        }
        return false;
    }

    /**
    * delete model by multiple column
    *
    * @param [type] $column
    * @param [type] $valueColumn
    * @return bool
    */
    public function updateByMultipleField($query=[], $data=[]): bool
    {
        try {
            return $this->getInstance()->where($query)->update($data);
        } catch (\Throwable $th) {
            set_log_error('deleteByField', $th->getMessage());
        }
        return false;
    }

    /* Find model by condition
    *
    * @param [type] $column
    * @param [type] $valueColumn
    * @param array $relations
    * @return void
    */
    public function findByFields($conditions = [], $relations = [], $cols = ['*'])
    {
        try {
            return $this->getInstance()->select($cols)->where($conditions)->with($relations)->first();
        } catch (\Throwable $th) {
            set_log_error('findByFields', $th->getMessage());
        }
        return null;
    }

    public function isDeleted($tableName, $objectIdName, $objectId, $identifyCode)
    {
        // ex: $tableName t_project
        // $objectIdName project_id
        $secondCond = $tableName.'.'.$objectIdName; // 't_project.project_id'

        return $this->getModel()::query()
            ->join('t_trash', function ($join) use ($objectIdName, $secondCond, $identifyCode) {
                $join->on('t_trash.'.$objectIdName, '=', $secondCond);
                $join->where('t_trash.identyfying_code', '=', $identifyCode);
            }, '', '', 'left outer')
            ->where($secondCond, $objectId)
            ->where($tableName.'.delete_flg', config('apps.general.is_deleted'))
            ->whereNull('t_trash.trash_id')
            ->withoutGlobalScope(new DeleteFlgNotDeleteScope())
            ->first();
    }

    public function isMovedToTrash($tableName, $objectIdName, $objectId, $identifyCode)
    {
        $secondCond = $tableName.'.'.$objectIdName; // 't_project.project_id'
        return $this->getModel()::query()
            ->join('t_trash', function ($join) use ($objectIdName, $secondCond, $identifyCode) {
                $join->on('t_trash.'.$objectIdName, '=', $secondCond);
                $join->where('t_trash.identyfying_code', '=', $identifyCode);
            })
            ->where($secondCond, $objectId)
            ->where($tableName.'.delete_flg', config('apps.general.is_deleted'))
            ->withoutGlobalScope(new DeleteFlgNotDeleteScope())
            ->first();
    }
}
