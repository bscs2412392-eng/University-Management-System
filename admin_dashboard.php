<?php
// admin_dashboard.php
require_once 'auth.php'; // Includes session_start(), db.php, and run_action_from_db()

// Auth check
checkAdmin();

global $pdo, $conn;

// Safe Database Object Connection Assignment
$db = null;
if (isset($pdo)) {
    if ($pdo !== null) {
        $db = $pdo;
    }
} else if (isset($conn)) {
    if ($conn !== null) {
        $db = $conn;
    }
}

$user_id = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
}

$current_role_id = 0;
if (isset($_SESSION['role_id'])) {
    $current_role_id = intval($_SESSION['role_id']);
}

// Safe Session Variable Fetching
$role_name = 'User';
if (isset($_SESSION['role_name'])) {
    $role_name = $_SESSION['role_name'];
}

$display_name = 'User';
if (isset($_SESSION['user_name'])) {
    $display_name = $_SESSION['user_name'];
} else if (isset($_SESSION['name'])) {
    $display_name = $_SESSION['name'];
}

// Dynamic Panel Header Title
$panel_title = htmlspecialchars($role_name) . ' Panel';

// GET parameters
$active_module_key = '';
if (isset($_GET['module_key'])) {
    $active_module_key = trim($_GET['module_key']);
}

$active_action_verb = '';
if (isset($_GET['verb'])) {
    $active_action_verb = trim($_GET['verb']);
}

$modules = array();
$current_module = null;
$module_actions = array();
$executed_output = '';
$error_msg = null;

// Automatic Audit Logger Function Check & Declaration
if (!function_exists('log_system_activity')) {
    function log_system_activity($db, $user_id, $action, $module, $ip) {
        if ($db === null) {
            return;
        }
        try {
            if ($db instanceof PDO) {
                $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action_performed, module_affected, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute(array($user_id, $action, $module, $ip));
            } else if ($db instanceof mysqli) {
                $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action_performed, module_affected, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("isss", $user_id, $action, $module, $ip);
                $stmt->execute();
            }
        } catch (Exception $e) {
            // Logging error silently
        }
    }
}

try {
    // 1. STRICTLY Fetch Authorized Modules for Logged-in Role
    $sql_modules = "
        SELECT DISTINCT sm.id, sm.module_name, sm.module_key
        FROM role_permissions rp
        INNER JOIN system_modules sm ON rp.module_id = sm.id
        WHERE rp.role_id = ?
        ORDER BY sm.id ASC
    ";

    if ($db instanceof PDO) {
        $stmt = $db->prepare($sql_modules);
        $stmt->execute(array($current_role_id));
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else if ($db instanceof mysqli) {
        $stmt = $db->prepare($sql_modules);
        $stmt->bind_param("i", $current_role_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $modules = $res->fetch_all(MYSQLI_ASSOC);
    }

    // 2. Active Module Verification & Actions Loading
    if ($active_module_key !== '') {
        foreach ($modules as $mod) {
            if ($mod['module_key'] === $active_module_key) {
                $current_module = $mod;
                break;
            }
        }

        if ($current_module !== null) {
            $mod_id = $current_module['id'];

            // STRICT Match: role_id, module_id, and action_verb_id in action_handlers
            $sql_actions = "
                SELECT DISTINCT ah.id, av.verb_name
                FROM action_handlers ah
                INNER JOIN action_verbs av ON ah.action_verb_id = av.id
                INNER JOIN role_permissions rp ON rp.module_id = ah.module_id 
                                               AND rp.action_verb_id = ah.action_verb_id 
                                               AND rp.role_id = ah.role_id
                WHERE ah.role_id = ? 
                  AND ah.module_id = ?
                ORDER BY ah.id ASC
            ";

            if ($db instanceof PDO) {
                $stmt_act = $db->prepare($sql_actions);
                $stmt_act->execute(array($current_role_id, $mod_id));
                $module_actions = $stmt_act->fetchAll(PDO::FETCH_ASSOC);
            } else if ($db instanceof mysqli) {
                $stmt_act = $db->prepare($sql_actions);
                $stmt_act->bind_param("ii", $current_role_id, $mod_id);
                $stmt_act->execute();
                $res_act = $stmt_act->get_result();
                $module_actions = $res_act->fetch_all(MYSQLI_ASSOC);
            }

            if ($active_action_verb === '') {
                if (!empty($module_actions)) {
                    $active_action_verb = $module_actions[0]['verb_name'];
                }
            }

            if ($active_action_verb !== '') {
                if (has_permission($active_module_key, $active_action_verb)) {
                    run_action_from_db($active_module_key, $active_action_verb, $executed_output);
                    
                    $user_ip = '127.0.0.1';
                    if (isset($_SERVER['REMOTE_ADDR'])) {
                        $user_ip = $_SERVER['REMOTE_ADDR'];
                    }
                    $action_name = "Executed " . strtoupper($active_action_verb);
                    log_system_activity($db, $user_id, $action_name, $current_module['module_name'], $user_ip);
                } else {
                    $executed_output = "<div class='alert alert-danger shadow-sm'>Access Denied: Is action ki permission aapko nahi di gayi hai.</div>";
                }
            }
        } else {
            $error_msg = "Access Denied: Is module ka access aapke role_permissions mein nahi hai.";
        }
    }

} catch (Exception $e) {
    $error_msg = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($panel_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html, body { height: 100%; background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        /* Fixed Height Sidebar with Internal Scrollbar */
        .sidebar { height: 100vh; position: sticky; top: 0; background-color: #1e293b; color: #fff; overflow-y: auto; }
        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        
        .sidebar .nav-link { color: #cbd5e1; margin-bottom: 4px; border-radius: 6px; transition: all 0.2s ease; font-size: 0.9rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: #334155; color: #38bdf8; font-weight: 600; }
        
        .module-card { transition: transform 0.2s ease, box-shadow 0.2s ease; border: none; }
        .module-card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
        .nav-tabs .nav-link.active { font-weight: bold; border-bottom: 3px solid #0d6efd; color: #0d6efd; }
    </style>
</head>
<body>

<!-- Mobile Responsive Toggle Bar -->
<div class="d-md-none bg-dark text-white p-3 d-flex justify-content-between align-items-center sticky-top">
    <h5 class="m-0 fw-bold"><i class="fa-solid fa-graduation-cap me-2"></i><?php echo htmlspecialchars($panel_title); ?></h5>
    <button class="btn btn-outline-light btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
        <i class="fa-solid fa-bars"></i>
    </button>
</div>

<div class="container-fluid">
    <div class="row">
        <!-- Fixed Desktop Sidebar with Full Modules List -->
        <nav class="col-md-3 col-lg-2 d-none d-md-block sidebar p-3">
            <h5 class="text-center py-3 border-bottom border-secondary text-info fw-bold">
                <i class="fa-solid fa-user-shield me-2"></i><?php echo htmlspecialchars($panel_title); ?>
            </h5>
            
            <ul class="nav flex-column mt-2">
                <li class="nav-item">
                    <a class="nav-link <?php if ($active_module_key === '') { echo 'active'; } ?>" href="admin_dashboard.php">
                        <i class="fa-solid fa-gauge me-2"></i> Dashboard
                    </a>
                </li>
                
                <?php if (!empty($modules)) { ?>
                    <li class="text-muted small fw-bold px-2 my-2 text-uppercase" style="letter-spacing: 0.5px;">All Modules</li>
                    <?php foreach ($modules as $module) { ?>
                        <li class="nav-item">
                            <a class="nav-link <?php if ($active_module_key === $module['module_key']) { echo 'active'; } ?>" 
                               href="admin_dashboard.php?module_key=<?php echo urlencode($module['module_key']); ?>">
                                <i class="fa-solid fa-folder me-2"></i>
                                <?php echo htmlspecialchars($module['module_name']); ?>
                            </a>
                        </li>
                    <?php } ?>
                <?php } ?>

                <li class="nav-item mt-4 border-top border-secondary pt-3">
                    <a class="nav-link text-danger" href="logout.php">
                        <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Mobile Offcanvas Sidebar -->
        <div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="mobileSidebar">
            <div class="offcanvas-header border-bottom border-secondary">
                <h5 class="offcanvas-title text-info fw-bold"><i class="fa-solid fa-user-shield me-2"></i><?php echo htmlspecialchars($panel_title); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white <?php if ($active_module_key === '') { echo 'fw-bold text-info'; } ?>" href="admin_dashboard.php">
                            <i class="fa-solid fa-gauge me-2"></i> Dashboard
                        </a>
                    </li>
                    <?php if (!empty($modules)) { ?>
                        <li class="text-muted small fw-bold px-2 my-2 text-uppercase">All Modules</li>
                        <?php foreach ($modules as $module) { ?>
                            <li class="nav-item mb-1">
                                <a class="nav-link text-white <?php if ($active_module_key === $module['module_key']) { echo 'fw-bold text-info'; } ?>" 
                                   href="admin_dashboard.php?module_key=<?php echo urlencode($module['module_key']); ?>">
                                    <i class="fa-solid fa-folder me-2"></i>
                                    <?php echo htmlspecialchars($module['module_name']); ?>
                                </a>
                            </li>
                        <?php } ?>
                    <?php } ?>
                    <li class="nav-item mt-4 border-top border-secondary pt-3">
                        <a class="nav-link text-danger" href="logout.php">
                            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content Area -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <!-- Header Welcome Banner -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-4 border-bottom">
                <h1 class="h2 text-dark fw-bold">
                    <?php 
                        if ($current_module !== null) {
                            echo htmlspecialchars($current_module['module_name']);
                        } else {
                            echo 'Dashboard Overview';
                        }
                    ?>
                </h1>
                <div class="badge bg-white text-dark shadow-sm p-2 fs-6 border">
                    <i class="fa-solid fa-circle-user me-2 text-primary"></i>
                    Welcome, <span class="text-primary fw-bold"><?php echo htmlspecialchars($display_name); ?></span>
                    <span class="badge bg-info text-dark ms-1"><?php echo htmlspecialchars($role_name); ?></span>
                </div>
            </div>

            <?php if ($error_msg !== null) { ?>
                <div class="alert alert-danger shadow-sm"><?php echo $error_msg; ?></div>
            <?php } ?>

            <?php if ($current_module !== null) { ?>
                <!-- Module Actions Execution View -->
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-header bg-white p-0">
                        <ul class="nav nav-tabs px-3 pt-2 flex-wrap">
                            <?php foreach ($module_actions as $index => $act) { ?>
                                <?php $is_act = ($active_action_verb === $act['verb_name']); ?>
                                <li class="nav-item">
                                    <a class="nav-link <?php if ($is_act) { echo 'active'; } ?>" 
                                       href="admin_dashboard.php?module_key=<?php echo urlencode($current_module['module_key']); ?>&verb=<?php echo urlencode($act['verb_name']); ?>">
                                        <i class="fa-solid fa-bolt me-1"></i> <?php echo htmlspecialchars(ucfirst($act['verb_name'])); ?>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                    <div class="card-body bg-white p-4">
                        <?php 
                            if ($executed_output !== '') {
                                echo $executed_output;
                            } else {
                                echo '<div class="text-center text-muted">No Code Output Available</div>';
                            }
                        ?>
                    </div>
                </div>
            <?php } else { ?>
                <!-- All Accessible Modules Displayed as Interactive Cards Grid -->
                <div class="mb-4">
                    <h5 class="fw-bold text-secondary mb-3">
                        <i class="fa-solid fa-cubes me-2 text-primary"></i>Your Accessible Modules
                    </h5>
                    
                    <?php if (!empty($modules)) { ?>
                        <div class="row g-4">
                            <?php foreach ($modules as $mod) { ?>
                                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                                    <div class="card h-100 module-card shadow-sm bg-white rounded-3 p-3">
                                        <div class="card-body d-flex flex-column justify-content-between p-2">
                                            <div>
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="bg-primary-subtle text-primary p-3 rounded-circle me-3">
                                                        <i class="fa-solid fa-folder-open fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="card-title fw-bold text-dark m-0">
                                                            <?php echo htmlspecialchars($mod['module_name']); ?>
                                                        </h6>
                                                        <small class="text-muted">Module ID: #<?php echo $mod['id']; ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <a href="admin_dashboard.php?module_key=<?php echo urlencode($mod['module_key']); ?>" 
                                               class="btn btn-outline-primary btn-sm w-100 fw-semibold mt-3">
                                                Open Module <i class="fa-solid fa-arrow-right ms-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="alert alert-warning shadow-sm">
                            <i class="fa-solid fa-exclamation-triangle me-2"></i>
                            Aapke account par koi module assign nahi kiya gaya hai. Please Administrator se rabta karein.
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>