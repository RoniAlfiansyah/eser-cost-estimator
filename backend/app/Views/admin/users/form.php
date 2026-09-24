<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<style>
    .form-shell {
        max-width: 760px;
    }
    .form-grid {
        display: grid;
        gap: 18px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .form-group.full {
        grid-column: 1 / -1;
    }
    .form-label {
        font-weight: 600;
    }
    .form-control, .form-select {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 12px 14px;
        font-size: 1rem;
        background: #fff;
    }
    .help-text {
        color: #6b7280;
        font-size: .9rem;
    }
    .error-text {
        color: #b91c1c;
        font-size: .88rem;
    }
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="card form-shell">
    <span class="badge">Admin Module</span>
    <h2 style="margin:16px 0 10px;"><?= esc($title) ?></h2>
    <p style="margin-top:0; color:#6b7280;">Lengkapi data user di bawah ini untuk mengelola akses aplikasi.</p>

    <form method="post" action="<?= esc($formAction) ?>">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label" for="name">Nama</label>
                <input class="form-control" id="name" name="name" type="text" value="<?= esc(old('name', $user['name'] ?? '')) ?>" required>
                <?php if (session('errors.name')): ?>
                    <small class="error-text"><?= esc(session('errors.name')) ?></small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" name="email" type="email" value="<?= esc(old('email', $user['email'] ?? '')) ?>" required>
                <?php if (session('errors.email')): ?>
                    <small class="error-text"><?= esc(session('errors.email')) ?></small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input class="form-control" id="password" name="password" type="password" <?= $user === null ? 'required' : '' ?>>
                <small class="help-text">
                    <?= $user === null ? 'Minimal 6 karakter.' : 'Kosongkan jika password tidak ingin diubah.' ?>
                </small>
                <?php if (session('errors.password')): ?>
                    <small class="error-text"><?= esc(session('errors.password')) ?></small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="role">Role</label>
                <select class="form-select" id="role" name="role" required>
                    <?php foreach ($roles as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= old('role', $user['role'] ?? 'staff') === $value ? 'selected' : '' ?>>
                            <?= esc($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (session('errors.role')): ?>
                    <small class="error-text"><?= esc(session('errors.role')) ?></small>
                <?php endif; ?>
            </div>

            <div class="form-group full">
                <label class="form-label" for="is_active">Status</label>
                <select class="form-select" id="is_active" name="is_active">
                    <option value="1" <?= (string) old('is_active', (string) ($user['is_active'] ?? 1)) === '1' ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= (string) old('is_active', (string) ($user['is_active'] ?? 1)) === '0' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>

        <div style="display:flex; gap:12px; margin-top:24px; flex-wrap:wrap;">
            <button class="btn" type="submit"><?= esc($submitLabel) ?></button>
            <a class="btn btn-secondary" href="<?= site_url('admin/users') ?>">Kembali</a>
        </div>
    </form>
</section>
<?= $this->endSection() ?>
