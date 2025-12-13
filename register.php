<?php
require_once 'config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!in_array($role, ['teacher', 'student'])) {
        $error = 'Invalid role selected';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Email already registered';
        } else {
            // Create user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
            
            if ($stmt->execute([$name, $email, $password_hash, $role])) {
                redirect('login.php', 'Registration successful! Please login.', 'success');
            } else {
                $error = 'Registration failed. Please try again.';
            }
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
    <title>Register - QuizSprint</title>
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

        <div class="auth-card auth-card-wide">
            <!-- Back to home link -->
            <div class="auth-nav">
                <a href="index.php" class="back-link">
                    <span>←</span> Back to QuizSprint
                </a>
            </div>

            <div class="auth-header">
                <div class="auth-icon">✨</div>
                <h1>Join QuizSprint</h1>
                <p>Create your account and start your quiz journey</p>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">
                            <span class="label-icon">👤</span>
                            Full Name
                        </label>
                        <input type="text" id="name" name="name" required 
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                               placeholder="Enter your full name">
                    </div>

                    <div class="form-group">
                        <label for="email">
                            <span class="label-icon">📧</span>
                            Email Address
                        </label>
                        <input type="email" id="email" name="email" required 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="Enter your email">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">
                            <span class="label-icon">🔒</span>
                            Password
                        </label>
                        <input type="password" id="password" name="password" required minlength="6"
                               placeholder="At least 6 characters">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">
                            <span class="label-icon">🔐</span>
                            Confirm Password
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                               placeholder="Confirm your password">
                    </div>
                </div>

                <div class="form-group">
                    <label for="role">
                        <span class="label-icon">👨‍🎓</span>
                        I am a:
                    </label>
                    <div class="role-selection">
                        <label class="role-option">
                            <input type="radio" name="role" value="teacher" <?= ($_POST['role'] ?? '') === 'teacher' ? 'checked' : '' ?>>
                            <div class="role-card">
                                <div class="role-icon">👨‍🏫</div>
                                <div class="role-title">Teacher</div>
                                <div class="role-desc">Create and manage quizzes</div>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="student" <?= ($_POST['role'] ?? '') === 'student' ? 'checked' : '' ?>>
                            <div class="role-card">
                                <div class="role-icon">🎓</div>
                                <div class="role-title">Student</div>
                                <div class="role-desc">Join and participate in quizzes</div>
                            </div>
                        </label>
                    </div>
                </div>

                <button type="submit" class="auth-btn">
                    <span>Create My Account</span>
                    <span class="btn-icon">→</span>
                </button>
            </form>

            <div class="auth-divider">
                <span>Already have an account?</span>
            </div>

            <div class="auth-footer">
                <a href="login.php" class="register-link">Sign in to QuizSprint</a>
                <p class="auth-footer-text">Welcome to the learning community!</p>
            </div>
        </div>
    </div>
</body>
</html>