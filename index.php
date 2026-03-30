<?php
require_once __DIR__ . '/db.php';

$pdo = app_require_db();
$default_state = [
    'funding_goal' => 4000000,
    'current_balance' => 2350000,
    'total_payouts' => 1620000,
    'total_expenses' => 185000,
    'outstanding_challenges' => 5,
];

$state_stmt = $pdo->query('SELECT funding_goal, current_balance, total_payouts, total_expenses, outstanding_challenges FROM dashboard_state WHERE id = 1 LIMIT 1');
$state = $state_stmt->fetch();
if (!$state) {
    $insert_default = $pdo->prepare('
        INSERT INTO dashboard_state (id, funding_goal, current_balance, total_payouts, total_expenses, outstanding_challenges)
        VALUES (1, :funding_goal, :current_balance, :total_payouts, :total_expenses, :outstanding_challenges)
    ');
    $insert_default->execute($default_state);
    $state = $default_state;
} else {
    $state = [
        'funding_goal' => (int)$state['funding_goal'],
        'current_balance' => (int)$state['current_balance'],
        'total_payouts' => (int)$state['total_payouts'],
        'total_expenses' => (int)$state['total_expenses'],
        'outstanding_challenges' => (int)$state['outstanding_challenges'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $state['funding_goal'] = max(0, (int)($_POST['funding_goal'] ?? $state['funding_goal']));
    $state['current_balance'] = max(0, (int)($_POST['current_balance'] ?? $state['current_balance']));
    $state['total_payouts'] = max(0, (int)($_POST['total_payouts'] ?? $state['total_payouts']));
    $state['total_expenses'] = max(0, (int)($_POST['total_expenses'] ?? $state['total_expenses']));
    $state['outstanding_challenges'] = max(0, (int)($_POST['outstanding_challenges'] ?? $state['outstanding_challenges']));

    $upsert_state = $pdo->prepare('
        INSERT INTO dashboard_state (id, funding_goal, current_balance, total_payouts, total_expenses, outstanding_challenges)
        VALUES (1, :funding_goal, :current_balance, :total_payouts, :total_expenses, :outstanding_challenges)
        ON CONFLICT (id) DO UPDATE SET
            funding_goal = EXCLUDED.funding_goal,
            current_balance = EXCLUDED.current_balance,
            total_payouts = EXCLUDED.total_payouts,
            total_expenses = EXCLUDED.total_expenses,
            outstanding_challenges = EXCLUDED.outstanding_challenges
    ');
    $upsert_state->execute($state);

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

function format_number($num) {
    return number_format($num, 0, '.', ',');
}

$progress = min(100, $state['funding_goal'] > 0 ? ($state['current_balance'] / $state['funding_goal']) * 100 : 0);
$active_nav = 'dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Panel - Rorhonco</title>
    <style>
        * {box-sizing: border-box;}
        body {font-family: Arial, sans-serif; background:
            radial-gradient(circle at top left, rgba(59, 130, 246, 0.20), transparent 28%),
            radial-gradient(circle at top right, rgba(14, 165, 233, 0.18), transparent 24%),
            linear-gradient(180deg, #dfe9ff 0%, #edf4ff 42%, #f5f8ff 100%);
            color: #111827; margin: 0;}
        .app-shell {min-height: 100vh; display: flex;}
        .sidebar {width: 260px; background: linear-gradient(180deg, #0b1f52 0%, #12398a 100%); color: #fff; padding: 28px 20px; display: flex; flex-direction: column; gap: 24px; box-shadow: 18px 0 36px rgba(11, 31, 82, 0.18);}
        .brand-title {font-size: 1.35rem; font-weight: 800; margin: 0;}
        .sidebar-nav {display: flex; flex-direction: column; gap: 10px;}
        .sidebar-link {display: block; padding: 14px 16px; border-radius: 16px; color: rgba(255,255,255,0.86); text-decoration: none; font-weight: 700; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.05); transition: background-color 0.2s ease, transform 0.2s ease;}
        .sidebar-link:hover {background: rgba(255,255,255,0.12); transform: translateX(2px);}
        .sidebar-link.active {background: rgba(255,255,255,0.18); color: #fff; border-color: rgba(191, 219, 254, 0.4); box-shadow: inset 0 0 0 1px rgba(255,255,255,0.08);}
        .main-content {flex: 1; padding: 28px;}
        .container {max-width: 1100px; margin: 0 auto;}
        .dashboard-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #071a44 0%, #12398a 58%, #2d6cdf 100%);
            border-radius: 30px;
            padding: 30px;
            color: #fff;
            box-shadow: 0 28px 55px rgba(15, 23, 42, 0.18);
        }
        .dashboard-hero::before {
            content: "";
            position: absolute;
            inset: auto -40px -70px auto;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }
        .dashboard-hero::after {
            content: "";
            position: absolute;
            top: -80px;
            right: 180px;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(147, 197, 253, 0.18);
        }
        .hero-top {position: relative; z-index: 1; display: grid; grid-template-columns: minmax(0, 1.2fr) 280px; gap: 24px; align-items: center;}
        .eyebrow {display: inline-block; padding: 8px 12px; border-radius: 999px; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.14); font-size: 0.8rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase;}
        h1 {font-size: 2.4rem; line-height: 1.05; margin: 16px 0 10px;}
        .hero-balance {
            position: relative;
            z-index: 1;
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 24px;
            padding: 22px;
            backdrop-filter: blur(10px);
        }
        .hero-balance-label {display: block; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(255,255,255,0.72); margin-bottom: 6px;}
        .hero-balance-value {display: block; font-size: 2.1rem; font-weight: 800; margin-bottom: 10px;}
        .hero-balance-meta {display: flex; justify-content: space-between; gap: 12px; color: rgba(255,255,255,0.78); font-size: 0.92rem;}
        .progress-panel {
            position: relative;
            z-index: 1;
            margin-top: 26px;
            padding: 20px 22px;
            border-radius: 24px;
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.12);
        }
        .progress-head {display: flex; justify-content: space-between; gap: 16px; align-items: center; margin-bottom: 12px;}
        .progress-title {font-size: 1rem; font-weight: 800;}
        .progress-caption {color: rgba(255,255,255,0.74); font-size: 0.92rem;}
        .progress {width: 100%; height: 18px; background: rgba(255,255,255,0.16); border-radius: 999px; overflow: hidden; margin-top: 10px;}
        .progress > div {height: 100%; background: linear-gradient(90deg, #7dd3fc 0%, #ffffff 48%, #86efac 100%); width: <?php echo number_format($progress, 2); ?>%; box-shadow: 0 0 18px rgba(255,255,255,0.25);}
        .progress-meta {display: flex; justify-content: space-between; gap: 12px; margin-top: 12px; color: rgba(255,255,255,0.82); font-size: 0.95rem;}
        .stats-grid {display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; margin-top: 22px;}
        .stat-card {
            background: rgba(255,255,255,0.94);
            border: 1px solid rgba(191, 219, 254, 0.7);
            border-radius: 24px;
            padding: 22px;
            box-shadow: 0 20px 38px rgba(15, 23, 42, 0.08);
        }
        .stat-card.payouts {background: linear-gradient(180deg, #ffffff 0%, #eff6ff 100%);}
        .stat-card.expenses {background: linear-gradient(180deg, #ffffff 0%, #fff7ed 100%);}
        .stat-card.challenges {background: linear-gradient(180deg, #ffffff 0%, #f5f3ff 100%);}
        .stat-label {display: block; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; margin-bottom: 10px;}
        .stat-value {display: block; font-size: 2rem; font-weight: 800; color: #0f172a; margin-bottom: 8px;}
        .editor-panel {
            margin-top: 22px;
            background: rgba(255,255,255,0.84);
            border: 1px solid rgba(191, 219, 254, 0.7);
            border-radius: 28px;
            padding: 24px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(12px);
        }
        .editor-header {display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 18px;}
        .editor-title {margin: 0; font-size: 1.4rem; color: #0f172a;}
        .editor-pill {padding: 9px 12px; border-radius: 999px; background: #dbeafe; color: #1d4ed8; font-weight: 800; white-space: nowrap;}
        .form-grid {display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 18px;}
        .form-group {display:flex; flex-direction: column;}
        label {font-weight: 800; margin-bottom: 7px; color: #334155;}
        input {
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            background: #fff;
            box-shadow: inset 0 1px 2px rgba(15,23,42,0.03);
        }
        input:focus {outline: none; border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59,130,246,0.12);}
        .save-row {display: flex; justify-content: flex-end; margin-top: 18px;}
        button {
            margin-top: 0;
            padding: 13px 18px;
            background: linear-gradient(135deg, #0f3ca6 0%, #2563eb 100%);
            color: white;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            font-weight: 800;
            box-shadow: 0 16px 26px rgba(37, 99, 235, 0.22);
        }
        button:hover {transform: translateY(-1px); box-shadow: 0 18px 28px rgba(37, 99, 235, 0.28);}
        @media (max-width: 900px) {
            .app-shell {flex-direction: column;}
            .sidebar {width: 100%; box-shadow: none;}
            .main-content {padding: 18px;}
            .hero-top, .stats-grid, .form-grid, .editor-header {grid-template-columns: 1fr;}
            .hero-top, .editor-header {display: grid;}
            .stats-grid, .form-grid {grid-template-columns: 1fr;}
            h1 {font-size: 2rem;}
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div>
                <h2 class="brand-title">RORHO &amp; CO</h2>
            </div>
            <nav class="sidebar-nav">
                <a class="sidebar-link <?php echo $active_nav === 'dashboard' ? 'active' : ''; ?>" href="index.php">Financial Dashboard</a>
                <a class="sidebar-link" href="funding.php?tab=funding-tab">Funding Activity</a>
                <a class="sidebar-link" href="funding.php?tab=calendar-tab">Calendar</a>
                <a class="sidebar-link" href="funding.php?tab=calendar-tab">Monthly PnL</a>
                <a class="sidebar-link" href="funding.php?tab=performance-metrics-tab">Performance Metrics</a>
                <a class="sidebar-link" href="weekly_journal.php">Weekly Journal</a>
            </nav>
        </aside>
        <main class="main-content">
            <div class="container">
                <section class="dashboard-hero">
                    <div class="hero-top">
                        <div>
                            <span class="eyebrow">Financial Dashboard</span>
                            <h1>Build a stronger view of your capital, progress, and pressure points.</h1>
                        </div>
                        <div class="hero-balance">
                            <span class="hero-balance-label">Current Balance</span>
                            <span class="hero-balance-value">$<?php echo format_number($state['current_balance']); ?></span>
                            <div class="hero-balance-meta">
                                <span>Goal</span>
                                <strong>$<?php echo format_number($state['funding_goal']); ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="progress-panel">
                        <div class="progress-head">
                            <div>
                                <div class="progress-title">Funding Progress</div>
                                <div class="progress-caption">Measured against your full target capital.</div>
                            </div>
                            <strong><?php echo number_format($progress, 2); ?>%</strong>
                        </div>
                        <div class="progress"><div></div></div>
                        <div class="progress-meta">
                            <span>Current: $<?php echo format_number($state['current_balance']); ?></span>
                            <span>Target: $<?php echo format_number($state['funding_goal']); ?></span>
                        </div>
                    </div>
                </section>

                <section class="stats-grid">
                    <article class="stat-card payouts">
                        <span class="stat-label">Total Payouts</span>
                        <span class="stat-value">$<?php echo format_number($state['total_payouts']); ?></span>
                    </article>
                    <article class="stat-card expenses">
                        <span class="stat-label">Total Expenses</span>
                        <span class="stat-value">$<?php echo format_number($state['total_expenses']); ?></span>
                    </article>
                    <article class="stat-card challenges">
                        <span class="stat-label">Challenges</span>
                        <span class="stat-value"><?php echo format_number($state['outstanding_challenges']); ?></span>
                    </article>
                </section>

                <form method="post" class="editor-panel">
                    <div class="editor-header">
                        <div>
                            <h2 class="editor-title">Update Financial Values</h2>
                        </div>
                        <div class="editor-pill">Control Panel</div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Funding Goal</label><input name="funding_goal" type="number" value="<?php echo $state['funding_goal']; ?>" min="0"></div>
                        <div class="form-group"><label>Current Balance</label><input name="current_balance" type="number" value="<?php echo $state['current_balance']; ?>" min="0"></div>
                        <div class="form-group"><label>Total Payouts</label><input name="total_payouts" type="number" value="<?php echo $state['total_payouts']; ?>" min="0"></div>
                        <div class="form-group"><label>Total Expenses</label><input name="total_expenses" type="number" value="<?php echo $state['total_expenses']; ?>" min="0"></div>
                        <div class="form-group"><label>Challenges</label><input name="outstanding_challenges" type="number" value="<?php echo $state['outstanding_challenges']; ?>" min="0"></div>
                    </div>
                    <div class="save-row">
                        <button type="submit">Save Dashboard</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
