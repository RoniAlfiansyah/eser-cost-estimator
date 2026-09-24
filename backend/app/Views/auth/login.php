<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<style>
    .auth-wrap {
        min-height: calc(100vh - 120px);
        display: grid;
        place-items: center;
    }
    .auth-grid {
        width: min(980px, 100%);
        display: grid;
        grid-template-columns: 1.1fr .9fr;
        overflow: hidden;
        border-radius: 24px;
        background: #ffffff;
        border: 1px solid #dbe3ef;
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
    }
    .auth-hero {
        padding: 42px;
        background:
            radial-gradient(circle at top left, rgba(245, 158, 11, 0.24), transparent 34%),
            linear-gradient(135deg, #0f766e 0%, #134e4a 100%);
        color: #ffffff;
    }
    .auth-form {
        padding: 42px;
    }
    .auth-form h2, .auth-hero h2 {
        margin-top: 0;
        margin-bottom: 10px;
    }
    .auth-copy {
        color: rgba(255, 255, 255, 0.82);
        line-height: 1.7;
    }
    .form-group {
        margin-bottom: 18px;
    }
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
    }
    .form-control {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 12px 14px;
        font-size: 1rem;
    }
    .error-text {
        display: block;
        margin-top: 8px;
        color: #b91c1c;
        font-size: .88rem;
    }
    .demo-box {
        margin-top: 24px;
        padding: 18px;
        border-radius: 16px;
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.18);
    }
    .demo-box p {
        margin: 0 0 10px;
    }
    @media (max-width: 860px) {
        .auth-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="auth-wrap">
    <div class="auth-grid">
        <section class="auth-hero">
            <h2>Project Costing System</h2>
            <p class="auth-copy">
                Fondasi aplikasi costing dengan struktur MVC yang siap dikembangkan untuk pengelolaan estimasi proyek,
                approval manager, dan operasional staff.
            </p>

        </section>

        <section class="auth-form">
            <h2>Login</h2>
            <p style="color:#6b7280; margin-top:0;">Masuk untuk mengakses dashboard sesuai peran Anda.</p>

            <form method="post" action="<?= site_url('login') ?>">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input
                        class="form-control"
                        id="email"
                        name="email"
                        type="email"
                        value="<?= old('email') ?>"
                        placeholder="nama@company.com"
                        required
                    >
                    <?php if (session('errors.email')): ?>
                        <small class="error-text"><?= esc(session('errors.email')) ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input
                        class="form-control"
                        id="password"
                        name="password"
                        type="password"
                        placeholder="Masukkan password"
                        required
                    >
                    <?php if (session('errors.password')): ?>
                        <small class="error-text"><?= esc(session('errors.password')) ?></small>
                    <?php endif; ?>
                </div>

                <button class="btn" type="submit" style="width:100%;">Login</button>
            </form>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
