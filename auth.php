<?php
// auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

function checkAdmin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Check module & verb access by active role_id
 */
function has_permission($module_key, $verb_name) {
    global $conn, $pdo;
    $role_id = $_SESSION['role_id'] ?? 0;

    if ($role_id == 1) {
        return true; // Super Admin full access
    }

    if (isset($conn) && $conn instanceof mysqli) {
        $stmt = $conn->prepare("
            SELECT rp.id 
            FROM role_permissions rp
            JOIN system_modules sm ON rp.module_id = sm.id
            JOIN action_verbs av ON rp.action_verb_id = av.id
            WHERE rp.role_id = ? AND sm.module_key = ? AND av.verb_name = ?
            LIMIT 1
        ");
        $stmt->bind_param("iss", $role_id, $module_key, $verb_name);
        $stmt->execute();
        $res = $stmt->get_result();
        $has_perm = ($res->num_rows > 0);
        $stmt->close();
        return $has_perm;
    } elseif (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("
            SELECT rp.id 
            FROM role_permissions rp
            JOIN system_modules sm ON rp.module_id = sm.id
            JOIN action_verbs av ON rp.action_verb_id = av.id
            WHERE rp.role_id = ? AND sm.module_key = ? AND av.verb_name = ?
            LIMIT 1
        ");
        $stmt->execute([$role_id, $module_key, $verb_name]);
        return ($stmt->fetch() !== false);
    }

    return false;
}

/**
 * Run dynamic handler code mapped to specific role_id
 */
function run_action_from_db($module_key, $action_verb, &$message) {
    global $conn, $pdo;
    $db = isset($conn) ? $conn : (isset($pdo) ? $pdo : null);
    $role_id = $_SESSION['role_id'] ?? 0;

    $handler_code = null;

    if (isset($conn) && $conn instanceof mysqli) {
        if ($role_id == 1) {
            $stmt = $conn->prepare("
                SELECT ah.handler_code 
                FROM action_handlers ah
                JOIN system_modules sm ON ah.module_id = sm.id
                JOIN action_verbs av ON ah.action_verb_id = av.id
                WHERE sm.module_key = ? AND av.verb_name = ?
                LIMIT 1
            ");
            $stmt->bind_param("ss", $module_key, $action_verb);
        } else {
            $stmt = $conn->prepare("
                SELECT ah.handler_code 
                FROM action_handlers ah
                JOIN role_permissions rp ON ah.module_id = rp.module_id AND ah.action_verb_id = rp.action_verb_id
                JOIN system_modules sm ON rp.module_id = sm.id
                JOIN action_verbs av ON rp.action_verb_id = av.id
                WHERE rp.role_id = ? AND sm.module_key = ? AND av.verb_name = ?
                LIMIT 1
            ");
            $stmt->bind_param("iss", $role_id, $module_key, $action_verb);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $handler_code = $row['handler_code'];
        }
        $stmt->close();

    } elseif (isset($pdo) && $pdo instanceof PDO) {
        if ($role_id == 1) {
            $stmt = $pdo->prepare("
                SELECT ah.handler_code 
                FROM action_handlers ah
                JOIN system_modules sm ON ah.module_id = sm.id
                JOIN action_verbs av ON ah.action_verb_id = av.id
                WHERE sm.module_key = ? AND av.verb_name = ?
                LIMIT 1
            ");
            $stmt->execute([$module_key, $action_verb]);
        } else {
            $stmt = $pdo->prepare("
                SELECT ah.handler_code 
                FROM action_handlers ah
                JOIN role_permissions rp ON ah.module_id = rp.module_id AND ah.action_verb_id = rp.action_verb_id
                JOIN system_modules sm ON rp.module_id = sm.id
                JOIN action_verbs av ON rp.action_verb_id = av.id
                WHERE rp.role_id = ? AND sm.module_key = ? AND av.verb_name = ?
                LIMIT 1
            ");
            $stmt->execute([$role_id, $module_key, $action_verb]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $handler_code = $row['handler_code'];
        }
    }

    if (!empty($handler_code)) {
        // Strip PHP opening and closing tags safely
        $code = trim($handler_code);
        if (strpos($code, '<?php') === 0) {
            $code = substr($code, 5);
        }
        if (substr($code, -2) === '?>') {
            $code = substr($code, 0, -2);
        }

        ob_start();
        try {
            eval($code);
        } catch (Throwable $e) {
            $message = "<div class='alert alert-danger mb-4'>
                            <strong>Syntax Error:</strong> " . htmlspecialchars($e->getMessage()) . "
                        </div>";
        }
        $output = ob_get_clean();
        
        if (empty($message)) {
            $message = $output;
        }
        return true;
    } else {
        $message = "<div class='alert alert-warning mb-4'>
                        Handler code not found for action '{$action_verb}' in '{$module_key}' for your role.
                    </div>";
        return false;
    }
}
?>