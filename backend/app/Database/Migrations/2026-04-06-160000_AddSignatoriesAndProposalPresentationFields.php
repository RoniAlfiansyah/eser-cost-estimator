<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSignatoriesAndProposalPresentationFields extends Migration
{
    public function up()
    {
        $this->createSignatoriesTable();
        $this->addProposalFields();
    }

    public function down()
    {
        if ($this->db->fieldExists('signatory_id', 'cost_proposals')) {
            $this->forge->dropColumn('cost_proposals', [
                'signatory_id',
                'signatory_name',
                'signatory_title',
                'attention_name',
                'proposal_subject',
                'attachment_label',
                'public_token',
            ]);
        }

        $this->forge->dropTable('signatories', true);
    }

    private function createSignatoriesTable(): void
    {
        if ($this->db->tableExists('signatories')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'position_title' => ['type' => 'VARCHAR', 'constraint' => 180],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('is_active');
        $this->forge->addKey('sort_order');
        $this->forge->createTable('signatories', true);
    }

    private function addProposalFields(): void
    {
        $fields = [
            'signatory_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'payment_terms',
            ],
            'signatory_name' => [
                'type' => 'VARCHAR',
                'constraint' => 180,
                'null' => true,
                'after' => 'signatory_id',
            ],
            'signatory_title' => [
                'type' => 'VARCHAR',
                'constraint' => 180,
                'null' => true,
                'after' => 'signatory_name',
            ],
            'attention_name' => [
                'type' => 'VARCHAR',
                'constraint' => 180,
                'null' => true,
                'after' => 'signatory_title',
            ],
            'proposal_subject' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'attention_name',
            ],
            'attachment_label' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'proposal_subject',
            ],
            'public_token' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
                'after' => 'attachment_label',
            ],
        ];

        foreach ($fields as $name => $definition) {
            if (! $this->db->fieldExists($name, 'cost_proposals')) {
                $this->forge->addColumn('cost_proposals', [$name => $definition]);
            }
        }

        try {
            $this->db->query('ALTER TABLE `cost_proposals` ADD CONSTRAINT `cost_proposals_signatory_id_foreign` FOREIGN KEY (`signatory_id`) REFERENCES `signatories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (\Throwable $exception) {
            // Constraint may already exist.
        }
    }
}
