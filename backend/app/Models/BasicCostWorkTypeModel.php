<?php

namespace App\Models;

use CodeIgniter\Model;

class BasicCostWorkTypeModel extends Model
{
    protected $table            = 'basic_cost_work_types';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'basic_cost_id',
        'category_id',
        'category_name',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id'            => 'integer',
        'basic_cost_id' => 'integer',
        'category_id'   => 'integer',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
