<?php

declare(strict_types=1);

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/helpers/functions.php";

start_secure_session();

$errors = [];
$success_data = null;

try {
    $stmt = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY code ASC");
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Gagal mengambil data: " . $e->getMessage();
    $services = [];
}

$available_time_slots = get_available_time_slots();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token         = $_POST["csrf_token"] ?? "";
    $service_id         = filter_input(INPUT_POST, "service_id", FILTER_VALIDATE_INT);
    $customer_name      = trim($_POST["customer_name"] ?? "");
    $customer_phone     = trim($_POST["customer_phone"] ?? "");
    $appointment_date   = trim($_POST["appointment_date"] ?? "");
    $selected_time_slot = trim($_POST["time_slot"] ?? "");

    if (!verify_csrf_token($csrf_token)) {
        $errors[] = "Token keamanan tidak valid, coba muat ulang halaman.";
    }

    if (!$service_id) {
        $errors[] = "Layanan harus dipilih.";
    }

    if (empty($customer_name) || mb_strlen($customer_name) > 100) {
        $errors[] = "Nama lengkap wajib diisi dan tidak boleh lebih dari 100 karakter.";
    }

    if (!preg_match("/^[0-9+()\- ]{9,20}$/", $customer_phone)) {
        $errors[] = "Nomor telepon tidak valid, harus 9–20 digit angka.";
    }

    $today    = date("Y-m-d");
    $max_date = date("Y-m-d", strtotime("+7 days"));
    if (empty($appointment_date) || $appointment_date < $today || $appointment_date > $max_date) {
        $errors[] = "Tanggal kunjungan harus antara hari ini sampai 7 hari ke depan.";
    }

    if (!in_array($selected_time_slot, $available_time_slots, true)) {
        $errors[] = "Slot waktu yang dipilih tidak valid.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt_svc = $pdo->prepare("SELECT * FROM services WHERE id = ? AND is_active = 1 FOR UPDATE");
            $stmt_svc->execute([$service_id]);
            $selected_service = $stmt_svc->fetch();

            if (!$selected_service) {
                throw new Exception("Layanan yang dipilih tidak ditemukan atau sudah tidak aktif.");
            }

            $stmt_count = $pdo->prepare(
                "SELECT COUNT(*) FROM queues
                    WHERE service_id = ? AND appointment_date = ? AND time_slot = ?
                    AND status != 'CANCELLED'
                    FOR UPDATE"
            );
            $stmt_count->execute([$service_id, $appointment_date, $selected_time_slot]);
            $current_slot_count = (int)$stmt_count->fetchColumn();

            if ($current_slot_count >= $selected_service["quota_per_slot"]) {
                throw new Exception("Slot waktu ini sudah penuh, silakan pilih slot lain.");
            }

            $stmt_max = $pdo->prepare(
                "SELECT COALESCE(MAX(queue_number), 0) + 1 AS next_number
                FROM queues
                WHERE service_id = ? AND appointment_date = ?
                FOR UPDATE"
            );
            $stmt_max->execute([$service_id, $appointment_date]);
            $next_queue_number = (int)$stmt_max->fetchColumn();

            $queue_code = sprintf("%s-%03d", $selected_service["code"], $next_queue_number);

            $stmt_insert = $pdo->prepare(
                "INSERT INTO queues (service_id, queue_number, queue_code, customer_name, customer_phone, appointment_date, time_slot, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'WAITING')"
            );

            $stmt_insert->execute([
                $service_id,
                $next_queue_number,
                $queue_code,
                $customer_name,
                $customer_phone,
                $appointment_date,
                $selected_time_slot,
            ]);

            $new_queue_id = (int)$pdo->lastInsertId();
            $pdo->commit();

            header("Location: ticket.php?id=" . $new_queue_id . "&code=" . urlencode($queue_code));
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Antrean Layanan</title>
    <meta name="description" content="Ambil nomor antrean dan pilih jadwal layanan dengan mudah dan cepat.">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            width: 100%;
            max-width: 540px;
        }

        .header {
            text-align: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1.2rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .header h1 {
            font-size: 1.5rem;
            color: #1e293b;
            margin-bottom: 0.3rem;
        }

        .header p {
            font-size: 0.875rem;
            color: #64748b;
        }

        .nav-links {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .nav-links a {
            font-size: 0.8rem;
            color: #3b82f6;
            text-decoration: none;
            padding: 0.35rem 0.75rem;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            background: #eff6ff;
            transition: background 0.2s;
        }

        .nav-links a:hover { background: #dbeafe; }

        .alert.alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.2rem;
            color: #dc2626;
            font-size: 0.875rem;
        }

        .alert.alert-danger ul { padding-left: 1rem; margin-top: 0.3rem; }

        .form-group {
            margin-bottom: 1.1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.4rem;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 0.6rem 0.85rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #1e293b;
            background: #fff;
            transition: border-color 0.2s;
            outline: none;
        }

        .form-group select:focus,
        .form-group input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 0.75rem;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: background 0.2s;
        }

        .btn-submit:hover { background: #1d4ed8; }

        .footer-note {
            margin-top: 1.2rem;
            font-size: 0.75rem;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Sistem Antrian Layanan</h1>
        <p>Ambil nomor antrean &amp; pilih jadwal layanan dengan mudah dan cepat</p>
    </div>

    <div class="nav-links">
        <a href="display.php" target="_blank">Layar Display Antrean</a>
        <a href="admin/login.php">Login Petugas / Admin</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Terjadi kesalahan:</strong>
            <ul style="margin-left: 1.2rem; margin-top: 0.4rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

        <div class="form-group">
            <label for="service_id">Pilih Layanan *</label>
            <select name="service_id" id="service_id" required>
                <option value="">-- Pilih Jenis Layanan --</option>
                <?php foreach ($services as $svc): ?>
                    <option value="<?= (int)$svc['id'] ?>" <?= (isset($_POST['service_id']) && (int)$_POST['service_id'] === (int)$svc['id']) ? 'selected' : '' ?>>
                        <?= e($svc['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="time_slot">Pilih Slot Waktu *</label>
            <select name="time_slot" id="time_slot" required>
                <option value="">-- Pilih Jam Kedatangan --</option>
                <?php foreach ($available_time_slots as $slot): ?>
                    <option value="<?= e($slot) ?>" <?= (isset($_POST["time_slot"]) && $_POST["time_slot"] === $slot) ? "selected" : "" ?>>
                        <?= e($slot) ?> WIB
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="appointment_date">Tanggal Kunjungan *</label>
            <input type="date" name="appointment_date" id="appointment_date"
                   value="<?= e($_POST["appointment_date"] ?? date("Y-m-d")) ?>"
                   min="<?= date("Y-m-d") ?>"
                   max="<?= date("Y-m-d", strtotime("+7 days")) ?>"
                   required>
        </div>

        <div class="form-group">
            <label for="customer_name">Nama Lengkap Pengunjung *</label>
            <input type="text" name="customer_name" id="customer_name"
                   placeholder="Masukkan nama sesuai KTP"
                   value="<?= e($_POST["customer_name"] ?? "") ?>"
                   required maxlength="100">
        </div>

        <div class="form-group">
            <label for="customer_phone">Nomor WhatsApp / Telepon *</label>
            <input type="tel" name="customer_phone" id="customer_phone"
                   placeholder="Contoh: 08123456789"
                   value="<?= e($_POST["customer_phone"] ?? "") ?>"
                   required maxlength="20">
        </div>

        <button type="submit" class="btn-submit">Dapatkan Nomor Antrean</button>
    </form>

    <div class="footer-note">
        Setiap nomor antrean dilindungi verifikasi transaksi database untuk mencegah antrean ganda.
    </div>
</div>
</body>
</html>
