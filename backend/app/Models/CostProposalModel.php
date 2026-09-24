<?php

namespace App\Models;

use CodeIgniter\Model;

class CostProposalModel extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVISION = 'revision';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table            = 'cost_proposals';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'basic_cost_id', 'proposal_number', 'proposal_date', 'valid_until',
        'client_name', 'project_name', 'location', 'work_type_names',
        'notes', 'assumptions', 'exclusions', 'payment_terms',
        'signatory_id', 'signatory_name', 'signatory_title', 'signatory_signature_path',
        'attention_name', 'proposal_subject', 'attachment_label', 'public_token',
        'basic_cost_total', 'overhead_type', 'overhead_value', 'overhead_amount',
        'contingency_type', 'contingency_value', 'contingency_amount',
        'margin_type', 'margin_value', 'margin_amount',
        'discount_type', 'discount_value', 'discount_amount',
        'tax_type', 'tax_value', 'tax_amount',
        'grand_total', 'status', 'submitted_at', 'submitted_by',
        'reviewed_at', 'reviewed_by', 'review_notes', 'created_by',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'integer', 'basic_cost_id' => 'integer', 'signatory_id' => '?integer', 'basic_cost_total' => 'float',
        'overhead_value' => 'float', 'overhead_amount' => 'float',
        'contingency_value' => 'float', 'contingency_amount' => 'float',
        'margin_value' => 'float', 'margin_amount' => 'float',
        'discount_value' => 'float', 'discount_amount' => 'float',
        'tax_value' => 'float', 'tax_amount' => 'float',
        'grand_total' => 'float', 'submitted_by' => '?integer',
        'reviewed_by' => '?integer', 'created_by' => '?integer',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
