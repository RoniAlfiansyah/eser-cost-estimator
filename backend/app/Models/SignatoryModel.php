<?php

namespace App\Models;

use CodeIgniter\Model;

class SignatoryModel extends Model
{
    protected $table            = 'signatories';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'position_title',
        'signature_image_path',
        'is_active',
        'sort_order',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
