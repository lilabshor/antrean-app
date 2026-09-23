<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../helpers/functions.php";

start_secure_session();

if (!empty($_SESSION["user_id"])) {
    header("Location : dashboard.php");
    exit;
}

$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST["csrf_token"] ?? "";
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if (!verify_csrf_token($csrf_token)) {
        $error = "Sesi kedaluwarsa. Silahkan muat ulang halaman";
    } elseif (empty($username) || empty($password)) {
        $error = "username dan password wajin di isi";

    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user["password_hash"])) {

                session_regenerate_id(true);

                $_SESSION["user_id"] = (int)$user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["role"] = $user["role"];
                $_SESSION["counter_number"] = (int)($user["counter_number"] ?? 1);

                header("location:dashboard.php");
                exit;
            } else {
                $error = "kredensial login salah. silahkan periksa kembali username dan password.";
            }
        } catch (PDOException $e) {
            error_log("login error : " . $e->getMessage());
            $error = "terjadi gangguan sistem silahkan coba lagi.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Petugas antrean</title>
    <style>
        /*            ini bagian front end tolong di isi ya wahai assistent ai*/
    </style>
</head>

<body>

<div class="login-card">
    <div class="login-title">Portal petugas</div>
    <div class="login-sub">Masuk untuk mengelola loket dan antrean</div>

    <?php if ($error) : ?>
        <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="password"
        </div>

        <div class="form-group">
            <label for="password"> Password</label>
            <input type="password" id="password" name="password" placeholder="**********" required>
        </div>

        <button type="submit" class="btn-login">Masuk ke Dashbooard</button>
    </form>

    <a href="../index.php" class="black-link"> <- kembali ke halaman utama</a>
</div>
</body>
</html>
