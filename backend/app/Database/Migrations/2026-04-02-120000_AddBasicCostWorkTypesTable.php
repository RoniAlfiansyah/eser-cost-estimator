<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBasicCostWorkTypesTable extends Migration
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
            'basic_cost_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'category_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'category_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
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
        $this->forge->addUniqueKey(['basic_cost_id', 'category_id']);
        $this->forge->addForeignKey('basic_cost_id', 'basic_costs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('category_id', 'categories', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('basic_cost_work_types', true);

        $this->db->query('ALTER TABLE `basic_costs` DROP FOREIGN KEY `basic_costs_work_type_category_id_foreign`');
        $this->db->query('ALTER TABLE `basic_costs` DROP INDEX `work_type_category_id`');
        $this->db->query('ALTER TABLE `basic_costs` DROP COLUMN `work_type_category_id`');
        $this->db->query('ALTER TABLE `basic_costs` DROP COLUMN `work_type_name`');
    }

    public function down()
    {
        $this->forge->addColumn('basic_costs', [
            'work_type_category_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'basic_cost_date',
            ],
            'work_type_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
                'null'       => true,
                'after'      => 'work_type_category_id',
            ],
        ]);

        $this->db->query('ALTER TABLE `basic_costs` ADD KEY `work_type_category_id` (`work_type_category_id`)');
        $this->db->query('ALTER TABLE `basic_costs` ADD CONSTRAINT `basic_costs_work_type_category_id_foreign` FOREIGN KEY (`work_type_category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE SET NULL');
        $this->forge->dropTable('basic_cost_work_types', true);
    }
}
