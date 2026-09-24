<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table            = 'audit_logs';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'action',
        'module',
        'description',
        'target_type',
        'target_id',
        'ip_address',
        'user_agent',
        'properties',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id'         => 'integer',
        'user_id'    => '?integer',
        'properties' => 'json-array',
        'created_at' => '?datetime',
        'updated_at' => '?datetime',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getRecentLogs(int $limit = 50): array
    {
        return $this->select('audit_logs.*, users.name as user_name, users.email as user_email')
            ->join('users', 'users.id = audit_logs.user_id', 'left')
            ->orderBy('audit_logs.created_at', 'DESC')
            ->findAll($limit);
    }
}
