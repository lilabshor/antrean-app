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
            </div>
    </div>
</div>
</body>
</html>
