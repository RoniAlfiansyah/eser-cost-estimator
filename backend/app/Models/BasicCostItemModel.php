<?php

namespace App\Models;

use CodeIgniter\Model;

class BasicCostItemModel extends Model
{
    protected $table            = 'basic_cost_items';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'basic_cost_id',
        'category_id',
        'category_name',
        'subcategory_id',
        'subcategory_name',
        'cost_item_id',
        'item_name',
        'unit_id',
        'unit_name',
        'unit_symbol',
        'quantity',
        'duration',
        'unit_price',
        'total_price',
        'sort_order',
        'source_type',
        'source_key',
        'source_payload',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id'             => 'integer',
        'basic_cost_id'  => 'integer',
        'category_id'    => '?integer',
        'subcategory_id' => '?integer',
        'cost_item_id'   => '?integer',
        'unit_id'        => '?integer',
        'quantity'       => 'float',
        'duration'       => 'float',
        'unit_price'     => 'float',
        'total_price'    => 'float',
        'sort_order'     => 'integer',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
