<?php
require_once __DIR__ . '/db.php';

$pdo = app_require_db();
$funding = [];
$calendar = [];

$view_month = $_GET['month'] ?? date('Y-m');
$selected_date = $_GET['calendar_date'] ?? date('Y-m-d');
$active_tab = in_array($_GET['tab'] ?? 'funding-tab', ['funding-tab', 'calendar-tab', 'monthly-pnl-tab', 'performance-metrics-tab'], true) ? $_GET['tab'] : 'funding-tab';


function float_or_null($value) {
    $value = trim((string)$value);
    if ($value === '' || $value === null) {
        return null;
    }
    return (float)$value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['calendar_entry'])) {
        $cdate = $_POST['calendar_date'] ?? date('Y-m-d');
        $calendar_type = $_POST['calendar_type'] ?? 'profit';
        $calendar_theory = in_array($_POST['theory'] ?? '', ['logical', 'model'], true) ? $_POST['theory'] : 'logical';
        $calendar_ovr = in_array($_POST['ovr'] ?? '', ['good', 'bad'], true) ? $_POST['ovr'] : 'good';
        $calendar_session = implode(',', array_values(array_filter(array_map('trim', explode(',', $_POST['session'] ?? '')))));
        $calendar_outsession = implode(',', array_values(array_filter(array_map('trim', explode(',', $_POST['outsession'] ?? '')))));

        $upsert_calendar = $pdo->prepare('
            INSERT INTO calendar_entries (
                date, type, rr, percent_made, be, loss, win, reentry_win, reentry_loss, reentry_be,
                session, outsession, journal, theory, ovr, links, updated_at
            ) VALUES (
                :date, :type, :rr, :percent_made, :be, :loss, :win, :reentry_win, :reentry_loss, :reentry_be,
                :session, :outsession, :journal, :theory, :ovr, :links, NOW()
            )
            ON CONFLICT (date) DO UPDATE SET
                type = EXCLUDED.type,
                rr = EXCLUDED.rr,
                percent_made = EXCLUDED.percent_made,
                be = EXCLUDED.be,
                loss = EXCLUDED.loss,
                win = EXCLUDED.win,
                reentry_win = EXCLUDED.reentry_win,
                reentry_loss = EXCLUDED.reentry_loss,
                reentry_be = EXCLUDED.reentry_be,
                session = EXCLUDED.session,
                outsession = EXCLUDED.outsession,
                journal = EXCLUDED.journal,
                theory = EXCLUDED.theory,
                ovr = EXCLUDED.ovr,
                links = EXCLUDED.links,
                updated_at = NOW()
        ');
        $upsert_calendar->execute([
            'date' => $cdate,
            'type' => $calendar_type,
            'rr' => float_or_null($_POST['rr'] ?? ''),
            'percent_made' => float_or_null($_POST['percent_made'] ?? ''),
            'be' => float_or_null($_POST['be'] ?? ''),
            'loss' => float_or_null($_POST['loss'] ?? ''),
            'win' => float_or_null($_POST['win'] ?? ''),
            'reentry_win' => float_or_null($_POST['reentry_win'] ?? ''),
            'reentry_loss' => float_or_null($_POST['reentry_loss'] ?? ''),
            'reentry_be' => float_or_null($_POST['reentry_be'] ?? ''),
            'session' => $calendar_session,
            'outsession' => $calendar_outsession,
            'journal' => trim($_POST['journal'] ?? ''),
            'theory' => $calendar_theory,
            'ovr' => $calendar_ovr,
            'links' => trim($_POST['links'] ?? ''),
        ]);

        header('Location: ' . $_SERVER['PHP_SELF'] . '?month=' . urlencode($_POST['calendar_month'] ?? date('Y-m')));
        exit;
    }

    $insert_funding = $pdo->prepare('
        INSERT INTO funding_entries (date, amount, firm, action, status)
        VALUES (:date, :amount, :firm, :action, :status)
    ');
    $insert_funding->execute([
        'date' => $_POST['date'] ?? date('Y-m-d'),
        'amount' => (float)($_POST['amount'] ?? 0.0),
        'firm' => trim($_POST['firm'] ?? ''),
        'action' => trim($_POST['action'] ?? ''),
        'status' => ($_POST['status'] ?? 'ongoing') === 'gone' ? 'gone' : 'ongoing',
    ]);

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$funding_rows = $pdo->query('
    SELECT date::text AS date, amount::float8 AS amount, firm, action, status
    FROM funding_entries
    ORDER BY date DESC, id DESC
')->fetchAll();

foreach ($funding_rows as $row) {
    $funding[] = [
        'date' => $row['date'],
        'amount' => (float)$row['amount'],
        'firm' => (string)$row['firm'],
        'action' => (string)$row['action'],
        'status' => (string)$row['status'],
    ];
}

$calendar_rows = $pdo->query('
    SELECT
        date::text AS date_key, type, rr::float8 AS rr, percent_made::float8 AS percent_made,
        be::float8 AS be, loss::float8 AS loss, win::float8 AS win,
        reentry_win::float8 AS reentry_win, reentry_loss::float8 AS reentry_loss, reentry_be::float8 AS reentry_be,
        session, outsession, journal, theory, ovr, links
    FROM calendar_entries
')->fetchAll();

foreach ($calendar_rows as $row) {
    $calendar[(string)$row['date_key']] = [
        'type' => (string)$row['type'],
        'rr' => $row['rr'] !== null ? (float)$row['rr'] : null,
        'percent_made' => $row['percent_made'] !== null ? (float)$row['percent_made'] : null,
        'be' => $row['be'] !== null ? (float)$row['be'] : null,
        'loss' => $row['loss'] !== null ? (float)$row['loss'] : null,
        'win' => $row['win'] !== null ? (float)$row['win'] : null,
        'reentry_win' => $row['reentry_win'] !== null ? (float)$row['reentry_win'] : null,
        'reentry_loss' => $row['reentry_loss'] !== null ? (float)$row['reentry_loss'] : null,
        'reentry_be' => $row['reentry_be'] !== null ? (float)$row['reentry_be'] : null,
        'session' => array_values(array_filter(array_map('trim', explode(',', (string)($row['session'] ?? ''))))),
        'outsession' => array_values(array_filter(array_map('trim', explode(',', (string)($row['outsession'] ?? ''))))),
        'journal' => (string)($row['journal'] ?? ''),
        'theory' => (string)($row['theory'] ?? 'logical'),
        'ovr' => (string)($row['ovr'] ?? 'good'),
        'links' => (string)($row['links'] ?? ''),
    ];
}

function format_currency($num) {
    return '$' . number_format($num, 2, '.', ',');
}

function get_calendar_display_info($entry) {
    if (!$entry || !is_array($entry)) {
        return ['field' => '', 'display' => '', 'signed_value' => null];
    }

    foreach (['percent_made', 'win', 'loss', 'be', 'rr'] as $field) {
        if (isset($entry[$field]) && $entry[$field] !== null && $entry[$field] !== '') {
            $rawValue = (float)$entry[$field];
            $signedValue = $rawValue;

            if ($field === 'be' || ($entry['type'] ?? '') === 'be') {
                $signedValue = 0.0;
            } elseif (($entry['type'] ?? '') === 'loss') {
                $signedValue = -abs($rawValue);
            } elseif (($entry['type'] ?? '') === 'profit') {
                $signedValue = abs($rawValue);
            }

            return [
                'field' => $field,
                'display' => (string)$entry[$field],
                'signed_value' => $signedValue,
            ];
        }
    }

    if (($entry['type'] ?? '') === 'be') {
        return ['field' => 'be', 'display' => '0', 'signed_value' => 0.0];
    }

    return ['field' => '', 'display' => '', 'signed_value' => null];
}

function calendar_label_class($type) {
    if ($type === 'ongoing') return 'status-ongoing';
    if ($type === 'gone') return 'status-gone';
    return 'status-breakeven';
}

$monthly_totals = [];
for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++) {
    $monthKey = sprintf('%02d', $monthNumber);
    $monthly_totals[$monthKey] = 0.0;
}

foreach ($calendar as $dateKey => $entry) {
    $monthKey = substr((string)$dateKey, 5, 2);
    if (!isset($monthly_totals[$monthKey])) {
        continue;
    }

    $displayInfo = get_calendar_display_info($entry);
    if ($displayInfo['signed_value'] !== null) {
        $monthly_totals[$monthKey] += $displayInfo['signed_value'];
    }
}

$trade_outcomes = [];
ksort($calendar);
foreach ($calendar as $dateKey => $entry) {
    $displayInfo = get_calendar_display_info($entry);
    if ($displayInfo['signed_value'] === null) {
        continue;
    }
    if ($displayInfo['signed_value'] > 0) {
        $trade_outcomes[] = 'win';
    } elseif ($displayInfo['signed_value'] < 0) {
        $trade_outcomes[] = 'loss';
    }
}

$win_count = 0;
$loss_count = 0;
$win_streaks = [];
$loss_streaks = [];
$current_streak_type = '';
$current_streak_count = 0;

foreach ($trade_outcomes as $outcome) {
    if ($outcome === 'win') {
        $win_count++;
    } elseif ($outcome === 'loss') {
        $loss_count++;
    }

    if ($outcome === $current_streak_type) {
        $current_streak_count++;
        continue;
    }

    if ($current_streak_type === 'win' && $current_streak_count > 0) {
        $win_streaks[] = $current_streak_count;
    } elseif ($current_streak_type === 'loss' && $current_streak_count > 0) {
        $loss_streaks[] = $current_streak_count;
    }

    $current_streak_type = $outcome;
    $current_streak_count = 1;
}

if ($current_streak_type === 'win' && $current_streak_count > 0) {
    $win_streaks[] = $current_streak_count;
} elseif ($current_streak_type === 'loss' && $current_streak_count > 0) {
    $loss_streaks[] = $current_streak_count;
}

$total_trades = $win_count + $loss_count;
$winrate_metrics = [
    'wins' => $win_count,
    'losses' => $loss_count,
    'total_trades' => $total_trades,
    'win_percentage' => $total_trades > 0 ? round(($win_count / $total_trades) * 100, 2) : 0,
    'loss_percentage' => $total_trades > 0 ? round(($loss_count / $total_trades) * 100, 2) : 0,
    'avg_win_streak' => !empty($win_streaks) ? round(array_sum($win_streaks) / count($win_streaks), 2) : 0,
    'avg_loss_streak' => !empty($loss_streaks) ? round(array_sum($loss_streaks) / count($loss_streaks), 2) : 0,
];

$equity_daily_points = [];
$calendar_dates = array_keys($calendar);
sort($calendar_dates);

if (!empty($calendar_dates)) {
    $curve_start = new DateTime($calendar_dates[0]);
    $curve_end = new DateTime(max(date('Y-m-d'), end($calendar_dates)));
    $running_total = 0.0;
    $cursor = clone $curve_start;

    while ($cursor <= $curve_end) {
        $dateKey = $cursor->format('Y-m-d');
        if (isset($calendar[$dateKey])) {
            $displayInfo = get_calendar_display_info($calendar[$dateKey]);
            if ($displayInfo['signed_value'] !== null) {
                $running_total += $displayInfo['signed_value'];
            }
        }

        $equity_daily_points[] = [
            'date' => $dateKey,
            'value' => round($running_total, 2),
        ];
        $cursor->modify('+1 day');
    }
} else {
    $equity_daily_points[] = [
        'date' => date('Y-m-d'),
        'value' => 0.0,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Funding Activity - Rorhonco</title>
    <style>
        * {box-sizing: border-box;}
        body {font-family: Arial, sans-serif; background: #eaf0ff; color: #111827; margin: 0;}
        .app-shell {min-height: 100vh; display: flex;}
        .sidebar {width: 260px; background: linear-gradient(180deg, #0b1f52 0%, #12398a 100%); color: #fff; padding: 28px 20px; display: flex; flex-direction: column; gap: 24px; box-shadow: 18px 0 36px rgba(11, 31, 82, 0.18);}
        .brand-title {font-size: 1.35rem; font-weight: 800; margin: 0;}
        .sidebar-nav {display: flex; flex-direction: column; gap: 10px;}
        .sidebar-link {display: block; padding: 14px 16px; border-radius: 16px; color: rgba(255,255,255,0.86); text-decoration: none; font-weight: 700; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.05); transition: background-color 0.2s ease, transform 0.2s ease;}
        .sidebar-link:hover {background: rgba(255,255,255,0.12); transform: translateX(2px); text-decoration: none;}
        .sidebar-link.active {background: rgba(255,255,255,0.18); color: #fff; border-color: rgba(191, 219, 254, 0.4); box-shadow: inset 0 0 0 1px rgba(255,255,255,0.08);}
        .main-content {flex: 1; padding: 28px;}
        .container {max-width: 1200px; margin: 0 auto; background: #fff; border-radius: 24px; padding: 24px; box-shadow: 0 18px 40px rgba(15,23,42,0.10);}
        h1 {margin-bottom: 16px;}
        table {width: 100%; border-collapse: collapse; margin-bottom: 20px;}
        .table-scroll {width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch;}
        .funding-table {min-width: 680px;}
        th, td {padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb;}
        th {background: #f9fafb; font-weight: bold;}
        .form-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 18px;}
        .form-group {display:flex; flex-direction: column;}
        label {font-weight: bold; margin-bottom: 5px;}
        input, select {padding: 8px 10px; border:1px solid #cbd5e1; border-radius: 8px;}
        button {margin-top: 12px; padding: 10px 16px; background-color: #1d4ed8; color: white; border: none; border-radius: 8px; cursor: pointer;}
        button:hover {background-color: #1e40af;}
        a {color: #1d4ed8; text-decoration: none;}
        a:hover {text-decoration: underline;}
        .status-badge {padding: 4px 12px; border-radius: 999px; font-weight: bold; display: inline-block;}
        .status-ongoing {background: #d1fae5; color: #065f46;}
        .status-gone {background: #fee2e2; color: #991b1b;}
        .status-breakeven {background: #ffedd5; color: #92400e;}
        .tabs {display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;}
        .tab-button {margin-top: 0; background: #dbe7ff; color: #17337a; border-radius: 12px; padding: 10px 14px; font-weight: 700;}
        .tab-button:hover {background: #c4d7ff;}
        .tab-button.active {background: #1d4ed8; color: #fff;}
        .tab-content {display: none;}
        .tab-content.active {display: block;}
        .section-card {background: #f8fbff; border: 1px solid #dbe7ff; border-radius: 20px; padding: 20px;}
        .monthly-pnl-title {margin: 0 0 16px; color: #102a66;}
        .monthly-pnl-table {width: 100%; border-collapse: separate; border-spacing: 8px; table-layout: fixed; min-width: 860px;}
        .monthly-pnl-table th {background: #e8f0ff; color: #17337a; border: none; border-radius: 14px; text-align: center;}
        .monthly-pnl-table td {border: 2px solid #d1d5db; border-radius: 16px; padding: 16px 10px; text-align: center; font-weight: 800; font-size: 1rem; background: #fff;}
        .monthly-pnl-table td.month-profit {background: #dcfce7; border-color: #86efac; color: #14532d;}
        .monthly-pnl-table td.month-loss {background: #fee2e2; border-color: #fca5a5; color: #7f1d1d;}
        .monthly-pnl-table td.month-be {background: #ffedd5; border-color: #fdba74; color: #9a3412;}
        .monthly-pnl-month {display: block; font-size: 0.82rem; letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 6px; opacity: 0.82;}
        .monthly-pnl-value {display: block; font-size: 1.15rem;}
        .equity-header {display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 18px;}
        .equity-copy {margin: 6px 0 0; color: #4b5563; max-width: 720px; line-height: 1.5;}
        .equity-scale-badge {padding: 10px 14px; border-radius: 999px; background: #dbe7ff; color: #17337a; font-weight: 800; white-space: nowrap;}
        .equity-chart-shell {border: 1px solid #d7e3ff; border-radius: 20px; padding: 18px; background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);}
        .equity-chart-wrap {position: relative;}
        .equity-chart {width: 100%; height: 360px; display: block;}
        .equity-axis-label {font-size: 12px; fill: #4b5563; font-weight: 700;}
        .equity-grid-line {stroke: #dbe7ff; stroke-width: 1;}
        .equity-axis-line {stroke: #94a3b8; stroke-width: 1.2;}
        .equity-zero-line {stroke: #f59e0b; stroke-width: 1.2; stroke-dasharray: 6 6;}
        .equity-curve-line {fill: none; stroke: #1d4ed8; stroke-width: 3.5; stroke-linecap: round; stroke-linejoin: round;}
        .equity-point {fill: #1d4ed8;}
        .equity-summary {display: grid; grid-template-columns: repeat(3, minmax(180px, 1fr)); gap: 12px; margin-top: 18px;}
        .equity-stat {background: #f8fbff; border: 1px solid #d7e3ff; border-radius: 16px; padding: 14px 16px;}
        .equity-stat-label {display: block; font-size: 0.82rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px;}
        .equity-stat-value {display: block; font-size: 1.35rem; font-weight: 800; color: #102a66;}
        .performance-grid {display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(320px, 1fr); gap: 18px; margin-top: 18px;}
        .winrate-card {background: #f8fbff; border: 1px solid #d7e3ff; border-radius: 20px; padding: 20px;}
        .winrate-layout {display: grid; grid-template-columns: minmax(0, 1fr) 220px; gap: 20px; align-items: center;}
        .winrate-title {margin: 0 0 14px; color: #102a66;}
        .winrate-gauge {width: 100%; height: 220px; display: block;}
        .winrate-track {fill: none; stroke: #dbe7ff; stroke-width: 28; stroke-linecap: round;}
        .winrate-win {fill: none; stroke: #16a34a; stroke-width: 28; stroke-linecap: round;}
        .winrate-loss {fill: none; stroke: #dc2626; stroke-width: 28; stroke-linecap: round;}
        .winrate-center-label {font-size: 14px; fill: #64748b; font-weight: 700;}
        .winrate-center-value {font-size: 26px; fill: #102a66; font-weight: 800;}
        .winrate-breakdown {display: flex; justify-content: center; gap: 18px; margin-top: 10px; color: #334155; font-weight: 700; flex-wrap: wrap;}
        .winrate-dot {display: inline-block; width: 10px; height: 10px; border-radius: 999px; margin-right: 8px;}
        .win-dot {background: #16a34a;}
        .loss-dot {background: #dc2626;}
        .streak-panel {display: grid; gap: 12px;}
        .streak-stat {background: #ffffff; border: 1px solid #d7e3ff; border-radius: 16px; padding: 16px;}
        .streak-stat-label {display: block; font-size: 0.82rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px;}
        .streak-stat-value {display: block; font-size: 1.4rem; font-weight: 800; color: #102a66;}
        .winrate-footer {margin-top: 16px; font-weight: 800; color: #102a66; text-align: center;}
        .calendar-wrapper {margin-top: 32px;}
        .calendar-header {display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;}
        .calendar-grid {width: 100%; border-collapse: separate; border-spacing: 8px; min-width: 760px;}
        .calendar-grid th, .calendar-grid td {width: 14.285%; border: 2px solid #d1d5db; vertical-align: top; padding: 10px; height: 120px; border-radius: 16px; transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;}
        .calendar-grid td.day-outside {background: #f9fafb; color: #9ca3af;}
        .calendar-grid td.day-inside {background: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);}
        .calendar-grid td.day-profit {background: #dcfce7; border-color: #86efac; color: #14532d;}
        .calendar-grid td.day-loss {background: #fee2e2; border-color: #fca5a5; color: #7f1d1d;}
        .calendar-grid td.day-be {background: #ffedd5; border-color: #fdba74; color: #9a3412;}
        .calendar-grid td.day-profit a,
        .calendar-grid td.day-loss a,
        .calendar-grid td.day-be a {color: inherit; font-weight: 600;}
        .calendar-entry {font-size: 0.85rem; margin-top: 4px;}
        .calendar-entry strong {font-weight: 700;}
        .selected-date {margin-top: 16px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; background: #ffffff;}
        @media (max-width: 900px) {
            .app-shell {flex-direction: column;}
            .sidebar {width: 100%; box-shadow: none;}
            .sidebar-nav {display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));}
            .main-content {padding: 18px;}
            .equity-header {flex-direction: column;}
            .equity-summary {grid-template-columns: 1fr;}
            .performance-grid, .winrate-layout {grid-template-columns: 1fr;}
        }
        @media (max-width: 640px) {
            .container {padding: 16px; border-radius: 16px;}
            .tab-button {width: 100%;}
            .sidebar-nav {grid-template-columns: 1fr;}
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
                <a class="sidebar-link" href="index.php">Financial Dashboard</a>
                <a class="sidebar-link <?php echo $active_tab === 'funding-tab' ? 'active' : ''; ?>" href="funding.php?tab=funding-tab">Funding Activity</a>
                <a class="sidebar-link <?php echo $active_tab === 'calendar-tab' ? 'active' : ''; ?>" href="funding.php?tab=calendar-tab">Calendar</a>
                <a class="sidebar-link <?php echo $active_tab === 'monthly-pnl-tab' ? 'active' : ''; ?>" href="funding.php?tab=monthly-pnl-tab">Monthly PnL</a>
                <a class="sidebar-link <?php echo $active_tab === 'performance-metrics-tab' ? 'active' : ''; ?>" href="funding.php?tab=performance-metrics-tab">Performance Metrics</a>
                <a class="sidebar-link" href="weekly_journal.php">Weekly Journal</a>
            </nav>
        </aside>
        <main class="main-content">
            <div class="container">
                <h1>Funding Activity</h1>
                <div class="tabs">
                    <button type="button" class="tab-button <?php echo $active_tab === 'funding-tab' ? 'active' : ''; ?>" data-tab="funding-tab">Funding Table</button>
                    <button type="button" class="tab-button <?php echo $active_tab === 'calendar-tab' ? 'active' : ''; ?>" data-tab="calendar-tab">Calendar</button>
                    <button type="button" class="tab-button <?php echo $active_tab === 'monthly-pnl-tab' ? 'active' : ''; ?>" data-tab="monthly-pnl-tab">Monthly PnL</button>
                    <button type="button" class="tab-button <?php echo $active_tab === 'performance-metrics-tab' ? 'active' : ''; ?>" data-tab="performance-metrics-tab">Performance Metrics</button>
                </div>
                <div id="funding-tab" class="tab-content <?php echo $active_tab === 'funding-tab' ? 'active' : ''; ?>">
        <div class="table-scroll">
        <table class="funding-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Firm</th>
                    <th>Action</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($funding as $entry): ?>
                <tr>
                    <td><?php echo htmlspecialchars($entry['date']); ?></td>
                    <td><?php echo format_currency($entry['amount']); ?></td>
                    <td><?php echo htmlspecialchars($entry['firm']); ?></td>
                    <td><?php echo htmlspecialchars($entry['action']); ?></td>
                    <td>
                        <?php 
                        $status_class = $entry['status'] === 'ongoing' ? 'status-ongoing' : 'status-gone';
                        $status_text = ucfirst($entry['status']);
                        echo "<span class='status-badge $status_class'>$status_text</span>";
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <form method="post">
            <h2>Add New Entry</h2>
            <div class="form-grid">
                <div class="form-group"><label>Date</label><input name="date" type="date" value="<?php echo date('Y-m-d'); ?>" required></div>
                <div class="form-group"><label>Amount</label><input name="amount" type="number" step="0.01" min="0" required></div>
                <div class="form-group"><label>Firm</label><input name="firm" type="text" required></div>
                <div class="form-group"><label>Action</label><input name="action" type="text" required></div>
                <div class="form-group"><label>Status</label><select name="status" required><option value="ongoing">Ongoing</option><option value="gone">Gone</option></select></div>
            </div>
            <button type="submit">Save</button>
        </form>
    </div>

    <div id="calendar-tab" class="tab-content <?php echo $active_tab === 'calendar-tab' ? 'active' : ''; ?>">
        <div class="calendar-wrapper">
            <div class="calendar-header">
                <h2>Monthly Profit/Loss Calendar</h2>
                <div>
                    <?php
                    $prev_month = date('Y-m', strtotime($view_month . ' -1 month'));
                    $next_month = date('Y-m', strtotime($view_month . ' +1 month'));
                    ?>
                    <a href="?month=<?php echo $prev_month; ?>">&laquo; Prev</a>
                    |
                    <a href="?month=<?php echo $next_month; ?>">Next &raquo;</a>
                </div>
            </div>

            <?php
            $firstDay = new DateTime($view_month . '-01');
            $startDay = clone $firstDay;
            $startDay->modify('monday this week');
            $endDay = clone $firstDay;
            $endDay->modify('last day of this month');
            $endDay->modify('sunday this week');

            $current = clone $startDay;
            $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            ?>

            <div class="table-scroll">
            <table class="calendar-grid">
                <thead>
                    <tr>
                        <?php foreach ($weekdays as $dayName): ?>
                            <th><?php echo $dayName; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($current <= $endDay): ?>
                        <tr>
                            <?php for ($i = 0; $i < 7; $i++): ?>
                                <?php
                                $dateKey = $current->format('Y-m-d');
                                $isCurrentMonth = $current->format('Y-m') === $view_month;
                                $entry = $calendar[$dateKey] ?? null;
                                $cellClass = $isCurrentMonth ? 'day-inside' : 'day-outside';
                                ?>
                                <?php
                                $displayInfo = get_calendar_display_info($entry);
                                $displayValue = $displayInfo['display'];
                                $displayField = $displayInfo['field'];
                                $extraClass = '';
                                if ($entry) {
                                    if ($displayField === 'be') {
                                        $extraClass = 'day-be';
                                    } elseif (($entry['type'] ?? '') === 'profit') {
                                        $extraClass = 'day-profit';
                                    } elseif (($entry['type'] ?? '') === 'loss') {
                                        $extraClass = 'day-loss';
                                    } else {
                                        $extraClass = 'day-be';
                                    }
                                }
                                ?>
                                <td class="<?php echo $cellClass . ' ' . $extraClass; ?>">
                                    <div><strong><?php echo $current->format('j'); ?></strong></div>
                                    <?php if ($displayValue !== ''): ?>
                                        <div class="calendar-entry"><?php echo htmlspecialchars($displayValue); ?>%</div>
                                    <?php endif; ?>
                                    <?php if ($entry && isset($entry['note']) && $entry['note'] !== ''): ?>
                                        <div class="calendar-entry"><?php echo htmlspecialchars($entry['note']); ?></div>
                                    <?php endif; ?>
                                    <div class="calendar-entry"><a href="#" class="open-modal" data-date="<?php echo $dateKey; ?>">Edit</a></div>
                                </td>
                                <?php $current->modify('+1 day'); ?>
                            <?php endfor; ?>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>

            <div id="calendar-modal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h3>Calendar Entry</h3>
                    <form method="post" id="calendar-form">
                        <input type="hidden" name="calendar_entry" value="1">
                        <input type="hidden" name="calendar_month" value="<?php echo htmlspecialchars($view_month); ?>">
                        <div class="form-grid">
                            <div class="form-group"><label>Date</label><input type="date" name="calendar_date" id="calendar_date" required></div>
                            <div class="form-group"><label>Type</label><select name="calendar_type" id="calendar_type" required><option value="profit">Profit</option><option value="loss">Loss</option><option value="be">Break Even</option></select></div>
                            <div class="form-group"><label>RR</label><input type="number" step="0.01" name="rr" id="rr"></div>
                            <div class="form-group"><label>% Made</label><input type="number" step="0.01" name="percent_made" id="percent_made"></div>
                            <div class="form-group"><label>BE</label><input type="number" step="0.01" name="be" id="be"></div>
                            <div class="form-group"><label>Loss</label><input type="number" step="0.01" name="loss" id="loss"></div>
                            <div class="form-group"><label>Win</label><input type="number" step="0.01" name="win" id="win"></div>
                            <div class="form-group"><label>Reentry Win</label><input type="number" step="0.01" name="reentry_win" id="reentry_win"></div>
                            <div class="form-group"><label>Reentry Loss</label><input type="number" step="0.01" name="reentry_loss" id="reentry_loss"></div>
                            <div class="form-group"><label>Reentry BE</label><input type="number" step="0.01" name="reentry_be" id="reentry_be"></div>
                            <div class="form-group"><label>Session</label><input type="text" name="session" id="session" placeholder="tags separated by commas"></div>
                            <div class="form-group"><label>Outsession</label><input type="text" name="outsession" id="outsession" placeholder="tags separated by commas"></div>
                            <div class="form-group"><label>Journal</label><textarea name="journal" id="journal" rows="3"></textarea></div>
                            <div class="form-group"><label>Theory</label><select name="theory" id="theory"><option value="logical">Logical</option><option value="model">Model</option></select></div>
                            <div class="form-group"><label>OVR</label><select name="ovr" id="ovr"><option value="good">Good</option><option value="bad">Bad</option></select></div>
                            <div class="form-group"><label>Links</label><input type="text" name="links" id="links" placeholder="Screenshot URL"></div>
                        </div>
                        <button type="submit">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="monthly-pnl-tab" class="tab-content <?php echo $active_tab === 'monthly-pnl-tab' ? 'active' : ''; ?>">
        <div class="section-card">
            <h2 class="monthly-pnl-title">Monthly PnL</h2>
            <div class="table-scroll">
            <table class="monthly-pnl-table">
                <thead>
                    <tr>
                        <?php foreach (['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as $monthLabel): ?>
                            <th><?php echo $monthLabel; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++): ?>
                            <?php
                            $monthKey = sprintf('%02d', $monthNumber);
                            $monthTotal = $monthly_totals[$monthKey];
                            if ($monthTotal > 0) {
                                $monthClass = 'month-profit';
                            } elseif ($monthTotal < 0) {
                                $monthClass = 'month-loss';
                            } else {
                                $monthClass = 'month-be';
                            }
                            ?>
                            <td class="<?php echo $monthClass; ?>">
                                <span class="monthly-pnl-month"><?php echo date('M', mktime(0, 0, 0, $monthNumber, 1)); ?></span>
                                <span class="monthly-pnl-value"><?php echo number_format($monthTotal, 2); ?></span>
                            </td>
                        <?php endfor; ?>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div id="performance-metrics-tab" class="tab-content <?php echo $active_tab === 'performance-metrics-tab' ? 'active' : ''; ?>">
        <div class="section-card">
            <div class="equity-header">
                <div>
                    <h2 class="monthly-pnl-title">Equity Curve</h2>
                    <p class="equity-copy">This line graph tracks cumulative % from your calendar entries over time. No-trade days stay flat, and the x-axis automatically shifts from days to weeks, months, and years as more data comes in.</p>
                </div>
                <div class="equity-scale-badge" id="equity-scale-badge">Scale: Days</div>
            </div>
            <div class="equity-chart-shell">
                <div class="equity-chart-wrap">
                    <svg id="equity-chart" class="equity-chart" viewBox="0 0 980 360" preserveAspectRatio="none" aria-label="Equity curve chart"></svg>
                </div>
            </div>
            <div class="equity-summary">
                <div class="equity-stat">
                    <span class="equity-stat-label">Current Equity</span>
                    <span class="equity-stat-value" id="equity-current-value">0.00%</span>
                </div>
                <div class="equity-stat">
                    <span class="equity-stat-label">Highest Equity</span>
                    <span class="equity-stat-value" id="equity-high-value">0.00%</span>
                </div>
                <div class="equity-stat">
                    <span class="equity-stat-label">Tracked Period</span>
                    <span class="equity-stat-value" id="equity-period-value">1 Day</span>
                </div>
            </div>
            <div class="performance-grid">
                <div class="winrate-card">
                    <h2 class="winrate-title">Winrate</h2>
                    <div class="winrate-layout">
                        <div>
                            <svg id="winrate-gauge" class="winrate-gauge" viewBox="0 0 360 220" preserveAspectRatio="xMidYMid meet" aria-label="Winrate gauge"></svg>
                            <div class="winrate-breakdown">
                                <span><span class="winrate-dot win-dot"></span>Wins: <span id="winrate-win-percent">0.00%</span></span>
                                <span><span class="winrate-dot loss-dot"></span>Losses: <span id="winrate-loss-percent">0.00%</span></span>
                            </div>
                            <div class="winrate-footer">Winrate = <span id="winrate-footer-value">0.00%</span></div>
                        </div>
                        <div class="streak-panel">
                            <div class="streak-stat">
                                <span class="streak-stat-label">Avg Winning Streak</span>
                                <span class="streak-stat-value" id="avg-win-streak-value">0.00</span>
                            </div>
                            <div class="streak-stat">
                                <span class="streak-stat-label">Avg Losing Streak</span>
                                <span class="streak-stat-value" id="avg-loss-streak-value">0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
            </div>
        </main>
    </div>

    <script>
        let activeTab = <?php echo json_encode($active_tab); ?>;
        const tabs = document.querySelectorAll('.tab-button');
        const contents = document.querySelectorAll('.tab-content');
        const sidebarLinks = document.querySelectorAll('.sidebar-link[href*="tab="]');

        function setActiveTab(tabId) {
            activeTab = tabId;
            tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === tabId));
            contents.forEach(c => c.classList.toggle('active', c.id === tabId));
            sidebarLinks.forEach(link => {
                const isMatch = link.getAttribute('href').indexOf('tab=' + tabId) !== -1;
                link.classList.toggle('active', isMatch);
            });
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                setActiveTab(tab.dataset.tab);
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tab.dataset.tab);
                window.history.replaceState({}, '', url);
            });
        });

        setActiveTab(activeTab);

        const calendarData = <?php echo json_encode($calendar); ?>;
        const equityDailyData = <?php echo json_encode($equity_daily_points); ?>;
        const winrateMetrics = <?php echo json_encode($winrate_metrics); ?>;
        const modal = document.getElementById('calendar-modal');
        const closeBtn = modal ? modal.querySelector('.close') : null;

        function groupEquityData(points) {
            const totalDays = points.length;
            if (totalDays <= 4) {
                return {
                    scale: 'Days',
                    points: points.map(point => ({
                        label: point.date.slice(5),
                        date: point.date,
                        value: point.value
                    }))
                };
            }

            const weekBuckets = [];
            const weekMap = new Map();
            points.forEach(point => {
                const date = new Date(point.date + 'T00:00:00');
                const monday = new Date(date);
                const day = (date.getDay() + 6) % 7;
                monday.setDate(date.getDate() - day);
                const key = monday.toISOString().slice(0, 10);
                if (!weekMap.has(key)) {
                    weekMap.set(key, {label: 'Wk ' + (weekBuckets.length + 1), date: point.date, value: point.value});
                    weekBuckets.push(weekMap.get(key));
                } else {
                    const bucket = weekMap.get(key);
                    bucket.date = point.date;
                    bucket.value = point.value;
                }
            });
            if (weekBuckets.length <= 4) {
                return {scale: 'Weeks', points: weekBuckets};
            }

            const monthBuckets = [];
            const monthMap = new Map();
            points.forEach(point => {
                const date = new Date(point.date + 'T00:00:00');
                const key = point.date.slice(0, 7);
                if (!monthMap.has(key)) {
                    const label = date.toLocaleString('en-US', {month: 'short'});
                    monthMap.set(key, {label, date: point.date, value: point.value});
                    monthBuckets.push(monthMap.get(key));
                } else {
                    const bucket = monthMap.get(key);
                    bucket.date = point.date;
                    bucket.value = point.value;
                }
            });
            monthBuckets.forEach(bucket => {
                const bucketDate = new Date(bucket.date + 'T00:00:00');
                const monthIndex = bucketDate.getMonth();
                bucket.yearLabel = monthIndex === 0 ? String(bucketDate.getFullYear()) : '';
            });
            return {scale: 'Months', points: monthBuckets};
        }

        function renderEquityChart() {
            const chart = document.getElementById('equity-chart');
            const scaleBadge = document.getElementById('equity-scale-badge');
            const currentValue = document.getElementById('equity-current-value');
            const highValue = document.getElementById('equity-high-value');
            const periodValue = document.getElementById('equity-period-value');
            if (!chart || !scaleBadge || !currentValue || !highValue || !periodValue) {
                return;
            }

            const grouped = groupEquityData(equityDailyData);
            const chartPoints = grouped.points.length ? grouped.points : [{label: 'Today', date: '', value: 0}];
            const values = equityDailyData.map(point => point.value);
            const currentEquity = values.length ? values[values.length - 1] : 0;
            const highestEquity = values.length ? Math.max(...values) : 0;
            const lowestEquity = values.length ? Math.min(...values) : 0;
            const yMax = Math.max(300, Math.ceil(highestEquity / 50) * 50 || 300);
            const yMin = Math.min(0, Math.floor(lowestEquity / 50) * 50 || 0);
            const tickCount = 5;
            const width = 980;
            const height = 360;
            const padding = {top: 20, right: 24, bottom: 54, left: 70};
            const plotWidth = width - padding.left - padding.right;
            const plotHeight = height - padding.top - padding.bottom;
            const valueRange = Math.max(1, yMax - yMin);

            scaleBadge.textContent = 'Scale: ' + grouped.scale;
            currentValue.textContent = currentEquity.toFixed(2) + '%';
            highValue.textContent = highestEquity.toFixed(2) + '%';
            periodValue.textContent = equityDailyData.length + (equityDailyData.length === 1 ? ' Day' : ' Days');

            const xForIndex = index => {
                if (chartPoints.length === 1) {
                    return padding.left + (plotWidth / 2);
                }
                return padding.left + (index / (chartPoints.length - 1)) * plotWidth;
            };
            const yForValue = value => padding.top + ((yMax - value) / valueRange) * plotHeight;
            const zeroY = yForValue(0);

            let svg = '';
            for (let i = 0; i < tickCount; i++) {
                const tickValue = yMax - (i * (valueRange / (tickCount - 1)));
                const y = padding.top + (i / (tickCount - 1)) * plotHeight;
                svg += '<line class="equity-grid-line" x1="' + padding.left + '" y1="' + y + '" x2="' + (width - padding.right) + '" y2="' + y + '"></line>';
                svg += '<text class="equity-axis-label" x="' + (padding.left - 12) + '" y="' + (y + 4) + '" text-anchor="end">' + tickValue.toFixed(0) + '%</text>';
            }

            if (zeroY >= padding.top && zeroY <= padding.top + plotHeight) {
                svg += '<line class="equity-zero-line" x1="' + padding.left + '" y1="' + zeroY + '" x2="' + (width - padding.right) + '" y2="' + zeroY + '"></line>';
            }

            svg += '<line class="equity-axis-line" x1="' + padding.left + '" y1="' + padding.top + '" x2="' + padding.left + '" y2="' + (height - padding.bottom) + '"></line>';
            svg += '<line class="equity-axis-line" x1="' + padding.left + '" y1="' + (height - padding.bottom) + '" x2="' + (width - padding.right) + '" y2="' + (height - padding.bottom) + '"></line>';

            const polylinePoints = chartPoints.map((point, index) => xForIndex(index) + ',' + yForValue(point.value)).join(' ');
            svg += '<polyline class="equity-curve-line" points="' + polylinePoints + '"></polyline>';

            chartPoints.forEach((point, index) => {
                const x = xForIndex(index);
                const y = yForValue(point.value);
                svg += '<circle class="equity-point" cx="' + x + '" cy="' + y + '" r="4"></circle>';
                svg += '<text class="equity-axis-label" x="' + x + '" y="' + (height - padding.bottom + 24) + '" text-anchor="middle">' + point.label + '</text>';
                if (point.yearLabel) {
                    svg += '<text class="equity-axis-label" x="' + x + '" y="' + (height - padding.bottom + 40) + '" text-anchor="middle">' + point.yearLabel + '</text>';
                }
            });

            svg += '<text class="equity-axis-label" x="' + (padding.left - 48) + '" y="' + (padding.top - 2) + '">% </text>';
            svg += '<text class="equity-axis-label" x="' + (padding.left + (plotWidth / 2)) + '" y="' + (height - 10) + '" text-anchor="middle">Time (' + grouped.scale + ')</text>';

            chart.innerHTML = svg;
        }

        renderEquityChart();

        function polarToCartesian(cx, cy, radius, angle) {
            const radians = (angle - 90) * Math.PI / 180.0;
            return {
                x: cx + (radius * Math.cos(radians)),
                y: cy + (radius * Math.sin(radians))
            };
        }

        function describeArc(cx, cy, radius, startAngle, endAngle) {
            const start = polarToCartesian(cx, cy, radius, endAngle);
            const end = polarToCartesian(cx, cy, radius, startAngle);
            const largeArcFlag = endAngle - startAngle <= 180 ? '0' : '1';
            return ['M', start.x, start.y, 'A', radius, radius, 0, largeArcFlag, 0, end.x, end.y].join(' ');
        }

        function renderWinrateGauge() {
            const gauge = document.getElementById('winrate-gauge');
            if (!gauge) {
                return;
            }

            const winPercent = Number(winrateMetrics.win_percentage || 0);
            const lossPercent = Number(winrateMetrics.loss_percentage || 0);
            const winAngle = 180 * (winPercent / 100);
            const trackPath = describeArc(180, 180, 110, 180, 360);
            const winPath = winPercent > 0 ? describeArc(180, 180, 110, 180, 180 + winAngle) : '';
            const lossPath = lossPercent > 0 ? describeArc(180, 180, 110, 180 + winAngle, 360) : '';

            let svg = '';
            svg += '<path class="winrate-track" d="' + trackPath + '"></path>';
            if (winPath) {
                svg += '<path class="winrate-win" d="' + winPath + '"></path>';
            }
            if (lossPath) {
                svg += '<path class="winrate-loss" d="' + lossPath + '"></path>';
            }
            svg += '<text class="winrate-center-label" x="180" y="136" text-anchor="middle">Winrate</text>';
            svg += '<text class="winrate-center-value" x="180" y="168" text-anchor="middle">' + winPercent.toFixed(2) + '%</text>';
            svg += '<text class="winrate-center-label" x="180" y="192" text-anchor="middle">' + winrateMetrics.wins + 'W / ' + winrateMetrics.losses + 'L</text>';
            gauge.innerHTML = svg;

            document.getElementById('winrate-win-percent').textContent = winPercent.toFixed(2) + '%';
            document.getElementById('winrate-loss-percent').textContent = lossPercent.toFixed(2) + '%';
            document.getElementById('winrate-footer-value').textContent = winPercent.toFixed(2) + '%';
            document.getElementById('avg-win-streak-value').textContent = Number(winrateMetrics.avg_win_streak || 0).toFixed(2);
            document.getElementById('avg-loss-streak-value').textContent = Number(winrateMetrics.avg_loss_streak || 0).toFixed(2);
        }

        renderWinrateGauge();

        function openModal(date) {
            if (!modal) {
                return;
            }
            const entry = calendarData[date] || {type:'profit', rr:'', percent_made:'', be:'', loss:'', win:'', reentry_win:'', reentry_loss:'', reentry_be:'', session:'', outsession:'', journal:'', theory:'logical', ovr:'good', links:''};
            document.getElementById('calendar_date').value = date;
            document.getElementById('calendar_type').value = entry.type;
            document.getElementById('rr').value = entry.rr;
            document.getElementById('percent_made').value = entry.percent_made;
            document.getElementById('be').value = entry.be;
            document.getElementById('loss').value = entry.loss;
            document.getElementById('win').value = entry.win;
            document.getElementById('reentry_win').value = entry.reentry_win;
            document.getElementById('reentry_loss').value = entry.reentry_loss;
            document.getElementById('reentry_be').value = entry.reentry_be;
            document.getElementById('session').value = (entry.session || []).join(', ');
            document.getElementById('outsession').value = (entry.outsession || []).join(', ');
            document.getElementById('journal').value = entry.journal;
            document.getElementById('theory').value = entry.theory;
            document.getElementById('ovr').value = entry.ovr;
            document.getElementById('links').value = entry.links;
            modal.style.display = 'flex';
        }

        document.querySelectorAll('.open-modal').forEach(el => {
            el.addEventListener('click', e => {
                e.preventDefault();
                openModal(e.target.dataset.date);
            });
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        }

        if (modal) {
            window.addEventListener('click', e => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
