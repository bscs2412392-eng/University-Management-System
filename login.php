<?php
// login.php
session_start();
include 'db.php';

$error = "";

// =========================================================================
// AUTO-CREATE TABLES (roles, users, system_modules, action_verbs, audit_logs)
// =========================================================================
$conn->query("
    CREATE TABLE IF NOT EXISTS `roles` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `role_name` VARCHAR(50) NOT NULL
    ) ENGINE=InnoDB;
");

$conn->query("
    CREATE TABLE IF NOT EXISTS `users` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(100) NOT NULL,
      `email` VARCHAR(100) NOT NULL UNIQUE,
      `password` VARCHAR(255) NOT NULL,
      `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
      `role_id` INT,
      FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;
");

$conn->query("
    CREATE TABLE IF NOT EXISTS `system_modules` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `module_name` VARCHAR(100) NOT NULL,
      `module_key` VARCHAR(100) NOT NULL UNIQUE
    ) ENGINE=InnoDB;
");

$conn->query("
    CREATE TABLE IF NOT EXISTS `action_verbs` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `verb_name` VARCHAR(50) NOT NULL
    ) ENGINE=InnoDB;
");

$conn->query("
    CREATE TABLE IF NOT EXISTS `role_permissions` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `role_id` INT,
      `module_id` INT,
      `action_verb_id` INT
    ) ENGINE=InnoDB;
");

$conn->query("
    CREATE TABLE IF NOT EXISTS `action_handlers` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `module_id` INT,
      `role_id` INT,
      `action_verb_id` INT,
      `handler_code` TEXT
    ) ENGINE=InnoDB;
");

// Audit Logs Table for tracking logins and activities
$conn->query("
    CREATE TABLE IF NOT EXISTS `audit_logs` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `action_performed` VARCHAR(255) NOT NULL,
      `module_affected` VARCHAR(100) NOT NULL,
      `ip_address` VARCHAR(45) NOT NULL,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
");

// Insert Default Roles if empty
$check_roles =$conn->query("SELECT COUNT(*) as count FROM roles");
if ($check_roles && $check_roles->fetch_assoc()['count'] == 0) {$conn->query("INSERT INTO roles (id, role_name) VALUES (1, 'Super Admin'), (2, 'Student'), (3, 'Teacher')");
}

// Insert / Sync Super Admin User
$super_admin_email = 'superadmin@university.edu';
$super_admin_pass  = 'admin123';

$check_super_admin =$conn->query("SELECT id FROM users WHERE email = '$super_admin_email'");
if ($check_super_admin && $check_super_admin->num_rows == 0) {$conn->query("INSERT INTO users (name, email, password, status, role_id) VALUES ('Super Admin', '$super_admin_email', '$super_admin_pass', 'Active', 1)");
} else {
    $conn->query("UPDATE users SET password = '$super_admin_pass', status = 'Active', role_id = 1 WHERE email = '$super_admin_email'");
}

// =========================================================================
// LOGIN PROCESS & AUDIT LOGGING
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    $query = "
        SELECT u.id, u.name, u.email, u.password, u.status, u.role_id, r.role_name 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.email = ? AND u.status = 'Active'
    ";

    $stmt = $conn->prepare($query);

    if ($stmt) {$stmt->bind_param("s", $email);$stmt->execute();
        $result =$stmt->get_result();

        if ($user =$result->fetch_assoc()) {
            if ($password ===$user['password'] || password_verify($password,$user['password'])) {
                $_SESSION['user_id']   =$user['id'];
                $_SESSION['user_name'] =$user['name'];
                $_SESSION['role_id']   =$user['role_id'];
                $_SESSION['role_name'] =$user['role_name'] ?? 'Super Admin';

                // --- AUDIT LOG ENTRY ON SUCCESSFUL LOGIN ---
                $log_user_id =$user['id'];
                $log_action  = "USER_LOGIN (" . strtoupper($user['role_name'] ?? 'USER') . ")";
                $log_module  = "Authentication";
                $log_ip      =$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

                $log_stmt =$conn->prepare("INSERT INTO audit_logs (user_id, action_performed, module_affected, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                if ($log_stmt) {$log_stmt->bind_param("isss", $log_user_id,$log_action, $log_module,$log_ip);
                    $log_stmt->execute();$log_stmt->close();
                }

                header("Location: admin_dashboard.php");
                exit();
            } else {
                $error = "Invalid Password! Please check your credentials.";
            }
        } else {
            $error = "Account not found or set to Inactive status!";
        }
        $stmt->close();
    } else {
        $error = "Database Connection Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Management System - Campus Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-100 flex flex-col justify-between min-h-screen">

    <!-- University Top Navigation Bar -->
    <header class="bg-indigo-950 text-white py-3 px-6 shadow-md flex justify-between items-center">
        <div class="flex items-center space-x-3">
            <div class="bg-indigo-600 p-2 rounded-lg text-white">
                <i class="fa-solid fa-graduation-cap text-2xl"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold tracking-wide leading-tight">UNIVERSITY PORTAL</h1>
                <p class="text-xs text-indigo-300">Academic & Administrative Management System</p>
            </div>
        </div>
        <div class="hidden md:flex items-center space-x-4 text-xs text-indigo-200">
            <span><i class="fa-solid fa-building-columns me-1"></i> Main Campus</span>
            <span>|</span>
            <span><i class="fa-solid fa-calendar-days me-1"></i> Academic Session 2026-2027</span>
        </div>
    </header>

    <!-- Main Content Body -->
    <main class="flex-grow flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 w-full max-w-md overflow-hidden my-6">
            
            <!-- Card Banner -->
            <div class="bg-indigo-900 text-white p-6 text-center relative">
                <div class="inline-block bg-indigo-800/60 p-3 rounded-full mb-2">
                    <i class="fa-solid fa-user-lock text-3xl text-indigo-200"></i>
                </div>
                <h2 class="text-xl font-bold">Portal Sign-In</h2>
                <p class="text-xs text-indigo-200 mt-1">Students, Faculty & Staff Centralized Login</p>
            </div>

            <!-- Login Form Wrapper -->
            <div class="p-6 md:p-8">
                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-3 rounded mb-4 text-xs flex items-center">
                        <i class="fa-solid fa-circle-exclamation text-base me-2"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            <i class="fa-solid fa-envelope me-1 text-indigo-600"></i> Institutional Email
                        </label>
                        <input type="email" name="email" required 
                               class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all outline-none" 
                               placeholder="user@university.edu" value="superadmin@university.edu">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            <i class="fa-solid fa-key me-1 text-indigo-600"></i> Password
                        </label>
                        <input type="password" name="password" required 
                               class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all outline-none" 
                               placeholder="••••••••" value="admin123">
                    </div>

                    <div class="flex items-center justify-between text-xs text-gray-500 pt-1">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 me-1.5"> Remember me
                        </label>
                        <a href="#" onclick="alert('Please contact University IT Helpdesk to reset your password.'); return false;" class="text-indigo-600 hover:underline">Forgot password?</a>
                    </div>

                    <button type="submit" 
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-lg font-semibold text-sm transition-colors shadow-md flex items-center justify-center space-x-2">
                        <span>Sign In to Portal</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </form>

                <!-- University Bulletin Notice -->
                <div class="mt-6 pt-4 border-t border-gray-100 text-center">
                    <p class="text-[11px] text-gray-500">
                        <i class="fa-solid fa-shield-halved text-indigo-600 me-1"></i> Authorized university access only. All login activities are monitored and logged into <code>audit_logs</code>.
                    </p>
                </div>
            </div>
        </div>
    </main>

    <!-- Portal Footer -->
    <footer class="bg-slate-900 text-gray-400 py-3 text-center text-xs border-t border-slate-800">
        <p>&copy; 2026 University Portal Systems. All Rights Reserved. | <a href="#" class="text-indigo-400 hover:underline">IT Helpdesk</a></p>
    </footer>

</body>
</html>