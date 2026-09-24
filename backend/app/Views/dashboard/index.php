<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="card" style="margin-bottom: 22px;">
    <span class="badge">Dashboard <?= esc(ucfirst((string) ($user['role'] ?? ''))) ?></span>
    <h2 style="margin: 16px 0 10px; font-size: 2rem;">Selamat datang, <?= esc((string) ($user['name'] ?? 'User')) ?></h2>
    <p style="margin:0; color:#6b7280; line-height:1.7;">
        Ini adalah dashboard awal untuk aplikasi Project Costing System. Struktur auth, role, database,
        controller, model, dan view sudah disiapkan agar mudah dikembangkan ke modul costing, approval,
        customer, vendor, dan laporan.
    </p>
</section>

<section class="grid stats" style="margin-bottom: 22px;">
    <article class="card">
        <div class="stat-label">Total Pengguna</div>
        <div class="stat-value"><?= esc((string) $stats['totalUsers']) ?></div>
        <div class="stat-label">Semua akun terdaftar</div>
    </article>
    <article class="card">
        <div class="stat-label">Pengguna Aktif</div>
        <div class="stat-value"><?= esc((string) $stats['activeUsers']) ?></div>
        <div class="stat-label">Akun aktif saat ini</div>
    </article>
    <article class="card">
        <div class="stat-label">Admin</div>
        <div class="stat-value"><?= esc((string) $stats['adminUsers']) ?></div>
        <div class="stat-label">Pengelola sistem</div>
    </article>
    <article class="card">
        <div class="stat-label">Manager</div>
        <div class="stat-value"><?= esc((string) $stats['managerUsers']) ?></div>
        <div class="stat-label">Pemberi approval</div>
    </article>
    <article class="card">
        <div class="stat-label">Staff</div>
        <div class="stat-value"><?= esc((string) $stats['staffUsers']) ?></div>
        <div class="stat-label">Pelaksana input data</div>
    </article>
</section>

<section class="grid" style="grid-template-columns: 1.2fr .8fr;">
    <article class="card">
        <h3 style="margin-top:0;">Modul Inti yang Disiapkan</h3>
        <table class="table">
            <thead>
            <tr>
                <th>Modul</th>
                <th>Status</th>
                <th>Keterangan</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>Authentication</td>
                <td>Ready</td>
                <td>Login, logout, session, role-based access</td>
            </tr>
            <tr>
                <td>Dashboard</td>
                <td>Ready</td>
                <td>Halaman awal setelah login</td>
            </tr>
            <tr>
                <td>User Management</td>
                <td>Starter</td>
                <td>Model dan tabel user sudah tersedia</td>
            </tr>
            <tr>
                <td>Project Costing</td>
                <td>Next</td>
                <td>Siap ditambahkan sebagai modul berikutnya</td>
            </tr>
            </tbody>
        </table>
    </article>

    <aside class="card">
        <h3 style="margin-top:0;">Hak Akses Role</h3>
        <p style="color:#6b7280; line-height:1.7;">
            Role aktif Anda adalah <strong><?= esc(ucfirst((string) ($user['role'] ?? ''))) ?></strong>.
            Filter role sudah disiapkan untuk memisahkan akses antar modul.
        </p>
        <p style="margin-top: 18px;">
            <?php if (($user['role'] ?? '') === 'admin'): ?>
                <a class="btn" href="<?= site_url('admin/users') ?>">Buka User Management</a>
            <?php elseif (($user['role'] ?? '') === 'manager'): ?>
                <a class="btn" href="<?= site_url('manager/approvals') ?>">Buka Approval Center</a>
            <?php else: ?>
                <a class="btn" href="<?= site_url('staff/projects') ?>">Buka Project Workspace</a>
            <?php endif; ?>
        </p>
        <ul style="padding-left: 20px; line-height: 1.8; margin-bottom: 0;">
            <li>Admin dapat mengelola user dan konfigurasi sistem.</li>
            <li>Manager dapat melakukan review dan approval costing.</li>
            <li>Staff dapat input data proyek dan detail costing.</li>
        </ul>
    </aside>
</section>
<?= $this->endSection() ?>
