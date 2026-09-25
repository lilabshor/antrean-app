<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../helpers/functions.php";

start_secure_session();

if (empty($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id        = (int)$_SESSION["user_id"];
$full_name      = $_SESSION["full_name"] ?? "Petugas";
$counter_number = (int)($_SESSION["counter_number"] ?? 1);
$today          = date("Y-m-d");

$filter_service_id = filter_input(INPUT_GET, "service_id", FILTER_VALIDATE_INT) ?: null;

try {
    $stmt_svc = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY code ASC");
    $services = $stmt_svc->fetchAll();

    $stmt_curr = $pdo->prepare(
        "SELECT q.*, s.name AS service_name
        FROM queues q
        JOIN services s ON q.service_id = s.id
        WHERE q.appointment_date = ? AND q.counter_called = ? AND q.status = 'CALLING'
        ORDER BY q.called_at DESC LIMIT 1"
    );

    $stmt_curr->execute([$today, $counter_number]);
    $current_serving = $stmt_curr->fetch();

    $sql_queues = "SELECT q.*, s.name AS service_name
                    FROM queues q
                    JOIN services s ON q.service_id = s.id
                    WHERE q.appointment_date = :today";

    if ($filter_service_id) {
        $sql_queues .= " AND q.service_id = :svc_id";
    }
    $sql_queues .= " ORDER BY q.id ASC";

    $stmt_all = $pdo->prepare($sql_queues);
    $params   = [":today" => $today];
    if ($filter_service_id) {
        $params[":svc_id"] = $filter_service_id;
    }
    $stmt_all->execute($params);
    $all_queues = $stmt_all->fetchAll();

    $stats = [
        "total"   => count($all_queues),
        "waiting" => 0,
        "calling" => 0,
        "served"  => 0,
        "skipped" => 0,
    ];

    foreach ($all_queues as $q) {
        $st = strtolower($q["status"]);
        if (isset($stats[$st])) $stats[$st]++;
    }

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    die("Terjadi kesalahan saat memuat dashboard.");
}

$flash_msg = $_SESSION["flash_msg"] ?? null;
$flash_err = $_SESSION["flash_err"] ?? null;
unset($_SESSION["flash_msg"], $_SESSION["flash_err"]);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Petugas Loket <?= $counter_number ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            min-height: 100vh;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 1.5rem;
            background: #1e293b;
            border-bottom: 2px solid #2563eb;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .brand {
            font-size: 1rem;
            font-weight: 700;
            color: #f8fafc;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
        }

        .user-info span { color: #94a3b8; }
        .user-info strong { color: #f1f5f9; }

        .user-info a {
            color: #38bdf8;
            text-decoration: none;
        }

        .btn-logout {
            background: #dc2626;
            color: #fff !important;
            padding: 0.3rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
        }

        .container {
            padding: 1.2rem 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .alert {
            padding: 0.7rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .alert-success {
            background: #052e16;
            border: 1px solid #16a34a;
            color: #4ade80;
        }

        .alert-danger {
            background: #450a0a;
            border: 1px solid #dc2626;
            color: #f87171;
        }

        .grid-top {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.2rem;
        }

        @media (max-width: 640px) { .grid-top { grid-template-columns: 1fr; } }

        .call-card, .stat-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 1.2rem;
        }

        .call-title {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            margin-bottom: 0.75rem;
        }

        .current-code {
            font-size: 2.4rem;
            font-weight: 800;
            color: #38bdf8;
            letter-spacing: 3px;
            margin-bottom: 0.3rem;
        }

        .current-details {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 1rem;
        }

        .action-btns {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-act {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn-act:hover { opacity: 0.85; }

        .btn-recall  { background: #1d4ed8; color: #fff; }
        .btn-done    { background: #15803d; color: #fff; }
        .btn-skip    { background: #b45309; color: #fff; }
        .btn-next    { background: #2563eb; color: #fff; }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-top: 0.75rem;
        }

        .stat-box {
            background: #0f172a;
            border-radius: 8px;
            padding: 0.75rem;
            text-align: center;
        }

        .stat-val {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .stat-lbl {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.2rem;
        }

        .table-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.9rem 1.2rem;
            border-bottom: 1px solid #334155;
        }

        .table-header h3 {
            font-size: 0.95rem;
            color: #f1f5f9;
        }

        .table-header select {
            background: #0f172a;
            color: #f8fafc;
            border: 1px solid #334155;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            font-size: 0.82rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        thead tr { background: #0f172a; }

        th {
            padding: 0.65rem 1rem;
            text-align: left;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
        }

        td {
            padding: 0.65rem 1rem;
            border-top: 1px solid #1e293b;
            color: #cbd5e1;
        }

        tbody tr:hover { background: #172033; }

        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-waiting  { background: #422006; color: #fbbf24; }
        .badge-calling  { background: #1e3a5f; color: #38bdf8; }
        .badge-served   { background: #052e16; color: #4ade80; }
        .badge-skipped  { background: #450a0a; color: #f87171; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="brand">Loket <?= $counter_number ?> - Panel Operator</div>
    <div class="user-info">
        <span>Petugas: <strong><?= e($full_name) ?></strong></span>
        <a href="../display.php" target="_blank" style="color:#38bdf8; text-decoration:none;">Buka Display</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</div>

<div class="container">

    <?php if ($flash_msg): ?>
        <div class="alert alert-success"><?= e($flash_msg) ?></div>
    <?php endif; ?>
    <?php if ($flash_err): ?>
        <div class="alert alert-danger"><?= e($flash_err) ?></div>
    <?php endif; ?>

    <div class="grid-top">
        <div class="call-card">
            <div class="call-title">Antrean Aktif Saat Ini di Loket <?= $counter_number ?></div>

            <?php if ($current_serving): ?>
                <div class="current-code"><?= e($current_serving["queue_code"]) ?></div>
                <div class="current-details">
                    <?= e($current_serving["customer_name"]) ?> &bull;
                    <?= e($current_serving["service_name"]) ?>
                </div>
                <div class="action-btns">
                    <form method="POST" action="action_queue.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                        <input type="hidden" name="action" value="recall">
                        <input type="hidden" name="queue_id" value="<?= (int)$current_serving["id"] ?>">
                        <button type="submit" class="btn-act btn-recall">Panggil Ulang</button>
                    </form>

                    <form method="POST" action="action_queue.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                        <input type="hidden" name="action" value="served">
                        <input type="hidden" name="queue_id" value="<?= (int)$current_serving["id"] ?>">
                        <button type="submit" class="btn-act btn-done">Selesai Dilayani</button>
                    </form>

                    <form method="POST" action="action_queue.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                        <input type="hidden" name="action" value="skip">
                        <input type="hidden" name="queue_id" value="<?= (int)$current_serving["id"] ?>">
                        <button type="submit" class="btn-act btn-skip">Lewati</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="current-code" style="color:#64748b;">---</div>
                <div class="current-details">Tidak ada antrean yang sedang aktif di loket ini</div>

                <form method="POST" action="action_queue.php">
                    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                    <input type="hidden" name="action" value="call_next">
                    <?php if ($filter_service_id): ?>
                        <input type="hidden" name="service_id" value="<?= (int)$filter_service_id ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn-act btn-next" style="font-size:1.1rem; padding:1rem 2rem;">
                        Panggil Antrean Berikutnya
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="stat-card">
            <div style="font-weight:bold; color:#cbd5e1;">Statistik Hari Ini (<?= e(format_indo_date($today)) ?>)</div>
            <div class="stat-grid">
                <div class="stat-box">
                    <div class="stat-val" style="color:#38bdf8;"><?= $stats["total"] ?></div>
                    <div class="stat-lbl">Total Antrean</div>
                </div>
                <div class="stat-box">
                    <div class="stat-val" style="color:#fbbf24;"><?= $stats["waiting"] ?></div>
                    <div class="stat-lbl">Menunggu</div>
                </div>
                <div class="stat-box">
                    <div class="stat-val" style="color:#34d399;"><?= $stats["served"] ?></div>
                    <div class="stat-lbl">Selesai</div>
                </div>
                <div class="stat-box">
                    <div class="stat-val" style="color:#f87171;"><?= $stats["skipped"] ?></div>
                    <div class="stat-lbl">Dilewatkan</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Daftar Antrean -->
    <div class="table-card">
        <div class="table-header">
            <h3>Daftar Antrean Hari Ini</h3>
            <form method="GET" action="dashboard.php">
                <select name="service_id" onchange="this.form.submit()" style="background:#0f172a; color:#f8fafc; border:1px solid #334155;">
                    <option value="">Semua Layanan</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= (int)$s["id"] ?>" <?= $filter_service_id === (int)$s["id"] ? "selected" : "" ?>>
                            <?= e($s["name"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th>No. Antrean</th>
            <th>Nama Pengunjung</th>
            <th>Layanan</th>
            <th>Slot Waktu</th>
            <th>Status</th>
            <th>Loket</th>
        </tr>
        </thead>
        <tbody>
            <?php if (empty($all_queues)): ?>
            <tr>
                <td colspan="6" style="text-align:center; color:#64748b; padding:2rem;">Belum ada data antrean hari ini</td>
            </tr>
            <?php else: ?>
                <?php foreach ($all_queues as $row): ?>
                    <tr>
                        <td style="font-weight:bold; color:#38bdf8;"><?= e($row["queue_code"]) ?></td>
                        <td><?= e($row["customer_name"]) ?></td>
                        <td><?= e($row["service_name"]) ?></td>
                        <td><?= e($row["time_slot"]) ?></td>
                        <td>
                            <span class="badge badge-<?= strtolower($row["status"]) ?>">
                                <?= e($row["status"]) ?>
                            </span>
                        </td>
                        <td><?= $row["counter_called"] ? "Loket " . (int)$row["counter_called"] : "-" ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
