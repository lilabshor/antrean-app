<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../helpers/functions.php";

start_secure_session();

if (!empty($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST["csrf_token"] ?? "";
    $username   = trim($_POST["username"] ?? "");
    $password   = $_POST["password"] ?? "";

    if (!verify_csrf_token($csrf_token)) {
        $error = "Sesi kedaluwarsa. Silakan muat ulang halaman.";
    } elseif (empty($username) || empty($password)) {
        $error = "Username dan password wajib diisi.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user["password_hash"])) {
                session_regenerate_id(true);

                $_SESSION["user_id"]        = (int)$user["id"];
                $_SESSION["username"]       = $user["username"];
                $_SESSION["full_name"]      = $user["full_name"];
                $_SESSION["role"]           = $user["role"];
                $_SESSION["counter_number"] = (int)($user["counter_number"] ?? 1);

                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Kredensial login salah. Silakan periksa kembali username dan password.";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = "Terjadi gangguan sistem, silakan coba lagi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Petugas Antrean</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2.2rem;
            width: 100%;
            max-width: 380px;
        }

        .login-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.3rem;
            text-align: center;
        }

        .login-sub {
            font-size: 0.85rem;
            color: #64748b;
            text-align: center;
            margin-bottom: 1.8rem;
        }

        .alert {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 0.7rem 1rem;
            margin-bottom: 1.2rem;
            color: #dc2626;
            font-size: 0.85rem;
        }

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

        .form-group input {
            width: 100%;
            padding: 0.65rem 0.85rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #1e293b;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }

        .btn-login {
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

        .btn-login:hover { background: #1d4ed8; }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.2rem;
            font-size: 0.82rem;
            color: #64748b;
            text-decoration: none;
        }

        .back-link:hover { color: #2563eb; }
    </style>
</head>

<body>

<div class="login-card">
    <div class="login-title">Portal Petugas</div>
    <div class="login-sub">Masuk untuk mengelola loket dan antrean</div>

    <?php if ($error): ?>
        <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   placeholder="Masukkan username" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   placeholder="**********" required>
        </div>

        <button type="submit" class="btn-login">Masuk ke Dashboard</button>
    </form>

    <a href="../index.php" class="back-link">&larr; Kembali ke halaman utama</a>
</div>
</body>
</html>
