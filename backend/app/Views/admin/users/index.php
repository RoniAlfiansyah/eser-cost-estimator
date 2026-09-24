<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="card">
    <span class="badge">Admin Module</span>
    <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap;">
        <div>
            <h2 style="margin: 16px 0 10px;">User Management</h2>
            <p style="margin-top:0; color:#6b7280;">Kelola pengguna sistem, role akses, dan status akun dari satu tempat.</p>
        </div>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <a class="btn btn-secondary" href="<?= site_url('admin/audit-logs') ?>">Audit Logs</a>
            <a class="btn" href="<?= site_url('admin/users/create') ?>">Tambah User</a>
        </div>
    </div>

    <table class="table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Nama</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Last Login</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($users === []): ?>
            <tr>
                <td colspan="7" style="text-align:center; color:#6b7280;">Belum ada data user.</td>
            </tr>
        <?php endif; ?>
        <?php foreach ($users as $item): ?>
            <tr>
                <td><?= esc((string) $item['id']) ?></td>
                <td><?= esc($item['name']) ?></td>
                <td><?= esc($item['email']) ?></td>
                <td><?= esc(ucfirst($item['role'])) ?></td>
                <td>
                    <span class="badge" style="background: <?= $item['is_active'] ? '#dcfce7' : '#fee2e2' ?>; color: <?= $item['is_active'] ? '#166534' : '#991b1b' ?>;">
                        <?= $item['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td><?= esc($item['last_login_at'] ? $item['last_login_at']->toDateTimeString() : '-') ?></td>
                <td>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <a class="btn btn-secondary" href="<?= site_url('admin/users/' . $item['id'] . '/edit') ?>">Edit</a>
                        <form method="post" action="<?= site_url('admin/users/' . $item['id'] . '/toggle-status') ?>" style="display:inline;">
                            <?= csrf_field() ?>
                            <button class="btn btn-secondary" type="submit">
                                <?= $item['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
                        </form>
                        <form method="post" action="<?= site_url('admin/users/' . $item['id'] . '/delete') ?>" style="display:inline;" onsubmit="return confirm('Hapus user ini?');">
                            <?= csrf_field() ?>
                            <button class="btn" type="submit" style="background:#b91c1c;">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?= $this->endSection() ?>
