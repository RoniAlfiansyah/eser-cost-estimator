<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddApprovalToBasicCosts extends Migration
{
    public function up()
    {
        $columnDefinitions = [
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'draft',
                'after'      => 'grand_total',
            ],
            'submitted_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'status',
            ],
            'submitted_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'submitted_at',
            ],
            'reviewed_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'submitted_by',
            ],
            'reviewed_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'reviewed_at',
            ],
            'review_notes' => [
                'type'  => 'TEXT',
                'null'  => true,
                'after' => 'reviewed_by',
            ],
        ];

        foreach ($columnDefinitions as $column => $definition) {
            if (! $this->db->fieldExists($column, 'basic_costs')) {
                $this->forge->addColumn('basic_costs', [$column => $definition]);
            }
        }

        if (! $this->db->tableExists('basic_cost_approval_histories')) {
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
                'action' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                ],
                'status_from' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'null'       => true,
                ],
                'status_to' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                ],
                'note' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'actor_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'actor_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 180,
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
            $this->forge->addKey('basic_cost_id');
            $this->forge->addKey('actor_id');
            $this->forge->addForeignKey('basic_cost_id', 'basic_costs', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('actor_id', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('basic_cost_approval_histories', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('basic_cost_approval_histories', true);
        $this->forge->dropColumn('basic_costs', [
            'status',
            'submitted_at',
            'submitted_by',
            'reviewed_at',
            'reviewed_by',
            'review_notes',
        ]);
    }
}
