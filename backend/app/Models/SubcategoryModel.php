<?php

namespace App\Models;

use CodeIgniter\Model;

class SubcategoryModel extends Model
{
    protected $table            = 'subcategories';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'category_id',
        'unit_id',
        'name',
        'default_duration',
        'default_price',
        'description',
        'is_active',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id'          => 'integer',
        'category_id' => 'integer',
        'unit_id'     => '?integer',
        'default_duration' => 'float',
        'default_price'    => 'float',
        'is_active'   => 'boolean',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
