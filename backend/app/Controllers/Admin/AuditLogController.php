<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditLogModel;

class AuditLogController extends BaseController
{
    public function index(): string
    {
        $auditLogModel = new AuditLogModel();

        return view('admin/audit_logs/index', [
            'title' => 'Audit Logs',
            'logs'  => $auditLogModel->getRecentLogs(100),
        ]);
    }
}
