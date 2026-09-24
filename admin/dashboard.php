<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../helpers/functions.php";

start_secure_session();

if(empty($_SESSION["user_id"])) {
    header("location: login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];
$full_name = $_SESSION["full_name"];
$counter_number = (int)($_SESSION["counter_number"] ?? 1);
$today = date("y-m-d");

$filter_service_id = filter_input(INPUT_GET, "service_id", FILTER_VALIDATE_INT) ?: null;

try {
    $stmt_svc = $pdo -> query("SELECT * FROM service WHERE is_active = 1 ORDER BY code ASC");
    $services = $stmt_svc -> fetchAll();

    $stmt_curr = $pdo->prepare(
        "SELECT q.*, s.name AS service_name
        FROM queues q
        JOIN services s ON q.service_id = s.id
        WHERE q.appointment_date = ? AND  q.counter_called = ? AND q.status = CALLING
        ORDER BY q.called_at DESC LIMIT 1"
    );

    $stmt_curr->execute([$today, $counter_number]);
    $current_serving = $stmt_curr -> fetch();

    $sql_queues = "SELECT q. *, s.name AS service_name 
                    FROM queues q 
                    JOIN services s ON q.service_id = s.id
                    WHERE q.appointment_date = : today";
    if ($filter_service_id) {
        $sql_queues .= " AND q.service_id = :svc_id";
    }
    $sql_queues = "ORDER BY q.id ASC";

    $stmt_all = $pdo->prepare($sql_queues);
    $params = [":today" => $today];
    if($filter_service_id) {
        $params[":svc_id"] = $filter_service_id;
    }
    $stmt_all -> execute($params);
    $all_queues =  $stmt_all -> fetchAll();

    $stats = [
        "total" => count($all_queues),
        "waiting" => 0,
        "calling" => 0,
        "served" => 0,
        "skipped" => 0,
    ];

    foreach ($all_queues as $q) {
        $st = strttolower($q["status"]);
        if (isset($stats[$st])) $stats[$st]++;
    }
} catch (PDOException $e) {
    error_log("Dashboard error : " . $e->getMessage());
    die("terjadi kesalahan memuat dashboard. ");
}

$flash_msg = $_SESSION["flash_msg"] ?? null;
$flash_err = $_SESSION["flash_err"] ?? null;
unset($_SESSION["flash_msg"], $_SESSION["flash_err"]);
?>

<!DOCTYPE html>
<html>
<head>
    <title> Dashboard petugas loket <?= $counter_number ?></title>
    <style>
        /*            ini bagian front end tolong di isi ya wahai assistent ai*/
    </style>
</head>
<body>

<div class="navbar">
    <div class="brand">Loket <?= $counter_number ?> - Panel operator</div>
    <div class="user-info">
        <span>Petugas : <strong><?= e($full_name) ?></strong></span>
        <a href="../display.php" target="_blank" style="color:#38bdf8; text-decoration:none;">Buka Display</a>
        <a href="logout.php" class="btn-logout">logout</a>
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
                <div class="call-title">Antrean Aktif saat ini di loket <?= $counter_number ?></div>

                <?php if ($current_serving): ?>
                    <div class="current-code"><?= e($current_serving["queue_code"]) ?></div>
                    <div class="current-details">
                        <?= e($current_serving["customer_name"]) ?> &bull;
                        <?= e($current_serving["service_name"]) ?> (<?= e($current_serving["counter_name"]) ?>)
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
                            <input type="hidden" name="queue_id" value="<?= (int)$current_serving["id"]?> ">
                            <button type="submit" class="btn-act btn-skip">Lewati</button>
                        </form>
                    </div>
                    <?php else : ?>
                        <div class="current-code" style="color : #64748b;">---</div>
                        <div class="current-details">Tidak ada antrean yang sedang aktif di loket ini</div>

                    <form method="POST" action="action_queue.php">
                        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                        <input type="hidden" name="action" value="call_next">
                        <?php if ($filter_service_id): ?>
                            <input type="hidden" name="service_id" value="<?= (int)$filter_service_id ?>">
                        <?php endif; ?>
                        <button type="submit" class="btn-act btn-next" style="font-size : 1.1rem; padding: 1rem 2rem;">
                            Panggil antrean berikutnya
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        <div class="stat-card">
            <div style="font-weight: bold; color: #cbd5e1;">Statistik hari ini (<?= format_indo_date($today) ?>)</div>
            <div class="stat-grid">
                <div class="stat-box">
                    <div class="stat-val" style="color: #38bdf8;" ><?= $stats["total"] ?></div>
                    <div class="stat-lbl">total antrean</div>
                </div>

                <div class="stat-box">
                    <div class="stat-val" style="color : #fbbf24;"> <?= $stats["waiting"] ?></div>
                    <div class="stat-lbl"> menunggu</div>
                </div>

                <div class="stat-box">
                    <div class="stat-val" style="color : #34d399;"><?= $stats["served"] ?></div>
                    <div class="stat-lbl">Selesai</div>
                </div>

                <div class="stat-box">
                    <div class="stat-val" style="color : #f87171;"><?= $stats["skipped"] ?></div>
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
            <Th>Nama pengunjung</Th>
            <th>Layanan</th>
            <th>slot waktu</th>
            <th>status</th>
            <th>LOket</th>
        </tr>
        </thead>
        <tbody>
            <?php if (empty($all_queues)) : ?>
            <tr>
                <td colspan="6" style="text-align : center; color: #64748b; padding: 2rem;">belum ada dta antrean hari ini</td>
            </tr>
            <?php else: ?>
                <?php foreach ($all_queues as $row): ?>
                    <tr>
                        <td style="font-weight : bold; color: #38bdf8;"><?= e($row["queue_code"]) ?></td>
                        <td><?= e($row["customer_name"]) ?></td>
                        <td><?= e($row["service_name"]) ?></td>
                        <td><?= e($row["time_slot"]) ?></td>
                        <td>
                            <span class="badge badge- <?= strtolower($row["status"]) ?>">
                                <?= e($row["status"]) ?>
                            </span>
                        </td>
                        <td><?= $row["counter_called"] ? "loket" . (int)$row["counter_called"] : "-" ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
