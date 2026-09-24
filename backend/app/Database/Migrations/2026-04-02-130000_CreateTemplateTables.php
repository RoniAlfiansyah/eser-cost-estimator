<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTemplateTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'work_type_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'template_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
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
        $this->forge->addKey('work_type_id');
        $this->forge->addKey('created_by');
        $this->forge->addUniqueKey(['work_type_id', 'template_name'], 'templates_work_type_name_unique');
        $this->forge->addForeignKey('work_type_id', 'categories', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('templates', true);

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'template_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'cost_item_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'default_quantity' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => true,
            ],
            'default_duration' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => true,
            ],
            'default_unit_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'null'       => true,
            ],
            'sort_order' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 1,
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
        $this->forge->addKey('template_id');
        $this->forge->addKey('cost_item_id');
        $this->forge->addUniqueKey(['template_id', 'cost_item_id'], 'template_items_template_cost_item_unique');
        $this->forge->addForeignKey('template_id', 'templates', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('cost_item_id', 'cost_items', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('template_items', true);
    }

    public function down()
    {
        $this->forge->dropTable('template_items', true);
        $this->forge->dropTable('templates', true);
    }
}
