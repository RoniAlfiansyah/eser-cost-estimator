<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="card">
    <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap;">
        <div>
            <span class="badge">Admin Module</span>
            <h2 style="margin: 16px 0 10px;">Audit Logs</h2>
            <p style="margin-top:0; color:#6b7280;">Riwayat aktivitas penting pengguna untuk login, logout, dan perubahan data user.</p>
        </div>
        <a class="btn btn-secondary" href="<?= site_url('admin/users') ?>">Kembali ke User Management</a>
    </div>

    <table class="table">
        <thead>
        <tr>
            <th>Waktu</th>
            <th>User</th>
            <th>Module</th>
            <th>Action</th>
            <th>Description</th>
            <th>Target</th>
            <th>IP</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($logs === []): ?>
            <tr>
                <td colspan="7" style="text-align:center; color:#6b7280;">Belum ada audit log.</td>
            </tr>
        <?php endif; ?>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= esc($log['created_at'] ? $log['created_at']->toDateTimeString() : '-') ?></td>
                <td>
                    <strong><?= esc($log['user_name'] ?? 'System') ?></strong><br>
                    <small style="color:#6b7280;"><?= esc($log['user_email'] ?? '-') ?></small>
                </td>
                <td><?= esc(ucwords(str_replace('_', ' ', (string) $log['module']))) ?></td>
                <td><?= esc(ucwords(str_replace('_', ' ', (string) $log['action']))) ?></td>
                <td><?= esc($log['description'] ?? '-') ?></td>
                <td><?= esc(trim(($log['target_type'] ?? '-') . ' #' . ($log['target_id'] ?? '-'))) ?></td>
                <td><?= esc($log['ip_address'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?= $this->endSection() ?>
