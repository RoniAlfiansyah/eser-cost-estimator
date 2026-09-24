<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddApprovalToCostProposals extends Migration
{
    public function up()
    {
        $columnDefinitions = [
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
            if (! $this->db->fieldExists($column, 'cost_proposals')) {
                $this->forge->addColumn('cost_proposals', [$column => $definition]);
            }
        }

        if (! $this->db->tableExists('cost_proposal_approval_histories')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'cost_proposal_id' => [
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
            $this->forge->addKey('cost_proposal_id');
            $this->forge->addKey('actor_id');
            $this->forge->addForeignKey('cost_proposal_id', 'cost_proposals', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('actor_id', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('cost_proposal_approval_histories', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('cost_proposal_approval_histories', true);
        $this->forge->dropColumn('cost_proposals', [
            'submitted_at',
            'submitted_by',
            'reviewed_at',
            'reviewed_by',
            'review_notes',
        ]);
    }
}
