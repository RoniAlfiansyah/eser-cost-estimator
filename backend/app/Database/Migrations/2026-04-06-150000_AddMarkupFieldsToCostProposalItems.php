<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMarkupFieldsToCostProposalItems extends Migration
{
    public function up()
    {
        $fields = [
            'basic_unit_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
                'after'      => 'duration',
            ],
            'basic_total_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
                'after'      => 'basic_unit_price',
            ],
            'markup_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'percent',
                'after'      => 'basic_total_price',
            ],
            'markup_value' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
                'after'      => 'markup_type',
            ],
            'markup_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
                'after'      => 'markup_value',
            ],
            'proposal_unit_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
                'after'      => 'markup_amount',
            ],
            'proposal_total_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
                'after'      => 'proposal_unit_price',
            ],
        ];

        foreach ($fields as $name => $definition) {
            if (! $this->db->fieldExists($name, 'cost_proposal_items')) {
                $this->forge->addColumn('cost_proposal_items', [$name => $definition]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropColumn('cost_proposal_items', [
            'basic_unit_price',
            'basic_total_price',
            'markup_type',
            'markup_value',
            'markup_amount',
            'proposal_unit_price',
            'proposal_total_price',
        ]);
    }
}
