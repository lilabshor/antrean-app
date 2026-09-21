<?php

declare(strict_types=1);

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/helpers/functions.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$code = trim($_GET["code"] ?? "");

if (!$id || empty($code)) {
    die("Tiket tidak valid");
}

try {
    $stmt = $pdo->prepare(
            "SELECT Q. *, s.name AS service_name, s.code AS service_code 
               FROM queques q 
               JOIN services s ON q.service_id = s.id
               WHERE q.id = ? AND q.queue_code = ?"
    );
    $stmt->execute([$id, $code]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        die("data nomor antrian tidak di temukan");
    }
} catch (PDOException $e) {
    error_log("Error fetching ticket : " . $e->getMessage());
    die("terjadi kesalahan sistem");
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Ticket antrean - <?= e($ticket["queue_code"]) ?></title>
    <style>
        /*            ini bagian front end tolong di isi ya wahai assistent ai*/
    </style>
</head>

<body>

<div class="ticket-box">
    <div class="ticket-title">tiket antrean layanan</div>
    <div class="ticket-sub">Pusat layanan terpadu</div>

    <div class="service-bagde"><?= e($ticket["queue_code"]) ?></div>

    <table class="info-table">
        <tr>
            <td class="label">Name :</td>
            <td class="val"><?= e($ticket["customer_name"]) ?></td>
        </tr>
        <tr>
            <td class="label">Telepon :</td>
            <td class="val"><?= e($ticket["customer_phone"]) ?></td>
        </tr>
        <tr>
            <td class="label">tanggal :</td>
            <td class="val"><?= e(format_indo_date($ticket["appointment_date"])) ?></td>
        </tr>
        <tr>
            <td class="label">Slot Waktu :</td>
            <td class="val"><?= e($ticket["time_slot"]) ?>Wib</td>
        </tr>
        <tr>
            <td class="label"> Status :</td>
            <td class="val" style="color : #0284c7;"><?= e($ticket["status"]) ?></td>
        </tr>
    </table>

    <div class="instructions">
        * Harap hadir 10menit sebelum slot waktu anda </br>
        * Pantau panggilan nomor antrean anda di layar monitor.
    </div>

    <div class="btn-group">
        <button class="btn btn-print" onclick="window.print()"> Cetak ticket</button>
        <a href="index.php" class="btn btn-back">kembali </a>
    </div>
</div>
</body>
</html>