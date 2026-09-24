<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class IntegrateSchedulerModule extends Migration
{
    public function up()
    {
        $this->createSchedulerProjects();
        $this->createMasterWbs();
        $this->createMasterActivities();
        $this->createPersonnel();
        $this->createEquipment();
        $this->createCostSnapshots();
        $this->createResourceMappings();

        if (! $this->db->fieldExists('scheduler_project_id', 'basic_costs')) {
            $this->forge->addColumn('basic_costs', [
                'scheduler_project_id' => [
                    'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true,
                    'after' => 'id',
                ],
            ]);
            $this->db->query('ALTER TABLE `basic_costs` ADD KEY `scheduler_project_id` (`scheduler_project_id`)');
            $this->db->query('ALTER TABLE `basic_costs` ADD CONSTRAINT `basic_costs_scheduler_project_id_foreign` FOREIGN KEY (`scheduler_project_id`) REFERENCES `scheduler_projects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        }

        foreach ([
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'sort_order'],
            'source_key' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true, 'after' => 'source_type'],
            'source_payload' => ['type' => 'LONGTEXT', 'null' => true, 'after' => 'source_key'],
        ] as $name => $definition) {
            if (! $this->db->fieldExists($name, 'basic_cost_items')) {
                $this->forge->addColumn('basic_cost_items', [$name => $definition]);
            }
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('scheduler_project_id', 'basic_costs')) {
            $this->db->query('ALTER TABLE `basic_costs` DROP FOREIGN KEY `basic_costs_scheduler_project_id_foreign`');
            $this->forge->dropColumn('basic_costs', 'scheduler_project_id');
        }
        foreach (['source_payload', 'source_key', 'source_type'] as $column) {
            if ($this->db->fieldExists($column, 'basic_cost_items')) {
                $this->forge->dropColumn('basic_cost_items', $column);
            }
        }
        $this->forge->dropTable('resource_cost_item_mappings', true);
        $this->forge->dropTable('scheduler_cost_snapshots', true);
        $this->forge->dropTable('resource_equipment', true);
        $this->forge->dropTable('resource_personnel', true);
        $this->forge->dropTable('scheduler_master_activities', true);
        $this->forge->dropTable('scheduler_master_wbs', true);
        $this->forge->dropTable('scheduler_projects', true);
    }

    private function timestamps(): array
    {
        return [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];
    }

    private function createSchedulerProjects(): void
    {
        $this->forge->addField(array_merge([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'client_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'location' => ['type' => 'VARCHAR', 'constraint' => 220, 'null' => true],
            'owner_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'payload' => ['type' => 'LONGTEXT'],
            'archived' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'legacy_scheduler_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addKey('owner_id');
        $this->forge->addUniqueKey('legacy_scheduler_id');
        $this->forge->addForeignKey('owner_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('scheduler_projects', true);
    }

    private function createMasterWbs(): void
    {
        $this->forge->addField(array_merge([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 30],
            'name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'prefix' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('scheduler_master_wbs', true);
    }

    private function createMasterActivities(): void
    {
        $this->forge->addField(array_merge([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'wbs_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'activity_code' => ['type' => 'VARCHAR', 'constraint' => 30],
            'name' => ['type' => 'VARCHAR', 'constraint' => 220],
            'default_duration' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 1],
            'default_pic' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addKey('wbs_id');
        $this->forge->addUniqueKey('activity_code');
        $this->forge->addForeignKey('wbs_id', 'scheduler_master_wbs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('scheduler_master_activities', true);
    }

    private function createPersonnel(): void
    {
        $this->forge->addField(array_merge([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'role_name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'daily_rate' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'standby_rate' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'field_allowance' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'accommodation_rate' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'meal_rate' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'transport_rate' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'overtime_rate' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'legacy_scheduler_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('resource_personnel', true);
    }

    private function createEquipment(): void
    {
        $this->forge->addField(array_merge([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 60],
            'name' => ['type' => 'VARCHAR', 'constraint' => 220],
            'category' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'brand' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'model' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'asset_tag' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'specification' => ['type' => 'TEXT', 'null' => true],
            'daily_rate' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'standby_rate' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'mobilization_cost' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'demobilization_cost' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'fuel_daily' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'operator_included' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'legacy_scheduler_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('resource_equipment', true);
    }

    private function createCostSnapshots(): void
    {
        $this->forge->addField(array_merge([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'scheduler_project_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'payload' => ['type' => 'LONGTEXT'],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addKey('scheduler_project_id');
        $this->forge->addForeignKey('scheduler_project_id', 'scheduler_projects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('scheduler_cost_snapshots', true);
    }

    private function createResourceMappings(): void
    {
        $this->forge->addField(array_merge([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'resource_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'resource_code' => ['type' => 'VARCHAR', 'constraint' => 60],
            'cost_component' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'working'],
            'cost_item_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addKey('cost_item_id');
        $this->forge->addUniqueKey(['resource_type', 'resource_code', 'cost_component'], 'resource_component_unique');
        $this->forge->addForeignKey('cost_item_id', 'cost_items', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('resource_cost_item_mappings', true);
    }
}
