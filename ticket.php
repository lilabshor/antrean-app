<?php

declare(strict_types=1);

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/helpers/functions.php";

$id   = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$code = trim($_GET["code"] ?? "");

if (!$id || empty($code)) {
    die("Tiket tidak valid.");
}

try {
    $stmt = $pdo->prepare(
        "SELECT q.*, s.name AS service_name, s.code AS service_code
           FROM queues q
           JOIN services s ON q.service_id = s.id
           WHERE q.id = ? AND q.queue_code = ?"
    );
    $stmt->execute([$id, $code]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        die("Data nomor antrean tidak ditemukan.");
    }
} catch (PDOException $e) {
    error_log("Error fetching ticket: " . $e->getMessage());
    die("Terjadi kesalahan sistem.");
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiket Antrean - <?= e($ticket["queue_code"]) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .ticket-box {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            width: 100%;
            max-width: 420px;
            text-align: center;
        }

        .ticket-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #64748b;
            margin-bottom: 0.2rem;
        }

        .ticket-sub {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 1.5rem;
        }

        .service-badge {
            display: inline-block;
            background: #2563eb;
            color: #fff;
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: 4px;
            padding: 0.6rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            margin-bottom: 1.5rem;
        }

        .info-table tr {
            border-bottom: 1px solid #f1f5f9;
        }

        .info-table td {
            padding: 0.5rem 0.4rem;
            font-size: 0.875rem;
        }

        .info-table td.label {
            color: #64748b;
            width: 35%;
            font-weight: 500;
        }

        .info-table td.val {
            color: #1e293b;
            font-weight: 600;
        }

        .instructions {
            background: #f8fafc;
            border-left: 3px solid #2563eb;
            padding: 0.75rem 1rem;
            font-size: 0.8rem;
            color: #475569;
            text-align: left;
            border-radius: 0 6px 6px 0;
            margin-bottom: 1.5rem;
            line-height: 1.7;
        }

        .btn-group {
            display: flex;
            gap: 0.75rem;
        }

        .btn {
            flex: 1;
            padding: 0.65rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: opacity 0.2s;
            border: none;
        }

        .btn:hover { opacity: 0.85; }

        .btn-print {
            background: #2563eb;
            color: #fff;
        }

        .btn-back {
            background: #f1f5f9;
            color: #374151;
        }

        @media print {
            body { background: #fff; }
            .btn-group { display: none; }
            .ticket-box { box-shadow: none; }
        }
    </style>
</head>

<body>

<div class="ticket-box">
    <div class="ticket-title">Tiket Antrean Layanan</div>
    <div class="ticket-sub">Pusat Layanan Terpadu</div>

    <div class="service-badge"><?= e($ticket["queue_code"]) ?></div>

    <table class="info-table">
        <tr>
            <td class="label">Nama :</td>
            <td class="val"><?= e($ticket["customer_name"]) ?></td>
        </tr>
        <tr>
            <td class="label">Telepon :</td>
            <td class="val"><?= e($ticket["customer_phone"]) ?></td>
        </tr>
        <tr>
            <td class="label">Tanggal :</td>
            <td class="val"><?= e(format_indo_date($ticket["appointment_date"])) ?></td>
        </tr>
        <tr>
            <td class="label">Slot Waktu :</td>
            <td class="val"><?= e($ticket["time_slot"]) ?> WIB</td>
        </tr>
        <tr>
            <td class="label">Layanan :</td>
            <td class="val"><?= e($ticket["service_name"]) ?></td>
        </tr>
        <tr>
            <td class="label">Status :</td>
            <td class="val" style="color:#0284c7;"><?= e($ticket["status"]) ?></td>
        </tr>
    </table>

    <div class="instructions">
        * Harap hadir 10 menit sebelum slot waktu Anda.<br>
        * Pantau panggilan nomor antrean Anda di layar monitor.
    </div>

    <div class="btn-group">
        <button class="btn btn-print" onclick="window.print()">Cetak Tiket</button>
        <a href="index.php" class="btn btn-back">&larr; Kembali</a>
    </div>
</div>
</body>
</html>