<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

auth_start_session();
if (auth_current_user()) {
    header('Location: index.php');
    exit;
}

$pdo = app_require_db();
$pdo->exec("
    CREATE TABLE IF NOT EXISTS app_users (
        id BIGSERIAL PRIMARY KEY,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )
");

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Password and confirm password do not match.';
    } else {
        $insert_stmt = $pdo->prepare('INSERT INTO app_users (email, password_hash) VALUES (:email, :password_hash) RETURNING id, email');
        try {
            $insert_stmt->execute([
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $new_user = $insert_stmt->fetch();
            auth_login_user((int)$new_user['id'], (string)$new_user['email']);
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                $error = 'This email is already registered. Please login.';
            } else {
                $error = 'Could not create account right now. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Rorhonco</title>
    <style>
        * {box-sizing: border-box;}
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(59, 130, 246, 0.20), transparent 30%),
                radial-gradient(circle at bottom right, rgba(14, 165, 233, 0.16), transparent 30%),
                #eaf0ff;
            color: #0f172a;
            padding: 16px;
        }
        .card {
            width: 100%;
            max-width: 440px;
            background: #fff;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #dbe7ff;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
        }
        h1 {margin: 0 0 10px; font-size: 1.6rem;}
        .muted {margin: 0 0 16px; color: #64748b;}
        .field {display: grid; gap: 6px; margin-bottom: 12px;}
        label {font-weight: 700; color: #334155;}
        input {
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            width: 100%;
        }
        button {
            width: 100%;
            margin-top: 8px;
            padding: 11px 14px;
            border: none;
            border-radius: 10px;
            background: #1d4ed8;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }
        button:hover {background: #1e40af;}
        .error {
            margin: 0 0 12px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .meta {margin-top: 14px; font-size: 0.95rem; color: #475569;}
        .meta a {color: #1d4ed8; text-decoration: none; font-weight: 700;}
        .meta a:hover {text-decoration: underline;}
    </style>
</head>
<body>
    <main class="card">
        <h1>Register</h1>
        <p class="muted">Create your account using email and password.</p>
        <?php if ($error !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" required value="<?php echo htmlspecialchars($email); ?>">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>
            <div class="field">
                <label for="confirm_password">Confirm Password</label>
                <input id="confirm_password" name="confirm_password" type="password" required>
            </div>
            <button type="submit">Create Account</button>
        </form>
        <p class="meta">Already have an account? <a href="login.php">Login</a></p>
    </main>
</body>
</html>

