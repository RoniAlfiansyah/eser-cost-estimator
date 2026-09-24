<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ArchiveLegacySchedulerAudit extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'legacy_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'source_user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 100],
            'entity_type' => ['type' => 'VARCHAR', 'constraint' => 100],
            'entity_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'details' => ['type' => 'LONGTEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('legacy_id', true);
        $this->forge->createTable('legacy_scheduler_audit_logs', true);
    }

    public function down()
    {
        $this->forge->dropTable('legacy_scheduler_audit_logs', true);
    }
}
