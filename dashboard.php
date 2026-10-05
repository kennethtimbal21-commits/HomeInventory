<?php
require 'auth.php';
check_auth();
require 'db.php';

// Redirect admins away to the admin panel
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error   = '';

// --- 1. HANDLE CRUD ACTIONS (POST Requests) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CREATE ITEM
    if (isset($_POST['action_create'])) {
        $item_name = trim($_POST['item_name']);
        $category  = trim($_POST['category']);
        $quantity  = (int)$_POST['quantity'];
        $location  = trim($_POST['location']);
        $notes     = trim($_POST['notes']);

        if (!empty($item_name) && !empty($category) && $quantity >= 0) {
            $stmt = $pdo->prepare("INSERT INTO inventory (user_id, item_name, category, quantity, location, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $item_name, $category, $quantity, $location, $notes]);
            $message = "Item added successfully!";
        } else {
            $error = "Please fill in all required fields.";
        }
    }

    // UPDATE ITEM
    if (isset($_POST['action_update'])) {
        $item_id   = (int)$_POST['item_id'];
        $item_name = trim($_POST['item_name']);
        $category  = trim($_POST['category']);
        $quantity  = (int)$_POST['quantity'];
        $location  = trim($_POST['location']);
        $notes     = trim($_POST['notes']);

        if (!empty($item_name) && !empty($category) && $quantity >= 0) {
            $stmt = $pdo->prepare("UPDATE inventory SET item_name = ?, category = ?, quantity = ?, location = ?, notes = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$item_name, $category, $quantity, $location, $notes, $item_id, $user_id]);
            $message = "Item updated successfully!";
        } else {
            $error = "Failed to update item. Make sure required fields are filled.";
        }
    }

    // DELETE ITEM
    if (isset($_POST['action_delete'])) {
        $item_id = (int)$_POST['item_id'];
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ? AND user_id = ?");
        $stmt->execute([$item_id, $user_id]);
        $message = "Item deleted successfully.";
    }
}

// --- 2. FETCH SUMMARY STATS ---
$stmt_stats = $pdo->prepare("SELECT COUNT(*) AS total_items, COALESCE(SUM(quantity), 0) AS total_qty FROM inventory WHERE user_id = ?");
$stmt_stats->execute([$user_id]);
$stats = $stmt_stats->fetch();

// --- 3. FETCH ITEMS WITH SEARCH & FILTER ---
$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

$sql = "SELECT * FROM inventory WHERE user_id = ?";
$params = [$user_id];

if (!empty($search)) {
    $sql .= " AND (item_name LIKE ? OR location LIKE ? OR notes LIKE ?)";
    $searchTerm = "%{$search}%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm);
}

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CatSU Inventory System</title>
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
            position: relative;
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

        /* Text Helper Classes */
        .text-bright {
            color: #ffffff !important;
        }

        .text-subtle {
            color: #cbd5e1 !important;
        }

        .text-accent {
            color: #d8b4fe !important;
        }

        /* Stat Icon Badges */
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: rgba(168, 85, 247, 0.25);
            border: 1px solid rgba(192, 132, 252, 0.4);
            color: #f3e8ff;
        }

        /* Form Inputs */
        .form-control, .form-select {
            background: #140d24 !important;
            border: 1px solid rgba(192, 132, 252, 0.35) !important;
            color: #ffffff !important;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 0.95rem;
            transition: all 0.25s ease;
        }

        .form-control:focus, .form-select:focus {
            background: #1c1233 !important;
            border-color: var(--primary-violet) !important;
            box-shadow: 0 0 0 0.25rem rgba(168, 85, 247, 0.3);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6) !important;
        }

        .form-select option {
            background-color: #120b20;
            color: #ffffff;
        }

        /* Buttons */
        .btn-violet {
            background: linear-gradient(135deg, #a855f7, #7e22ce);
            border: none;
            color: #ffffff;
            font-weight: 600;
            border-radius: 12px;
            padding: 10px 18px;
            transition: all 0.25s ease;
            box-shadow: 0 6px 16px rgba(126, 34, 206, 0.4);
        }

        .btn-violet:hover {
            background: linear-gradient(135deg, #9333ea, #6b21a8);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(126, 34, 206, 0.55);
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

        .table tbody tr {
            transition: background 0.2s ease;
        }

        .table tbody tr:hover {
            background: rgba(168, 85, 247, 0.12);
        }

        /* Badge Styling */
        .badge-category {
            background: rgba(168, 85, 247, 0.35);
            border: 1px solid rgba(192, 132, 252, 0.5);
            color: #f3e8ff;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 8px;
        }

        /* Modal Styling */
        .modal-content {
            background: #120b22;
            border: 1px solid var(--card-border);
            color: #f8fafc;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.85);
        }

        .modal-header, .modal-footer {
            border-color: rgba(192, 132, 252, 0.2);
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4 px-4 py-3">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-3 fw-bold fs-5 text-bright" href="dashboard.php">
                <img src="csu-logo.png" alt="CatSU Seal" class="brand-logo-nav">
                <span>CatSU Inventory System</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-subtle small d-none d-sm-inline">Logged in as <strong class="text-bright"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></strong></span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm px-3 rounded-3 fw-semibold"><i class="fa-solid fa-right-from-bracket me-1"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Status Alerts -->
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

        <!-- Stat Summary Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="glass-card p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-subtle small fw-bold text-uppercase tracking-wider">Total Item Categories</span>
                        <h2 class="fw-bold m-0 mt-1 text-bright"><?= number_format($stats['total_items']) ?></h2>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-cubes"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="glass-card p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-subtle small fw-bold text-uppercase tracking-wider">Total Stored Units</span>
                        <h2 class="fw-bold m-0 mt-1 text-bright"><?= number_format($stats['total_qty']) ?></h2>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Controls: Header, Add Item, Search & Filter -->
        <div class="glass-card p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h5 class="fw-bold m-0 text-bright"><i class="fa-solid fa-list me-2 text-accent"></i> Inventory Items</h5>
                    <p class="text-subtle small m-0 mt-1">Manage, search, and track all university assets and items</p>
                </div>
                <button class="btn btn-violet" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="fa-solid fa-plus me-1"></i> Add New Item
                </button>
            </div>

            <!-- Search & Filter Form -->
            <form method="GET" action="dashboard.php" class="row g-2">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="Search by item name, location, or notes..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-4">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <option value="Electronics" <?= $category === 'Electronics' ? 'selected' : '' ?>>Electronics</option>
                        <option value="Furniture" <?= $category === 'Furniture' ? 'selected' : '' ?>>Furniture</option>
                        <option value="Tools" <?= $category === 'Tools' ? 'selected' : '' ?>>Tools</option>
                        <option value="Personal" <?= $category === 'Personal' ? 'selected' : '' ?>>Personal</option>
                        <option value="Other" <?= $category === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-violet w-100"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
                    <?php if (!empty($search) || !empty($category)): ?>
                        <a href="dashboard.php" class="btn btn-outline-light rounded-3 px-3"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Inventory Data Table -->
        <div class="glass-card p-3">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Location</th>
                            <th>Notes</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($items) > 0): ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="fw-bold text-bright"><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td><span class="badge badge-category"><?= htmlspecialchars($item['category']) ?></span></td>
                                    <td class="fw-bold text-accent" style="font-size: 1.05rem;"><?= (int)$item['quantity'] ?></td>
                                    <td class="text-bright"><?= htmlspecialchars($item['location'] ?? '-') ?></td>
                                    <td class="small text-subtle"><?= htmlspecialchars($item['notes'] ?? '-') ?></td>
                                    <td class="text-end">
                                        <!-- Edit Modal Trigger -->
                                        <button class="btn btn-sm btn-outline-warning rounded-2 me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editItemModal<?= $item['id'] ?>">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <!-- Delete Form -->
                                        <form method="POST" action="dashboard.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                            <input type="hidden" name="action_delete" value="1">
                                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-2"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Item Modal -->
                                <div class="modal fade" id="editItemModal<?= $item['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold text-bright"><i class="fa-solid fa-pen-to-square me-2 text-accent"></i> Edit Item</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="dashboard.php">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action_update" value="1">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">

                                                    <div class="mb-3">
                                                        <label class="form-label small text-subtle fw-bold">Item Name</label>
                                                        <input type="text" name="item_name" class="form-control" value="<?= htmlspecialchars($item['item_name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small text-subtle fw-bold">Category</label>
                                                        <select name="category" class="form-select" required>
                                                            <option value="Electronics" <?= $item['category'] === 'Electronics' ? 'selected' : '' ?>>Electronics</option>
                                                            <option value="Furniture" <?= $item['category'] === 'Furniture' ? 'selected' : '' ?>>Furniture</option>
                                                            <option value="Tools" <?= $item['category'] === 'Tools' ? 'selected' : '' ?>>Tools</option>
                                                            <option value="Personal" <?= $item['category'] === 'Personal' ? 'selected' : '' ?>>Personal</option>
                                                            <option value="Other" <?= $item['category'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small text-subtle fw-bold">Quantity</label>
                                                        <input type="number" name="quantity" class="form-control" min="0" value="<?= (int)$item['quantity'] ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small text-subtle fw-bold">Location</label>
                                                        <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($item['location'] ?? '') ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small text-subtle fw-bold">Notes</label>
                                                        <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($item['notes'] ?? '') ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-violet">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-subtle">
                                    <i class="fa-solid fa-box-open fs-2 mb-2 d-block opacity-50 text-accent"></i>
                                    No inventory items found. Click <strong class="text-bright">"Add New Item"</strong> to get started.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-bright"><i class="fa-solid fa-plus-circle me-2 text-accent"></i> Add New Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="dashboard.php">
                    <div class="modal-body">
                        <input type="hidden" name="action_create" value="1">

                        <div class="mb-3">
                            <label class="form-label small text-subtle fw-bold">Item Name</label>
                            <input type="text" name="item_name" class="form-control" placeholder="e.g. Dell UltraSharp Monitor" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-subtle fw-bold">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="Electronics" selected>Electronics</option>
                                <option value="Furniture">Furniture</option>
                                <option value="Tools">Tools</option>
                                <option value="Personal">Personal</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-subtle fw-bold">Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="0" value="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-subtle fw-bold">Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. Building A / Room 102">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-subtle fw-bold">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Optional details (e.g. serial numbers, condition)..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-violet">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>