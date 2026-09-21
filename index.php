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
}

$time_slots = get_available_time_slots();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $csrf_token = $_POST["csrf_token"] ?? "";
    $service_id = filter_input(INPUT_POST, "service_id", FILTER_VALIDATE_INT);
    $customer_name = trim($_POST["customer_name"] ?? "");
    $customer_phone = trim($_POST["customer_phone"] ?? "");
    $appointment_date = trim($_POST["appointment_data"] ?? "");
    $time_slots = trim($_POST["time_slots"] ?? "");
}
if (!verify_csrf_token($csrf_token)) {
    $errors[] = "CSRF token tidak valid";
}
if (!$service_id) {
    $errors[] = "Service ID tidak boleh kosong";
}

if (empty($customer_name) || mb_strln($customer_name) > 100) {
    $errors[] = "Customer name tidak boleh lebih dari 100 karakter";
}

if (!preg_match("/^[0-9+() - ]{9,20}$/", $customer_phone)) {
    $errors[] = "Customer phone tidak boleh lebih dari 9 digit";
}

$today = date("Y-m-d");
$max_date = date("Y-m-d", strtotime("+7 days"));
if (empty($appointment_date) || $appointment_date < $today || $appointment_date > $max_date) {
    $errors[] = "Appointment date tidak boleh lebih dari 7 digit";
}

if (!in_array($time_slots, $time_slots, true)) {
    $errors[] = "Time slots tidak valid";

}

if (empty($errors)) {
    try {
        $pdo->beginTransaction();

        $stmt_svc = $pdo->prepare("SELECT * FROM services WHRE id = ? AND is_active = 1 FOR UPDATE");
        $stmt_svc->execute([$service_id]);
        $selected_services = $stmt_svc->fetch();

        if (!$selected_services) {
            throw new Exception("Service tidak ditemukan");
        }

        $stmt_count = $pdo->prepare(
                "SELECT COUNT (*) FROM  queues
                        WHERE service_id = ? AND  appointmrnt_date = ? AND time_slots = ?
                        AND ststus != CANCELLED FOR UPDATE"
        );
        $stmt_count->execute([$service_id, $appointment_date, $time_slots]);
        $current_slot_count = (int)$stmt_count->fetchColumn();

        if ($current_slot_count >= $selected_services ["quota_per_slot"]) {
            throw new Exception("Service tidak dapat ditemukan");
        }
        $stmt_max = $pdo->prepare(
                "SELECT COALESCE(MAX(queue_number), 0) + 1 AS next_number
                FROM  queues
                WHERE service_id = ? AND appointment_date = ? FOR UPDATE"
        );
        $stmt_max->execute([$service_id, $appointment_date]);
        $next_queue_number = (int)$stmt_max->fetchColumn();

        $queue_code = sprintf("%s-%03d", $selected_services["code"], $next_queue_number + 1);
// Simpan Data Antrean
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
                $time_slots,
        ]);

        $new_queue_id = (int)$pdo->lastInsertId();
        $pdo->commit();

        header("location: ticket.php?id=" . $new_queue_id . "urlencode($queue_code)");
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>

<html>
<head>
    <style>
        /*            ini bagian front end tolong di isi ya wahai assistent ai*/
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Sistem Antrian Layanan</h1>
        <p>Ambil nomor antrean & pilih jadwal layanan dengan mudah dan cepat</p>
    </div>

    <div class="nav-links">
        <a href="display.php" target="_blank"> Layar display Antrean</a>
        <a href="admin/login.php">Login petugas/admin</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Terjadi kesalahan</strong>
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
            <label for="time_slot">Pilih Slot Waktu *</label>
            <select name="time_slot" id="time_slot" required>
                <option value="">-- Pilih Jam Kedatangan --</option>

                <?php foreach ($time_slots as $slot): ?>
                    <option value="<?= e($slot) ?>" <?= (isset($_POST["time_slot"]) && $_POST["time_slot"] === $slot) ? "selected" : "" ?>>
                        <?= e($slot) ?> WIB
                    </option>
                <?php endforeach; ?>

            </select>
        </div>
        <div class="form-group">
            <label for="customer_name">Nama Lengkap Pengunjung</label>
            <input type="text" name="customer_name" id="customer_name" placeholder="masukan nama sesuai ktp anda"
                   value="<?= e($_POST["customer_name"] ?? "") ?> " required maxlength="100">
        </div>

        <div class="form-group">
            <label for="customer_phone"> nomor whatsap / telepon </label>
            <input type="tel" name="customer_phone" id="customer_phone" placeholder="contoh : 08123456789"
                   value="<?= e($_POST["customer_phone"] ?? "") ?>" required maxlength="20">
        </div>

        <button type="submit" class="btn-submit">Dapatkan nomor Antrean</button>
    </form>

    <div class="footer-note">
        setiap nomor antrian di lindungi verifikasi transaksi database untuk mencegah antrian ganda
    </div>
</div>
</body>

</html>
