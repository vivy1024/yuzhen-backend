<?php

namespace App\Infrastructure\Database\Repositories;

/**
 * Base Repository
 * 
 * 所有Repository的基类
 * 提供通用的数据访问方法
 */
abstract class BaseRepository
{
    protected $model;

    /**
     * 获取模型实例
     */
    protected function getModel()
    {
        return app($this->model);
    }

    /**
     * 查找所有记录
     */
    public function all(array $columns = ['*']): array
    {
        return $this->getModel()->get($columns)->toArray();
    }

    /**
     * 根据条件查找
     */
    public function findWhere(array $where, array $columns = ['*']): array
    {
        $query = $this->getModel();
        
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                [$field, $condition, $val] = $value;
                $query = $query->where($field, $condition, $val);
            } else {
                $query = $query->where($field, $value);
            }
        }
        
        return $query->get($columns)->toArray();
    }

    /**
     * 创建记录
     */
    public function create(array $data): array
    {
        $model = $this->getModel()->create($data);
        return $model->toArray();
    }

    /**
     * 更新记录
     */
    public function update(int $id, array $data): bool
    {
        $model = $this->getModel()->find($id);
        
        if (!$model) {
            return false;
        }
        
        return $model->update($data);
    }

    /**
     * 删除记录
     */
    public function delete(int $id): bool
    {
        return $this->getModel()->destroy($id) > 0;
    }

    /**
     * 批量插入
     */
    public function bulkInsert(array $data): bool
    {
        return $this->getModel()->insert($data);
    }
}

