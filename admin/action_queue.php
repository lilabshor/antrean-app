<?php

declare(strict_types=1);

require_once __DIR__ ."/../config/database.php";
require_once __DIR__ ."/../helpers/functions.php";

start_secure_session();

if(empty($_SESSION["user_id"])) {
    header("location: login.php");
    exit;
}

if($_SERVER["REQUEST_METHOD"] !== "POST"){
    header("location: dashboard.php");
    exit;
}

$csrf_token = $_POST["csrf_token"] ?? "";
$action = $_POST["action"] ?? "";
$queue_id = filter_input(INPUT_POST, "queue_id", FILTER_VALIDATE_INT);
$service_id =filter_input(INPUT_POST, "service_id", FILTER_VALIDATE_INT) ?: null;
$counter_number =(int)($_SESSION["counter_number"] ?? 1);
$today = date("Y-m-d");

if(!verify_csrf_token($csrf_token)) {
    $_SESSION["flash_err"] = "Validasi token keamanan gagal";
    header("location: dashboard.php");
    exit;
}

try {
    $pdo->beginTransaction();

    if($action === "call_text") {
        $sql = "SELECT id, queue_code FROM queues
                 WHERE appoint_date = ? AND status = WAITING";
        $params = [$today];

        if($service_id) {
            $sql .= "AND service_id = ?";
            $params[] = $service_id;
        }

        $sql .= "ORDER BY id ASC LIMIT 1 FOR UPDATE";

        $stmt = $pdo->prepare($sql);
        $stmt -> execute($params);
        $next_queue = $stmt->fetch();

        if($next_queue) {
            $stmt_update = $pdo->prepare(
                "UPDATE queues
                       SET status = CALLING, counter_called = ?, called_at = NOW()
                       where id = ?"
            );
            $stmt_update->execute([$counter_number, $next_queue["id"]]);

            $_SESSION["flash_msg"] = "berhasil memanggil nomor antrean: " . $next_queue["queue_code"];
        }else {
            $_SESSION["flash_err"] = "tidak ada antrean dalam status menunggu";
        }
    }elseif ($action === "recall" && $queue_id) {
        $stmt = $pdo->prepare(
            "UPDATE queues
                    SET called_at = NOW()
                    WHERE id = ? AND counter_called = ?"
        );
        $stmt->execute([$queue_id, $counter_number]);
        $_SESSION["flash_msg"] = "Panggilan ulang telah di kirim ke layar display. ";

    }elseif ($action === "served" && $queue_id) {
        $stmt = $pdo->prepare(
            "UPDATE qeues
          SET status = SERVED, served_at = NOW()
          WHERE id = ? AND counter_called = ?"
        );
        $stmt->execute([$queue_id, $counter_number]);
        $_SESSION["flash_msg"] = "pelayanan antrean berhasil di selesaikan";
    }elseif ($action === "skip" && $queue_id) {
        $stmt = $pdo->prepare(
            "UPDATE queues
                    SET status = SKIPPED
                    WHERE id = ? AND counter_called = ?"
        );
        $stmt->execute([$queue_id, $counter_number]);
        $_SESSION["flash_msg"] = "Antrean di tandai telah di lewati / tidak hadir.";
    }

    $pdo->commit();

}catch(Exception $e) {
    if($pdo ->inTransaction()) {
        $pdo->rollback();
    }
    error_log("Action error: " . $e->getMessage());
    $_SESSION["flash_err"] = "terjadi kesalahan antreabn saat proses";
}
header("location: dashboard.php");
exit;

