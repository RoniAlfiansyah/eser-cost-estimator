<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBasicCostTables extends Migration
{
    public function up()
    {
        $this->createBasicCostsTable();
        $this->createBasicCostItemsTable();
    }

    public function down()
    {
        $this->forge->dropTable('basic_cost_items', true);
        $this->forge->dropTable('basic_costs', true);
    }

    private function createBasicCostsTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'client_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
            ],
            'project_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 220,
            ],
            'location' => [
                'type'       => 'VARCHAR',
                'constraint' => 220,
                'null'       => true,
            ],
            'basic_cost_date' => [
                'type' => 'DATE',
            ],
            'work_type_category_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'work_type_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
                'null'       => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'grand_total' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('work_type_category_id');
        $this->forge->addKey('created_by');
        $this->forge->addForeignKey('work_type_category_id', 'categories', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('basic_costs', true);
    }

    private function createBasicCostItemsTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'basic_cost_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'category_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'category_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
            ],
            'subcategory_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'subcategory_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
                'null'       => true,
            ],
            'cost_item_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'item_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
            ],
            'unit_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'unit_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'unit_symbol' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
            'quantity' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'default'    => 1,
            ],
            'duration' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'default'    => 1,
            ],
            'unit_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
            ],
            'total_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
            ],
            'sort_order' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('basic_cost_id');
        $this->forge->addKey('category_id');
        $this->forge->addKey('subcategory_id');
        $this->forge->addKey('cost_item_id');
        $this->forge->addKey('unit_id');
        $this->forge->addForeignKey('basic_cost_id', 'basic_costs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('category_id', 'categories', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('subcategory_id', 'subcategories', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('cost_item_id', 'cost_items', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('unit_id', 'units', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('basic_cost_items', true);
    }
}
