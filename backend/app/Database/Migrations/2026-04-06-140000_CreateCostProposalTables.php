<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCostProposalTables extends Migration
{
    public function up()
    {
        $this->createCostProposalsTable();
        $this->createCostProposalItemsTable();
    }

    public function down()
    {
        $this->forge->dropTable('cost_proposal_items', true);
        $this->forge->dropTable('cost_proposals', true);
    }

    private function createCostProposalsTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'basic_cost_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'proposal_number' => ['type' => 'VARCHAR', 'constraint' => 60],
            'proposal_date' => ['type' => 'DATE'],
            'valid_until' => ['type' => 'DATE', 'null' => true],
            'client_name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'project_name' => ['type' => 'VARCHAR', 'constraint' => 220],
            'location' => ['type' => 'VARCHAR', 'constraint' => 220, 'null' => true],
            'work_type_names' => ['type' => 'TEXT', 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'assumptions' => ['type' => 'TEXT', 'null' => true],
            'exclusions' => ['type' => 'TEXT', 'null' => true],
            'payment_terms' => ['type' => 'TEXT', 'null' => true],
            'basic_cost_total' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'overhead_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'percent'],
            'overhead_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'overhead_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'contingency_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'percent'],
            'contingency_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'contingency_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'margin_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'percent'],
            'margin_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'margin_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'discount_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'percent'],
            'discount_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'tax_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'percent'],
            'tax_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'grand_total' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'draft'],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('proposal_number');
        $this->forge->addKey('basic_cost_id');
        $this->forge->addKey('status');
        $this->forge->addKey('created_by');
        $this->forge->addForeignKey('basic_cost_id', 'basic_costs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cost_proposals', true);
    }

    private function createCostProposalItemsTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'cost_proposal_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'basic_cost_item_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'category_name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'subcategory_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'item_name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'unit_symbol' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 1],
            'duration' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 1],
            'unit_price' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'total_price' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('cost_proposal_id');
        $this->forge->addKey('basic_cost_item_id');
        $this->forge->addForeignKey('cost_proposal_id', 'cost_proposals', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('basic_cost_item_id', 'basic_cost_items', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cost_proposal_items', true);
    }
}
