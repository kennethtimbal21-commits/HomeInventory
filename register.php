<?php
session_start();
require 'db.php';

// If user is already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if (!empty($username) && !empty($email) && !empty($password) && !empty($confirm)) {
        if ($password !== $confirm) {
            $error = "Passwords do not match.";
        } else {
            // Check if username or email is taken
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);

            if ($stmt->fetch()) {
                $error = "Username or Email is already taken.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
                
                if ($stmt->execute([$username, $email, $hashedPassword])) {
                    $success = "Account created successfully! You can now login.";
                } else {
                    $error = "Failed to create account. Please try again.";
                }
            }
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CatSU Inventory System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-violet: #a855f7;
            --primary-hover: #9333ea;
            --card-bg: rgba(15, 10, 28, 0.92);
            --card-border: rgba(192, 132, 252, 0.35);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            color: #f8fafc;
            background: 
                linear-gradient(135deg, rgba(10, 7, 22, 0.94) 0%, rgba(22, 11, 40, 0.92) 50%, rgba(35, 12, 78, 0.94) 100%),
                url('https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1920&q=80') center/cover no-repeat fixed;
        }

        .register-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.8), 0 0 30px rgba(168, 85, 247, 0.2);
            width: 100%;
            max-width: 440px;
            padding: 40px;
        }

        .brand-logo-img {
            width: 95px;
            height: 95px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid rgba(192, 132, 252, 0.5);
            box-shadow: 0 0 15px rgba(168, 85, 247, 0.4);
            transition: transform 0.3s ease;
        }

        .brand-logo-img:hover {
            transform: scale(1.05);
        }

        .text-bright { color: #ffffff !important; }
        .text-subtle { color: #cbd5e1 !important; }

        .form-control {
            background: #140d24 !important;
            border: 1px solid rgba(192, 132, 252, 0.35) !important;
            color: #ffffff !important;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: all 0.25s ease;
        }

        .form-control:focus {
            background: #1c1233 !important;
            border-color: var(--primary-violet) !important;
            box-shadow: 0 0 0 0.25rem rgba(168, 85, 247, 0.3);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6) !important;
        }

        .input-group-text {
            background: #140d24 !important;
            border: 1px solid rgba(192, 132, 252, 0.35) !important;
            border-right: none !important;
            color: #d8b4fe !important;
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }

        .input-group .form-control {
            border-left: none !important;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .btn-violet {
            background: linear-gradient(135deg, #a855f7, #7e22ce);
            border: none;
            color: #ffffff;
            font-weight: 600;
            border-radius: 12px;
            padding: 12px;
            width: 100%;
            transition: all 0.25s ease;
            box-shadow: 0 6px 16px rgba(126, 34, 206, 0.4);
        }

        .btn-violet:hover {
            background: linear-gradient(135deg, #9333ea, #6b21a8);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(126, 34, 206, 0.55);
        }

        a {
            color: #d8b4fe;
            text-decoration: none;
            font-weight: 600;
        }

        a:hover {
            color: #f3e8ff;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="register-card">
        <div class="text-center mb-4">
            <img src="csu-logo.png" alt="CatSU Seal" class="brand-logo-img mb-3">
            <h4 class="fw-bold text-bright m-0">Create Account</h4>
            <p class="text-subtle small mt-1">CatSU Inventory System</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger bg-danger bg-opacity-25 text-white border-0 alert-dismissible fade show rounded-3 small mb-4" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2 text-danger"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success bg-success bg-opacity-25 text-white border-0 alert-dismissible fade show rounded-3 small mb-4" role="alert">
                <i class="fa-solid fa-circle-check me-2 text-success"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="mb-3">
                <label class="form-label small text-subtle fw-bold">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="Choose a username" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small text-subtle fw-bold">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small text-subtle fw-bold">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Create a password" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small text-subtle fw-bold">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-check-double"></i></span>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-violet mb-3"><i class="fa-solid fa-user-plus me-2"></i> Register</button>
        </form>

        <div class="text-center mt-3">
            <span class="text-subtle small">Already have an account?</span>
            <a href="login.php" class="small ms-1">Login here</a>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>