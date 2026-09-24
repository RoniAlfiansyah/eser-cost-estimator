<?php

namespace App\Models;

use CodeIgniter\Model;

class TemplateItemModel extends Model
{
    protected $table            = 'template_items';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'template_id',
        'cost_item_id',
        'default_quantity',
        'default_duration',
        'default_unit_price',
        'sort_order',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id'                 => 'integer',
        'template_id'        => 'integer',
        'cost_item_id'       => 'integer',
        'default_quantity'   => '?float',
        'default_duration'   => '?float',
        'default_unit_price' => '?float',
        'sort_order'         => 'integer',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
