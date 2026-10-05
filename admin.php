<?php
require 'auth.php';
check_auth();
require 'db.php';

// Ensure user is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error   = '';

// --- 1. HANDLE ADMIN ACTIONS (Role Update & User Deletion) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update Role
    if (isset($_POST['action_update_role'])) {
        $target_id = (int)$_POST['user_id'];
        $new_role  = $_POST['role'] === 'admin' ? 'admin' : 'user';

        // Prevent admin from revoking their own admin access
        if ($target_id === (int)$_SESSION['user_id'] && $new_role !== 'admin') {
            $error = "You cannot revoke your own admin privilege.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$new_role, $target_id]);
            $message = "User role updated successfully!";
        }
    }

    // Delete User & Their Inventory Data
    if (isset($_POST['action_delete_user'])) {
        $target_id = (int)$_POST['user_id'];

        if ($target_id === (int)$_SESSION['user_id']) {
            $error = "You cannot delete your own account while logged in as admin.";
        } else {
            $pdo->beginTransaction();
            try {
                // Delete user's inventory items first
                $stmt_inv = $pdo->prepare("DELETE FROM inventory WHERE user_id = ?");
                $stmt_inv->execute([$target_id]);

                // Delete the user record
                $stmt_user = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt_user->execute([$target_id]);

                $pdo->commit();
                $message = "User and associated inventory items deleted successfully.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to delete user: " . $e->getMessage();
            }
        }
    }
}

// --- 2. FETCH SYSTEM-WIDE STATS ---
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_items = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
$total_units = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM inventory")->fetchColumn();

// --- 3. FETCH ALL USERS ---
$stmt_users = $pdo->query("
    SELECT u.id, u.username, u.role, u.created_at, 
           COUNT(i.id) AS item_count, 
           COALESCE(SUM(i.quantity), 0) AS unit_count 
    FROM users u 
    LEFT JOIN inventory i ON u.id = i.user_id 
    GROUP BY u.id 
    ORDER BY u.id ASC
");
$users = $stmt_users->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control Panel - CatSU Inventory System</title>
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
            color: #f8fafc;
            margin: 0;
            padding-bottom: 60px;
            background: 
                linear-gradient(135deg, rgba(10, 7, 22, 0.94) 0%, rgba(22, 11, 40, 0.92) 50%, rgba(35, 12, 78, 0.94) 100%),
                url('https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1920&q=80') center/cover no-repeat fixed;
        }

        /* Navbar Styling */
        .navbar-custom {
            background: rgba(15, 10, 28, 0.95);
            border-bottom: 1px solid var(--card-border);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .brand-logo-nav {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border-radius: 50%;
            border: 1px solid rgba(192, 132, 252, 0.5);
            box-shadow: 0 0 10px rgba(168, 85, 247, 0.3);
        }

        /* Glass Cards */
        .glass-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.7), 0 0 25px rgba(168, 85, 247, 0.15);
        }

        /* Text Helpers */
        .text-bright { color: #ffffff !important; }
        .text-subtle { color: #cbd5e1 !important; }
        .text-accent { color: #d8b4fe !important; }

        /* Stat Icon Badges */
        .stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            background: rgba(168, 85, 247, 0.25);
            border: 1px solid rgba(192, 132, 252, 0.4);
            color: #f3e8ff;
        }

        /* Form Inputs */
        .form-select {
            background: #140d24 !important;
            border: 1px solid rgba(192, 132, 252, 0.35) !important;
            color: #ffffff !important;
            border-radius: 10px;
            padding: 6px 12px;
            font-size: 0.875rem;
        }

        .form-select option {
            background-color: #120b20;
            color: #ffffff;
        }

        /* Table Styling */
        .table {
            color: #f8fafc;
            vertical-align: middle;
            margin-bottom: 0;
        }

        .table thead th {
            background: rgba(168, 85, 247, 0.25);
            color: #e9d5ff;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.9px;
            border-bottom: 1px solid var(--card-border);
            padding: 14px 16px;
        }

        .table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(192, 132, 252, 0.15);
            background: transparent;
            color: #f1f5f9;
        }

        .table tbody tr:hover {
            background: rgba(168, 85, 247, 0.12);
        }

        /* Badges */
        .badge-role-admin {
            background: rgba(234, 179, 8, 0.25);
            border: 1px solid rgba(250, 204, 21, 0.5);
            color: #fef08a;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 8px;
        }

        .badge-role-user {
            background: rgba(168, 85, 247, 0.25);
            border: 1px solid rgba(192, 132, 252, 0.5);
            color: #f3e8ff;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4 px-4 py-3">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-3 fw-bold fs-5 text-bright" href="admin.php">
                <img src="csu-logo.png" alt="CatSU Seal" class="brand-logo-nav">
                <span>CatSU Admin Control Panel</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-subtle small d-none d-sm-inline">Logged in as <strong class="text-bright"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></strong></span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm px-3 rounded-3 fw-semibold"><i class="fa-solid fa-right-from-bracket me-1"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Status Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-success bg-success bg-opacity-25 text-white border-0 alert-dismissible fade show mb-4 rounded-3" role="alert">
                <i class="fa-solid fa-circle-check me-2 text-success"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger bg-danger bg-opacity-25 text-white border-0 alert-dismissible fade show mb-4 rounded-3" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2 text-danger"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- System Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="glass-card p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-subtle small fw-bold text-uppercase tracking-wider">Total Accounts</span>
                        <h2 class="fw-bold m-0 mt-1 text-bright"><?= number_format($total_users) ?></h2>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-card p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-subtle small fw-bold text-uppercase tracking-wider">Total Categories</span>
                        <h2 class="fw-bold m-0 mt-1 text-bright"><?= number_format($total_items) ?></h2>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-cubes"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-card p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-subtle small fw-bold text-uppercase tracking-wider">System Units Stored</span>
                        <h2 class="fw-bold m-0 mt-1 text-bright"><?= number_format($total_units) ?></h2>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registered Users Table -->
        <div class="glass-card p-4">
            <div class="mb-4">
                <h5 class="fw-bold m-0 text-bright"><i class="fa-solid fa-user-shield me-2 text-accent"></i> System User Management</h5>
                <p class="text-subtle small m-0 mt-1">Manage user access permissions, roles, and accounts</p>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Current Role</th>
                            <th>Registered Items</th>
                            <th>Total Units</th>
                            <th>Change Role</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="fw-bold text-accent">#<?= (int)$user['id'] ?></td>
                                <td class="fw-bold text-bright"><?= htmlspecialchars($user['username']) ?></td>
                                <td>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="badge badge-role-admin"><i class="fa-solid fa-shield-halved me-1"></i> Admin</span>
                                    <?php else: ?>
                                        <span class="badge badge-role-user"><i class="fa-solid fa-user me-1"></i> User</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-bright fw-semibold"><?= number_format($user['item_count']) ?></td>
                                <td class="text-bright fw-semibold"><?= number_format($user['unit_count']) ?></td>
                                <td>
                                    <form method="POST" action="admin.php" class="d-flex gap-2 align-items-center" style="max-width: 180px;">
                                        <input type="hidden" name="action_update_role" value="1">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <select name="role" class="form-select form-select-sm">
                                            <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-light rounded-2"><i class="fa-solid fa-check"></i></button>
                                    </form>
                                </td>
                                <td class="text-end">
                                    <?php if ((int)$user['id'] !== (int)$_SESSION['user_id']): ?>
                                        <form method="POST" action="admin.php" class="d-inline" onsubmit="return confirm('Deleting this user will permanently erase all their inventory records. Proceed?');">
                                            <input type="hidden" name="action_delete_user" value="1">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-2"><i class="fa-solid fa-trash me-1"></i> Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="small text-subtle italic">Active Account</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>