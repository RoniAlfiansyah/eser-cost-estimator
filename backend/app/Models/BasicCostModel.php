<?php

namespace App\Models;

use CodeIgniter\Model;

class BasicCostModel extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVISION = 'revision';
    public const STATUS_APPROVED = 'approved';

    protected $table            = 'basic_costs';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'scheduler_project_id',
        'client_name',
        'project_name',
        'location',
        'basic_cost_date',
        'notes',
        'grand_total',
        'status',
        'submitted_at',
        'submitted_by',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'created_by',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id'           => 'integer',
        'scheduler_project_id' => '?integer',
        'grand_total'  => 'float',
        'submitted_by' => '?integer',
        'reviewed_by'  => '?integer',
        'created_by'   => '?integer',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
