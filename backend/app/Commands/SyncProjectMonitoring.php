<?php

namespace App\Commands;

use App\Libraries\ProjectMonitoringSync;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Psr\Log\LoggerInterface;

class SyncProjectMonitoring extends BaseCommand
{
    protected $group = 'Scheduler';
    protected $name = 'monitoring:sync-projects';
    protected $description = 'Sinkronkan project dan baseline Scheduler ke Client Project Monitoring Portal.';
    protected $usage = 'monitoring:sync-projects [project-id]';

    public function run(array $params)
    {
        $db = db_connect();
        $builder = $db->table('scheduler_projects')->where('archived', 0)->orderBy('id');
        if (! empty($params[0])) $builder->where('id', (int) $params[0]);
        $projects = $builder->get()->getResultArray();
        if (! $projects) {
            CLI::error('Project Scheduler tidak ditemukan.');
            return;
        }

        $sync = new ProjectMonitoringSync();
        $failed = 0;
        foreach ($projects as $project) {
            $result = $sync->sync($project);
            $status = $result['status'] ?? 'failed';
            if ($status !== 'succeeded') $failed++;
            CLI::write(sprintf('[%s] #%d %s - %s aktivitas%s', strtoupper($status), $project['id'], $project['name'], $result['activityCount'] ?? 0, isset($result['message']) ? ' - ' . $result['message'] : ''), $status === 'succeeded' ? 'green' : 'yellow');
        }

        if ($failed > 0) CLI::error($failed . ' project belum tersinkron.');
        else CLI::write('Semua project aktif berhasil tersinkron.', 'green');
    }
}
