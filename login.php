<?php
require_once 'config/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $redirect_url = hasRole('teacher') ? 'teacher_dashboard.php' : 'join_room.php';
    redirect($redirect_url);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            $redirect_url = $user['role'] === 'teacher' ? 'teacher_dashboard.php' : 'join_room.php';
            redirect($redirect_url, 'Welcome back, ' . $user['name'] . '!', 'success');
        } else {
            $error = 'Invalid email or password';
        }
    }
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - QuizSprint</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="auth-container">
        <!-- Background Elements -->
        <div class="auth-background">
            <div class="floating-shapes">
                <div class="shape shape-1"></div>
                <div class="shape shape-2"></div>
                <div class="shape shape-3"></div>
            </div>
        </div>

        <div class="auth-card">
            <!-- Back to home link -->
            <div class="auth-nav">
                <a href="index.php" class="back-link">
                    <span>←</span> Back to QuizSprint
                </a>
            </div>

            <div class="auth-header">
                <div class="auth-icon">🚀</div>
                <h1>Welcome Back!</h1>
                <p>Sign in to continue your quiz journey</p>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="email">
                        <span class="label-icon">📧</span>
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" required 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="Enter your email">
                </div>

                <div class="form-group">
                    <label for="password">
                        <span class="label-icon">🔒</span>
                        Password
                    </label>
                    <input type="password" id="password" name="password" required
                           placeholder="Enter your password">
                </div>

                <button type="submit" class="auth-btn">
                    <span>Login to QuizSprint</span>
                    <span class="btn-icon">→</span>
                </button>
            </form>

            <div class="auth-divider">
                <span>New to QuizSprint?</span>
            </div>

            <div class="auth-footer">
                <a href="register.php" class="register-link">Create your account</a>
                <p class="auth-footer-text">Join thousands of educators and students</p>
            </div>
        </div>
    </div>
</body>
</html>