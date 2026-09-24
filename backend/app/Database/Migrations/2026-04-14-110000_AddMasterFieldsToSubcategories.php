<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMasterFieldsToSubcategories extends Migration
{
    public function up()
    {
        $fields = [
            'unit_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'category_id',
            ],
            'default_duration' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'default'    => 0,
                'after'      => 'name',
            ],
            'default_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
                'after'      => 'default_duration',
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'default_price',
            ],
        ];

        $this->forge->addColumn('subcategories', $fields);
        $this->db->query('ALTER TABLE `subcategories` ADD KEY `unit_id` (`unit_id`)');
        $this->db->query('ALTER TABLE `subcategories` ADD CONSTRAINT `subcategories_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');

        $this->db->query(
            'UPDATE subcategories s
            JOIN cost_items ci ON ci.subcategory_id = s.id
            SET
                s.unit_id = ci.unit_id,
                s.default_duration = ci.default_duration,
                s.default_price = ci.default_price,
                s.description = ci.description'
        );
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `subcategories` DROP FOREIGN KEY `subcategories_unit_id_foreign`');
        $this->db->query('ALTER TABLE `subcategories` DROP INDEX `unit_id`');
        $this->forge->dropColumn('subcategories', ['unit_id', 'default_duration', 'default_price', 'description']);
    }
}
