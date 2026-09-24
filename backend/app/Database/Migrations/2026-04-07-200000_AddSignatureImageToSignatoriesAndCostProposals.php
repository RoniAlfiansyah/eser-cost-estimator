<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSignatureImageToSignatoriesAndCostProposals extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('signature_image_path', 'signatories')) {
            $this->forge->addColumn('signatories', [
                'signature_image_path' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                    'after' => 'position_title',
                ],
            ]);
        }

        if (! $this->db->fieldExists('signatory_signature_path', 'cost_proposals')) {
            $this->forge->addColumn('cost_proposals', [
                'signatory_signature_path' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                    'after' => 'signatory_title',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('signatory_signature_path', 'cost_proposals')) {
            $this->forge->dropColumn('cost_proposals', 'signatory_signature_path');
        }

        if ($this->db->fieldExists('signature_image_path', 'signatories')) {
            $this->forge->dropColumn('signatories', 'signature_image_path');
        }
    }
}
