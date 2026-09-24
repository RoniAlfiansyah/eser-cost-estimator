<?php

use App\Models\AuditLogModel;

if (! function_exists('write_audit_log')) {
    function write_audit_log(array $data): void
    {
        $request = service('request');
        $auditLogModel = new AuditLogModel();

        $payload = [
            'user_id'     => $data['user_id'] ?? current_user('id'),
            'action'      => $data['action'] ?? 'unknown',
            'module'      => $data['module'] ?? 'system',
            'description' => $data['description'] ?? null,
            'target_type' => $data['target_type'] ?? null,
            'target_id'   => isset($data['target_id']) ? (string) $data['target_id'] : null,
            'ip_address'  => $data['ip_address'] ?? $request->getIPAddress(),
            'user_agent'  => $data['user_agent'] ?? (string) $request->getUserAgent(),
            'properties'  => $data['properties'] ?? null,
        ];

        $auditLogModel->insert($payload);
    }
}
