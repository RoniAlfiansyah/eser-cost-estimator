<?php

namespace App\Models;

use CodeIgniter\Model;

class BasicCostApprovalHistoryModel extends Model
{
    protected $table            = 'basic_cost_approval_histories';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'basic_cost_id',
        'action',
        'status_from',
        'status_to',
        'note',
        'actor_id',
        'actor_name',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id'            => 'integer',
        'basic_cost_id' => 'integer',
        'actor_id'      => '?integer',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
