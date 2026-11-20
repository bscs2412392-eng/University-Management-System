<?php
// module_view.php
session_start();

// Database Connection Include Karein
require_once 'db.php';

// Safe Database Connection Variable Check ($pdo ya$conn support)
if (!isset($pdo)) {
    if (isset($conn) &&$conn instanceof PDO) {
        $pdo =$conn;
    }
}

// Check karein ki user logged in hai ya nahi
if (!isset($_SESSION['user_id']) \vert{}\vert{} !isset($_SESSION['role_id'])) {
    header("Location: login.php");
    exit();
}

$current_role_id =$_SESSION['role_id'];
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';$module_key = isset($_GET['key']) ? trim($_GET['key']) : '';

if (empty($module_key)) {
    header("Location: admin_dashboard.php");
    exit();
}

// 1. Module & Authorization Check
$module_info = null;
$action_handlers = [];$error_msg = null;

try {
    $sql = "
        SELECT sm.id, sm.module_name, sm.module_key
        FROM system_modules sm
        INNER JOIN role_permissions rp ON sm.id = rp.module_id
        WHERE sm.module_key = ? AND rp.role_id = ?
        LIMIT 1
    ";

    if (isset($pdo) &&$pdo instanceof PDO) {
        $stmt =$pdo->prepare($sql);$stmt->execute([$module_key,$current_role_id]);
        $module_info =$stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (isset($conn) && $conn instanceof mysqli) {$stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $module_key, $current_role_id);$stmt->execute();
        $result =$stmt->get_result();
        $module_info =$result->fetch_assoc();
    }

    // Agar module assign na ho ya permission na ho
    if (!$module_info) {$error_msg = "Access Denied: Is module ($module_key) ka access aapke role ke paas nahi hai.";
    } else {
        // Fetch Action Handlers for this module
        $module_id = $module_info['id'];$handler_sql = "SELECT id, handler_name, handler_route FROM action_handlers WHERE module_id = ?";
        
        if (isset($pdo) && $pdo instanceof PDO) {$h_stmt = $pdo->prepare($handler_sql);
            $h_stmt->execute([$module_id]);
            $action_handlers =$h_stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif (isset($conn) &&$conn instanceof mysqli) {
            $h_stmt =$conn->prepare($handler_sql);$h_stmt->bind_param("i", $module_id);$h_stmt->execute();
            $h_res =$h_stmt->get_result();
            while ($row =$h_res->fetch_assoc()) {
                $action_handlers[] =$row;
            }
        }
    }

} catch (Exception $e) {$error_msg = "Database Error: " . $e->getMessage();
}

// 2. Fetch Module Specific Table Data (Example: users, roles, etc.)
$table_data = [];
if ($module_info && empty($error_msg)) {
    try {
        $allowed_tables = ['users', 'roles', 'system_modules', 'role_permissions', 'action_handlers'];
        if (in_array($module_key, $allowed_tables)) {$data_sql = "SELECT * FROM `$module_key` LIMIT 50";
            if (isset($pdo) && $pdo instanceof PDO) {$table_data = $pdo->query($data_sql)->fetchAll(PDO::FETCH_ASSOC);
            } elseif (isset($conn) && $conn instanceof mysqli) {$res = $conn->query($data_sql);
                if ($res) {
                    while ($r =$res->fetch_assoc()) {
                        $table_data[] =$r;
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Dynamic table query fails silently if table doesn't exist yet
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $module_info ? htmlspecialchars($module_info['module_name']) : 'Module View'; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background-color: #2c3e50; color: #fff; }
        .sidebar .nav-link { color: #ecf0f1; margin-bottom: 5px; border-radius: 4px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: #34495e; color: #1abc9c; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        
        <!-- Sidebar Navigation -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse p-3">
            <h4 class="text-center py-3 border-bottom border-secondary">
                <i class="fa-solid fa-user-shield me-2"></i>Admin Panel
            </h4>
            <ul class="nav flex-column mt-3">
                <li class="nav-item">
                    <a class="nav-link" href="admin_dashboard.php">
                        <i class="fa-solid fa-gauge me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link text-danger" href="logout.php">
                        <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content Area -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-4 border-bottom">
                <h1 class="h2">
                    <i class="fa-solid fa-cubes me-2 text-primary"></i>
                    <?php echo $module_info ? htmlspecialchars($module_info['module_name']) : 'Module Access'; ?>
                </h1>
                <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger shadow-sm" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php else: ?>

                <!-- Module Handlers / Actions -->
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">
                        <i class="fa-solid fa-sliders me-2 text-secondary"></i> Module Actions & Handlers
                    </div>
                    <div class="card-body">
                        <?php if (!empty($action_handlers)): ?>
                            <div class="d-flex gap-2 flex-wrap">
                                <?php foreach ($action_handlers as$handler): ?>
                                    <a href="<?php echo htmlspecialchars($handler['handler_route']); ?>" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-gear me-1"></i> <?php echo htmlspecialchars($handler['handler_name']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0 small"><i class="fa-solid fa-circle-info me-1"></i> Is module ke koi extra action handlers register nahi hain.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Module Data Table -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-table me-2 text-secondary"></i> Dynamic Data Records (<?php echo htmlspecialchars($module_key); ?>)</span>
                        <span class="badge bg-primary"><?php echo count($table_data); ?> Records</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($table_data)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0 align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <?php foreach (array_keys($table_data[0]) as$column): ?>
                                                <?php if (strtolower($column) !== 'password'): ?>
                                                    <th><?php echo htmlspecialchars(strtoupper($column)); ?></th>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($table_data as$row): ?>
                                            <tr>
                                                <?php foreach ($row as $col_name =>$val): ?>
                                                    <?php if (strtolower($col_name) !== 'password'): ?>
                                                        <td><?php echo htmlspecialchars($val ?? ''); ?></td>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">
                                <i class="fa-solid fa-folder-open fs-1 d-block mb-2 text-secondary"></i>
                                Is module ke table me abhi koi records maujood nahi hain.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php endif; ?>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>