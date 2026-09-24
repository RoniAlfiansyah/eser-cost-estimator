<?php

namespace App\Models;

use CodeIgniter\Model;

class CostProposalItemModel extends Model
{
    protected $table            = 'cost_proposal_items';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'cost_proposal_id', 'basic_cost_item_id', 'category_name', 'subcategory_name',
        'item_name', 'unit_symbol', 'quantity', 'duration',
        'basic_unit_price', 'basic_total_price',
        'markup_type', 'markup_value', 'markup_amount',
        'proposal_unit_price', 'proposal_total_price',
        'unit_price', 'total_price', 'sort_order',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'integer', 'cost_proposal_id' => 'integer', 'basic_cost_item_id' => '?integer',
        'quantity' => 'float', 'duration' => 'float',
        'basic_unit_price' => 'float', 'basic_total_price' => 'float',
        'markup_value' => 'float', 'markup_amount' => 'float',
        'proposal_unit_price' => 'float', 'proposal_total_price' => 'float',
        'unit_price' => 'float', 'total_price' => 'float', 'sort_order' => 'integer',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
