<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Project Costing System') ?></title>
    <style>
        :root {
            --bg: #f5f7fb;
            --panel: #ffffff;
            --ink: #1f2937;
            --muted: #6b7280;
            --line: #dbe3ef;
            --brand: #0f766e;
            --brand-dark: #134e4a;
            --accent: #f59e0b;
            --danger: #b91c1c;
            --success: #166534;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(180deg, #eef6f5 0%, var(--bg) 220px);
            color: var(--ink);
        }
        a { color: inherit; text-decoration: none; }
        .shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--line);
            padding: 16px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .brand-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--brand-dark);
        }
        .brand-subtitle {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: .9rem;
        }
        .topbar-right {
            display: flex;
            gap: 16px;
            align-items: center;
        }
        .container {
            width: min(1120px, calc(100% - 32px));
            margin: 32px auto;
        }
        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }
        .grid {
            display: grid;
            gap: 20px;
        }
        .stats {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .stat-value {
            margin: 10px 0 4px;
            font-size: 2rem;
            font-weight: 700;
        }
        .stat-label {
            color: var(--muted);
            font-size: .95rem;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: #ccfbf1;
            color: var(--brand-dark);
            padding: 6px 12px;
            font-size: .85rem;
            font-weight: 600;
        }
        .flash {
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 20px;
        }
        .flash-success {
            background: #ecfdf5;
            color: var(--success);
            border: 1px solid #bbf7d0;
        }
        .flash-error {
            background: #fef2f2;
            color: var(--danger);
            border: 1px solid #fecaca;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 12px;
            padding: 11px 16px;
            background: var(--brand);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: var(--ink);
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--line);
            text-align: left;
        }
        .table th {
            color: var(--muted);
            font-size: .9rem;
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .topbar {
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }
            .topbar-right {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
<div class="shell">
    <?php if (is_logged_in()): ?>
        <header class="topbar">
            <div>
                <h1 class="brand-title">Project Costing System</h1>
                <p class="brand-subtitle">Struktur awal aplikasi costing berbasis CodeIgniter 4</p>
            </div>
            <div class="topbar-right">
                <span class="badge"><?= esc(ucfirst((string) current_user('role'))) ?></span>
                <div>
                    <strong><?= esc((string) current_user('name')) ?></strong><br>
                    <small style="color: var(--muted);"><?= esc((string) current_user('email')) ?></small>
                </div>
                <a class="btn btn-secondary" href="<?= site_url('logout') ?>">Logout</a>
            </div>
        </header>
    <?php endif; ?>

    <main class="container">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="flash flash-success"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="flash flash-error"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <?= $this->renderSection('content') ?>
    </main>
</div>
</body>
</html>
