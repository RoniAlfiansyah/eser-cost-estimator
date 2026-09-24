<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCostCategoryToSchedulerEquipment extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('cost_category_id', 'resource_equipment')) {
            $this->forge->addColumn('resource_equipment', [
                'cost_category_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'category',
                ],
            ]);
            $this->db->query('ALTER TABLE `resource_equipment` ADD KEY `resource_equipment_cost_category_id` (`cost_category_id`)');
            $this->db->query('ALTER TABLE `resource_equipment` ADD CONSTRAINT `resource_equipment_cost_category_id_foreign` FOREIGN KEY (`cost_category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        }

        $rules = [
            'Aerial Photogrammetry / LiDAR Survey' => ['Aerial Survey'],
            'Topography Survey' => ['Geodetic', 'GPS', 'Topography', 'Waterpass'],
            'Hydrography Survey' => ['Hydrography', 'Multibeam', 'Single Beam'],
            'Oceanography Survey' => ['AWLR', 'Oceanography'],
            'Geophysical Survey' => ['Magnetometer', 'SBP', 'Side Scan Sonar'],
            'Equipment' => ['Marine Logistics'],
        ];

        foreach ($rules as $costCategory => $equipmentCategories) {
            $category = $this->db->table('categories')->select('id')->where('name', $costCategory)->get()->getRowArray();
            if ($category) {
                $this->db->table('resource_equipment')
                    ->whereIn('category', $equipmentCategories)
                    ->update(['cost_category_id' => (int) $category['id']]);
            }
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('cost_category_id', 'resource_equipment')) {
            $this->db->query('ALTER TABLE `resource_equipment` DROP FOREIGN KEY `resource_equipment_cost_category_id_foreign`');
            $this->forge->dropColumn('resource_equipment', 'cost_category_id');
        }
    }
}
