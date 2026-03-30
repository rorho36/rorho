<?php
$journal_path = __DIR__ . '/weekly_journal_data.json';
$default_journal = [];

if (!file_exists($journal_path)) {
    file_put_contents($journal_path, json_encode($default_journal, JSON_PRETTY_PRINT));
}

$journal_entries = json_decode(file_get_contents($journal_path), true);
if (!is_array($journal_entries)) {
    $journal_entries = $default_journal;
}

function normalize_week_start($value) {
    $raw = trim((string)$value);
    if ($raw === '') {
        return date('Y-m-d', strtotime('monday this week'));
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return date('Y-m-d', strtotime('monday this week'));
    }

    return date('Y-m-d', strtotime('monday this week', $timestamp));
}

function week_range_label($week_start) {
    $start_ts = strtotime($week_start);
    $end_ts = strtotime($week_start . ' +6 days');
    if ($start_ts === false || $end_ts === false) {
        return '';
    }
    return date('M j', $start_ts) . ' - ' . date('M j', $end_ts);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect_year = (int)($_POST['year'] ?? date('Y'));
    if ($redirect_year < 2000 || $redirect_year > 2100) {
        $redirect_year = (int)date('Y');
    }

    if (isset($_POST['delete_entry'])) {
        $delete_week = normalize_week_start($_POST['week_start'] ?? '');
        if (isset($journal_entries[$delete_week])) {
            unset($journal_entries[$delete_week]);
            file_put_contents($journal_path, json_encode($journal_entries, JSON_PRETTY_PRINT));
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?year=' . urlencode((string)$redirect_year));
        exit;
    }

    $week_start = normalize_week_start($_POST['week_start'] ?? '');
    $journal_entries[$week_start] = [
        'weekly_lessons' => trim($_POST['weekly_lessons'] ?? ''),
        'overall_trading' => trim($_POST['overall_trading'] ?? ''),
        'updated_at' => date('c'),
    ];

    file_put_contents($journal_path, json_encode($journal_entries, JSON_PRETTY_PRINT));
    header('Location: ' . $_SERVER['PHP_SELF'] . '?year=' . urlencode((string)$redirect_year) . '&week=' . urlencode($week_start));
    exit;
}

$view_year = (int)($_GET['year'] ?? date('Y'));
if ($view_year < 2000 || $view_year > 2100) {
    $view_year = (int)date('Y');
}

$selected_week = normalize_week_start($_GET['week'] ?? date('Y-m-d'));
$selected_entry = $journal_entries[$selected_week] ?? ['weekly_lessons' => '', 'overall_trading' => ''];

$prev_year = $view_year - 1;
$next_year = $view_year + 1;

krsort($journal_entries);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Journal - Rorhonco</title>
    <style>
        * {box-sizing: border-box;}
        body {font-family: Arial, sans-serif; background: #eaf0ff; color: #111827; margin: 0;}
        .app-shell {min-height: 100vh; display: flex;}
        .sidebar {width: 260px; background: linear-gradient(180deg, #0b1f52 0%, #12398a 100%); color: #fff; padding: 28px 20px; display: flex; flex-direction: column; gap: 24px; box-shadow: 18px 0 36px rgba(11, 31, 82, 0.18);}
        .brand-title {font-size: 1.35rem; font-weight: 800; margin: 0;}
        .sidebar-nav {display: flex; flex-direction: column; gap: 10px;}
        .sidebar-link {display: block; padding: 14px 16px; border-radius: 16px; color: rgba(255,255,255,0.86); text-decoration: none; font-weight: 700; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.05); transition: background-color 0.2s ease, transform 0.2s ease;}
        .sidebar-link:hover {background: rgba(255,255,255,0.12); transform: translateX(2px);}
        .sidebar-link.active {background: rgba(255,255,255,0.18); color: #fff; border-color: rgba(191, 219, 254, 0.4); box-shadow: inset 0 0 0 1px rgba(255,255,255,0.08);}
        .main-content {flex: 1; padding: 28px;}
        .container {max-width: 1280px; margin: 0 auto; display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(320px, 1fr); gap: 18px;}
        .card {background: #fff; border-radius: 24px; padding: 24px; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);}
        .card h1, .card h2 {margin-top: 0;}
        .muted {color: #64748b; margin-top: 6px;}
        .calendar-header {display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px;}
        .year-nav {display: flex; align-items: center; gap: 10px;}
        .year-chip {display: inline-flex; padding: 8px 12px; border-radius: 999px; background: #dbe7ff; color: #17337a; font-weight: 800;}
        .year-link {display: inline-flex; padding: 8px 12px; border-radius: 999px; background: #eff6ff; color: #1d4ed8; text-decoration: none; font-weight: 700; border: 1px solid #bfdbfe;}
        .year-link:hover {background: #dbeafe;}
        .week-calendar-grid {display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px;}
        .month-card {border: 1px solid #dbe7ff; border-radius: 16px; overflow: hidden; background: #f8fbff;}
        .month-title {margin: 0; padding: 10px 12px; background: #e8f0ff; color: #17337a; font-size: 0.95rem;}
        .month-weeks {padding: 8px; display: grid; gap: 8px;}
        .week-cell {
            display: block;
            border: 1px solid #dbe7ff;
            border-radius: 10px;
            background: #fff;
            padding: 8px;
            color: #1f2937;
            text-decoration: none;
            line-height: 1.25;
            min-height: 72px;
        }
        .week-cell:hover {border-color: #93c5fd; box-shadow: 0 0 0 2px rgba(147, 197, 253, 0.25);}
        .week-cell.active {border-color: #1d4ed8; box-shadow: 0 0 0 2px rgba(29, 78, 216, 0.22);}
        .week-cell.has-entry {background: #dcfce7; border-color: #86efac;}
        .week-index {display: block; font-size: 0.73rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 700;}
        .week-range {display: block; margin-top: 5px; font-size: 0.86rem; font-weight: 700;}
        .week-status {display: inline-block; margin-top: 6px; font-size: 0.74rem; font-weight: 800; color: #166534; background: rgba(22, 101, 52, 0.1); border-radius: 999px; padding: 3px 8px;}
        .form-grid {display: grid; gap: 12px; margin-top: 16px;}
        .form-group {display: flex; flex-direction: column; gap: 6px;}
        label {font-weight: 700; color: #334155;}
        input, textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.95rem;
        }
        textarea {min-height: 130px; resize: vertical;}
        .button-row {display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap;}
        button {
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 700;
            cursor: pointer;
        }
        .primary {background: #1d4ed8; color: #fff;}
        .primary:hover {background: #1e40af;}
        .danger {background: #fee2e2; color: #991b1b;}
        .danger:hover {background: #fecaca;}
        .history-list {display: grid; gap: 12px; margin-top: 14px;}
        .history-item {border: 1px solid #dbe7ff; border-radius: 14px; padding: 12px; background: #f8fbff;}
        .history-item h3 {margin: 0; font-size: 1rem; color: #102a66;}
        .history-preview {margin: 8px 0 10px; color: #334155; font-size: 0.92rem; line-height: 1.4;}
        .history-link {display: inline-block; color: #1d4ed8; font-weight: 700; text-decoration: none;}
        .history-link:hover {text-decoration: underline;}
        .empty {margin-top: 12px; padding: 12px; border-radius: 12px; background: #f8fafc; color: #475569; border: 1px dashed #cbd5e1;}
        @media (max-width: 980px) {
            .app-shell {flex-direction: column;}
            .sidebar {width: 100%; box-shadow: none;}
            .main-content {padding: 18px;}
            .container {grid-template-columns: 1fr;}
            .week-calendar-grid {grid-template-columns: repeat(2, minmax(0, 1fr));}
        }
        @media (max-width: 640px) {
            .week-calendar-grid {grid-template-columns: 1fr;}
            .calendar-header {flex-direction: column; align-items: flex-start;}
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
                <a class="sidebar-link" href="funding.php?tab=funding-tab">Funding Activity</a>
                <a class="sidebar-link" href="funding.php?tab=calendar-tab">Calendar</a>
                <a class="sidebar-link" href="funding.php?tab=monthly-pnl-tab">Monthly PnL</a>
                <a class="sidebar-link" href="funding.php?tab=performance-metrics-tab">Performance Metrics</a>
                <a class="sidebar-link active" href="weekly_journal.php">Weekly Journal</a>
            </nav>
        </aside>
        <main class="main-content">
            <div class="container">
                <section class="card">
                    <h1>Weekly Journal</h1>
                    <p class="muted">Track your weekly lessons and overall trading with a calendar organized by weeks.</p>
                    <div class="calendar-header">
                        <div class="year-nav">
                            <a class="year-link" href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?year=<?php echo $prev_year; ?>">&laquo; <?php echo $prev_year; ?></a>
                            <span class="year-chip"><?php echo $view_year; ?></span>
                            <a class="year-link" href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?year=<?php echo $next_year; ?>"><?php echo $next_year; ?> &raquo;</a>
                        </div>
                    </div>
                    <div class="week-calendar-grid">
                        <?php for ($month = 1; $month <= 12; $month++): ?>
                            <?php
                            $month_start = sprintf('%04d-%02d-01', $view_year, $month);
                            $month_start_ts = strtotime($month_start);
                            $month_name = date('F', $month_start_ts);
                            $first_week_monday = date('Y-m-d', strtotime('monday this week', $month_start_ts));
                            ?>
                            <article class="month-card">
                                <h3 class="month-title"><?php echo htmlspecialchars($month_name); ?></h3>
                                <div class="month-weeks">
                                    <?php for ($slot = 0; $slot < 6; $slot++): ?>
                                        <?php
                                        $week_start = date('Y-m-d', strtotime($first_week_monday . ' +' . $slot . ' week'));
                                        $cell_classes = 'week-cell';
                                        if ($week_start === $selected_week) {
                                            $cell_classes .= ' active';
                                        }
                                        $has_entry = isset($journal_entries[$week_start]);
                                        if ($has_entry) {
                                            $cell_classes .= ' has-entry';
                                        }
                                        ?>
                                        <a class="<?php echo $cell_classes; ?>" href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?year=<?php echo $view_year; ?>&week=<?php echo urlencode($week_start); ?>">
                                            <span class="week-index">Week <?php echo $slot + 1; ?></span>
                                            <span class="week-range"><?php echo htmlspecialchars(week_range_label($week_start)); ?></span>
                                            <?php if ($has_entry): ?>
                                                <span class="week-status">Saved</span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endfor; ?>
                                </div>
                            </article>
                        <?php endfor; ?>
                    </div>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="year" value="<?php echo $view_year; ?>">
                        <div class="form-group">
                            <label for="week_start">Week Start (Monday)</label>
                            <input id="week_start" name="week_start" type="date" required value="<?php echo htmlspecialchars($selected_week); ?>">
                        </div>
                        <div class="form-group">
                            <label for="weekly_lessons">Weekly Lessons</label>
                            <textarea id="weekly_lessons" name="weekly_lessons" placeholder="What did you learn this week?"><?php echo htmlspecialchars($selected_entry['weekly_lessons'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="overall_trading">Overall Weekly Trading</label>
                            <textarea id="overall_trading" name="overall_trading" placeholder="How was your trading overall?"><?php echo htmlspecialchars($selected_entry['overall_trading'] ?? ''); ?></textarea>
                        </div>
                        <div class="button-row">
                            <button class="primary" type="submit">Save Weekly Journal</button>
                        </div>
                    </form>
                </section>

                <aside class="card">
                    <h2>Saved Weeks</h2>
                    <?php if (empty($journal_entries)): ?>
                        <div class="empty">No weekly entries yet. Save your first week to build your journal history.</div>
                    <?php else: ?>
                        <div class="history-list">
                            <?php foreach ($journal_entries as $weekKey => $entry): ?>
                                <div class="history-item">
                                    <h3>Week of <?php echo htmlspecialchars($weekKey); ?></h3>
                                    <?php
                                    $preview = trim((string)($entry['overall_trading'] ?? ''));
                                    if (strlen($preview) > 120) {
                                        $preview = substr($preview, 0, 120) . '...';
                                    }
                                    ?>
                                    <div class="history-preview"><?php echo nl2br(htmlspecialchars($preview)); ?></div>
                                    <a class="history-link" href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?year=<?php echo $view_year; ?>&week=<?php echo urlencode($weekKey); ?>">Edit week</a>
                                    <form method="post" style="margin-top: 10px;">
                                        <input type="hidden" name="year" value="<?php echo $view_year; ?>">
                                        <input type="hidden" name="week_start" value="<?php echo htmlspecialchars($weekKey); ?>">
                                        <button class="danger" type="submit" name="delete_entry" value="1">Delete</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        </main>
    </div>
</body>
</html>
