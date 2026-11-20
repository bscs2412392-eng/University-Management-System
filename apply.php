<?php
session_start();
require_once 'db.php'; // Aap ki original db.php ($conn) file

if (!isset($_SESSION['applicant_id']) || intval($_SESSION['applicant_id']) <= 0) {
    header("Location: applicant_login.php");
    exit;
}

$applicant_id   = intval($_SESSION['applicant_id']);
$applicant_name = $_SESSION['applicant_name'] ?? 'Applicant';

$msg = "";
$msg_type = "";

// Helper function to always fetch ONLY the logged-in applicant's exact row
function fetchLoggedApplicant($conn, $aid) {
    $q = $conn->prepare("SELECT app.*, a.full_name, a.email, a.phone, a.cnic_bform 
                         FROM student_applications app 
                         INNER JOIN applicants a ON app.applicant_id = a.id 
                         WHERE app.applicant_id = ? 
                         LIMIT 1");
    $q->bind_param("i", $aid);
    $q->execute();
    $res = $q->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $q->close();
    return $row;
}

// 1. Fetch Application & Applicant Record Safely (Strictly for logged-in $applicant_id)
$app = fetchLoggedApplicant($conn, $applicant_id);

if (!$app) {
    // Verify applicant exists in applicants table before creating application row
    $chk = $conn->prepare("SELECT id, full_name FROM applicants WHERE id = ? LIMIT 1");
    $chk->bind_param("i", $applicant_id);
    $chk->execute();
    $valid_acc = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$valid_acc) {
        session_destroy();
        header("Location: applicant_login.php");
        exit;
    }

    $init = $conn->prepare("INSERT INTO student_applications (applicant_id, current_step) VALUES (?, 1)");
    $init->bind_param("i", $applicant_id);
    $init->execute();
    $init->close();

    $app = fetchLoggedApplicant($conn, $applicant_id);
}

// Automatic Step Transition System based on Role Approvals (Strictly for $applicant_id)
$step_updated = false;

if ((int)$app['current_step'] === 6 && ($app['voucher_status'] ?? '') === 'Verified') {
    $app['current_step'] = 7;
    $step_updated = true;
}
if ((int)$app['current_step'] === 7 && ($app['entry_test_status'] ?? '') === 'Pass') {
    $app['current_step'] = 8;
    $step_updated = true;
}
if ((int)$app['current_step'] === 8 && ($app['interview_status'] ?? '') === 'Recommended') {
    $app['current_step'] = 9;
    $step_updated = true;
}

if ($step_updated) {
    $up_step = $conn->prepare("UPDATE student_applications SET current_step = ? WHERE applicant_id = ? LIMIT 1");
    $up_step->bind_param("ii", $app['current_step'], $applicant_id);
    $up_step->execute();
    $up_step->close();
}

// Fetch Selected Program Name
$program_name = !empty($app['selected_program']) ? $app['selected_program'] : "Not Selected";
if (!empty($app['program_id'])) {
    $p_stmt = $conn->prepare("SELECT program_name FROM degree_programs WHERE id = ? LIMIT 1");
    $p_stmt->bind_param("i", $app['program_id']);
    $p_stmt->execute();
    $p_res = $p_stmt->get_result()->fetch_assoc();
    $p_stmt->close();
    if ($p_res) {
        $program_name = $p_res['program_name'];
    }
}

$current_step  = (int)($app['current_step'] ?? 1);
$selected_type = isset($_GET['type']) ? $_GET['type'] : (!empty($app['student_type']) ? $app['student_type'] : 'Undergraduate');

$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Fetch Dynamic Active Programs from DB
$db_programs = [];
$prog_stmt = $conn->prepare("SELECT id, program_name FROM degree_programs WHERE degree_level = ? AND status = 'active'");
if ($prog_stmt) {
    $prog_stmt->bind_param("s", $selected_type);
    $prog_stmt->execute();
    $db_programs = $prog_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $prog_stmt->close();
}

// -------------------------------------------------------------
// Form Handlers (Every UPDATE strictly targets WHERE applicant_id = $applicant_id)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_step = intval($_POST['step_number']);

    try {
        if ($submitted_step === 1) {
            $student_type = isset($_POST['student_type']) ? trim($_POST['student_type']) : $selected_type;
            $dob          = isset($_POST['dob']) ? $_POST['dob'] : '';
            $gender       = isset($_POST['gender']) ? $_POST['gender'] : 'Male';
            $father       = isset($_POST['father_name']) ? trim($_POST['father_name']) : '';
            $phone        = isset($_POST['guardian_phone']) ? trim($_POST['guardian_phone']) : '';

            if ($student_type === 'International') {
                $passport_no = strtoupper(trim($_POST['passport_number'] ?? ''));
                $country     = trim($_POST['country_of_origin'] ?? '');
                $visa_noc    = !empty($app['visa_noc_status']) ? $app['visa_noc_status'] : 'Pending';
            } else {
                $passport_no = null;
                $country     = 'Pakistan';
                $visa_noc    = null;
            }

            $up = $conn->prepare("UPDATE student_applications 
                                  SET student_type=?, dob=?, gender=?, father_name=?, guardian_phone=?, 
                                      passport_number=?, country_of_origin=?, visa_noc_status=?,
                                      current_step = GREATEST(current_step, 2) 
                                  WHERE applicant_id = ? LIMIT 1");
            $up->bind_param("ssssssssi", $student_type, $dob, $gender, $father, $phone, $passport_no, $country, $visa_noc, $applicant_id);
            $up->execute();
            $up->close();
            $selected_type = $student_type;
            $msg = "Step 1 (Basic Info) Saved Successfully for Applicant #{$applicant_id}!"; $msg_type = "success";
        }

        elseif ($submitted_step === 2) {
            $address  = isset($_POST['address']) ? trim($_POST['address']) : '';
            $city     = isset($_POST['city']) ? trim($_POST['city']) : '';
            $province = isset($_POST['province']) ? trim($_POST['province']) : '';

            $up = $conn->prepare("UPDATE student_applications SET address=?, city=?, province=?, current_step = GREATEST(current_step, 3) WHERE applicant_id = ? LIMIT 1");
            $up->bind_param("sssi", $address, $city, $province, $applicant_id);
            $up->execute();
            $up->close();
            $msg = "Step 2 (Home Address) Saved Successfully!"; $msg_type = "success";
        }

        elseif ($submitted_step === 3) {
            $cat          = !empty($app['student_type']) ? $app['student_type'] : $selected_type;
            $matric       = floatval($_POST['matric_marks'] ?? 0);
            $matric_total = floatval($_POST['matric_total'] ?? 1100);
            $inter        = floatval($_POST['inter_marks'] ?? 0);
            $inter_total  = floatval($_POST['inter_total'] ?? 1100);
            $board        = isset($_POST['inter_board']) ? trim($_POST['inter_board']) : '';

            if ($cat === 'Postgraduate' || $cat === 'PhD') {
                $ug_stream      = null;
                $bachelor_cgpa  = floatval($_POST['bachelor_cgpa'] ?? 0);
                $gat_gre_score  = ($_POST['gat_gre_score'] !== '' && $_POST['gat_gre_score'] !== null) ? floatval($_POST['gat_gre_score']) : null;
                $research_area  = trim($_POST['grad_research_area'] ?? '');
            } else {
                $ug_stream      = trim($_POST['ug_stream_group'] ?? '');
                $bachelor_cgpa  = null;
                $gat_gre_score  = null;
                $research_area  = null;
            }

            $up = $conn->prepare("UPDATE student_applications 
                                  SET matric_marks=?, matric_total=?, inter_marks=?, inter_total=?, inter_board=?, 
                                      ug_stream_group=?, bachelor_cgpa=?, gat_gre_score=?, grad_research_area=?,
                                      current_step = GREATEST(current_step, 4) 
                                  WHERE applicant_id = ? LIMIT 1");
            $up->bind_param("ddddssddsi", $matric, $matric_total, $inter, $inter_total, $board, $ug_stream, $bachelor_cgpa, $gat_gre_score, $research_area, $applicant_id);
            $up->execute();
            $up->close();
            $msg = "Step 3 (Education Info) Saved Successfully!"; $msg_type = "success";
        }

        elseif ($submitted_step === 4) {
            $program_id = isset($_POST['program_id']) ? intval($_POST['program_id']) : 0;
            $confirm    = isset($_POST['terms_confirmed']) ? 1 : 0;

            if ($confirm && $program_id > 0) {
                $sel_prog_name = '';
                $ps = $conn->prepare("SELECT program_name FROM degree_programs WHERE id = ? LIMIT 1");
                if ($ps) {
                    $ps->bind_param("i", $program_id);
                    $ps->execute();
                    $pr = $ps->get_result()->fetch_assoc();
                    $ps->close();
                    if ($pr) { $sel_prog_name = $pr['program_name']; }
                }

                $up = $conn->prepare("UPDATE student_applications SET program_id=?, selected_program=?, terms_confirmed=?, current_step = GREATEST(current_step, 5) WHERE applicant_id = ? LIMIT 1");
                $up->bind_param("isii", $program_id, $sel_prog_name, $confirm, $applicant_id);
                $up->execute();
                $up->close();
                $program_name = $sel_prog_name ?: $program_name;
                $msg = "Step 4 (Program Selection) Saved Successfully!"; $msg_type = "success";
            } else {
                $msg = "Please select a program and confirm terms."; $msg_type = "warning";
            }
        }

        elseif ($submitted_step === 5) {
            $cnic_file   = !empty($_FILES['cnic_doc']['name']) ? $upload_dir . time() . '_' . $applicant_id . '_cnic_' . basename($_FILES['cnic_doc']['name']) : ($app['cnic_doc'] ?? '');
            $matric_file = !empty($_FILES['matric_doc']['name']) ? $upload_dir . time() . '_' . $applicant_id . '_matric_' . basename($_FILES['matric_doc']['name']) : ($app['matric_doc'] ?? '');
            $inter_file  = !empty($_FILES['inter_doc']['name']) ? $upload_dir . time() . '_' . $applicant_id . '_inter_' . basename($_FILES['inter_doc']['name']) : ($app['inter_doc'] ?? '');

            if (!empty($_FILES['cnic_doc']['tmp_name'])) move_uploaded_file($_FILES['cnic_doc']['tmp_name'], $cnic_file);
            if (!empty($_FILES['matric_doc']['tmp_name'])) move_uploaded_file($_FILES['matric_doc']['tmp_name'], $matric_file);
            if (!empty($_FILES['inter_doc']['tmp_name'])) move_uploaded_file($_FILES['inter_doc']['tmp_name'], $inter_file);

            $up = $conn->prepare("UPDATE student_applications SET cnic_doc=?, matric_doc=?, inter_doc=?, doc_audit_status = COALESCE(NULLIF(doc_audit_status,''), 'Pending'), current_step = GREATEST(current_step, 6) WHERE applicant_id = ? LIMIT 1");
            $up->bind_param("sssi", $cnic_file, $matric_file, $inter_file, $applicant_id);
            $up->execute();
            $up->close();
            $msg = "Step 5 (Documents Uploaded) Successfully!"; $msg_type = "success";
        }

        elseif ($submitted_step === 6) {
            if (!empty($_FILES['voucher_receipt']['name'])) {
                $receipt = $upload_dir . time() . '_' . $applicant_id . '_receipt_' . basename($_FILES['voucher_receipt']['name']);
                move_uploaded_file($_FILES['voucher_receipt']['tmp_name'], $receipt);

                $up = $conn->prepare("UPDATE student_applications SET voucher_receipt=?, voucher_status='Paid' WHERE applicant_id = ? LIMIT 1");
                $up->bind_param("si", $receipt, $applicant_id);
                $up->execute();
                $up->close();
                $msg = "Voucher Receipt Uploaded! Status set to Paid."; $msg_type = "success";
            }
        }

        // STEP 9: AUTOMATIC TABLE CREATION & DATA MIGRATION UPON ACCEPTANCE
        elseif ($submitted_step === 9) {
            $student_type = !empty($app['student_type']) ? $app['student_type'] : 'Undergraduate';
            $roll_number  = !empty($app['roll_number']) 
                            ? $app['roll_number'] 
                            : strtoupper(substr($student_type, 0, 2)) . '-' . date('Y') . '-' . str_pad($applicant_id, 4, '0', STR_PAD_LEFT);
            $section_def  = !empty($app['section_assigned']) ? $app['section_assigned'] : 'Section-A';

            $up_offer = $conn->prepare("UPDATE student_applications 
                                        SET offer_status = 'Accepted', 
                                            roll_number = COALESCE(NULLIF(roll_number,''), ?),
                                            section_assigned = COALESCE(NULLIF(section_assigned,''), ?),
                                            enrollment_status = 'Enrolled'
                                        WHERE applicant_id = ? LIMIT 1");
            $up_offer->bind_param("ssi", $roll_number, $section_def, $applicant_id);
            $up_offer->execute();
            $up_offer->close();

            $target_table = 'undergraduate_students';
            if ($student_type === 'Postgraduate') $target_table = 'postgraduate_students';
            elseif ($student_type === 'PhD') $target_table = 'phd_students';
            elseif ($student_type === 'International') $target_table = 'international_students';

            $create_table_sql = "CREATE TABLE IF NOT EXISTS `{$target_table}` (
              `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
              `applicant_id` INT(11) UNSIGNED NOT NULL UNIQUE,
              `roll_number` VARCHAR(50) DEFAULT NULL,
              `section_assigned` VARCHAR(50) DEFAULT NULL,
              `full_name` VARCHAR(100) NOT NULL,
              `email` VARCHAR(100) NOT NULL,
              `phone` VARCHAR(20) NOT NULL,
              `cnic_bform` VARCHAR(30) NOT NULL,
              `dob` DATE DEFAULT NULL,
              `gender` VARCHAR(20) DEFAULT NULL,
              `father_name` VARCHAR(100) DEFAULT NULL,
              `guardian_phone` VARCHAR(20) DEFAULT NULL,
              `address` TEXT DEFAULT NULL,
              `city` VARCHAR(50) DEFAULT NULL,
              `province` VARCHAR(50) DEFAULT NULL,
              `matric_marks` DECIMAL(6,2) DEFAULT NULL,
              `inter_marks` DECIMAL(6,2) DEFAULT NULL,
              `inter_board` VARCHAR(100) DEFAULT NULL,
              `program_id` INT(11) UNSIGNED DEFAULT NULL,
              `selected_program` VARCHAR(150) DEFAULT NULL,
              `ug_stream_group` VARCHAR(100) DEFAULT NULL,
              `ug_shift_quota` VARCHAR(100) DEFAULT NULL,
              `ug_deficiency_courses` VARCHAR(255) DEFAULT NULL,
              `bachelor_cgpa` DECIMAL(4,2) DEFAULT NULL,
              `gat_gre_score` DECIMAL(6,2) DEFAULT NULL,
              `grad_supervisor_name` VARCHAR(150) DEFAULT NULL,
              `grad_research_area` VARCHAR(200) DEFAULT NULL,
              `grad_fellowship_tier` VARCHAR(100) DEFAULT NULL,
              `passport_number` VARCHAR(50) DEFAULT NULL,
              `country_of_origin` VARCHAR(100) DEFAULT NULL,
              `intl_hostel_room` VARCHAR(100) DEFAULT NULL,
              `cnic_doc` VARCHAR(255) DEFAULT NULL,
              `matric_doc` VARCHAR(255) DEFAULT NULL,
              `inter_doc` VARCHAR(255) DEFAULT NULL,
              `enrolled_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $conn->query($create_table_sql);

            $insert_student = $conn->prepare("INSERT INTO {$target_table} 
                (applicant_id, roll_number, section_assigned, full_name, email, phone, cnic_bform, dob, gender, father_name, guardian_phone, address, city, province, matric_marks, inter_marks, inter_board, program_id, selected_program, ug_stream_group, ug_shift_quota, ug_deficiency_courses, bachelor_cgpa, gat_gre_score, grad_supervisor_name, grad_research_area, grad_fellowship_tier, passport_number, country_of_origin, intl_hostel_room, cnic_doc, matric_doc, inter_doc) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE roll_number = VALUES(roll_number), section_assigned = VALUES(section_assigned)");

            $insert_student->bind_param("isssssssssssssddsissssddsssssssss", 
                $applicant_id, $roll_number, $section_def, $app['full_name'], $app['email'], $app['phone'], $app['cnic_bform'],
                $app['dob'], $app['gender'], $app['father_name'], $app['guardian_phone'], $app['address'], $app['city'],
                $app['province'], $app['matric_marks'], $app['inter_marks'], $app['inter_board'], $app['program_id'],
                $app['selected_program'], $app['ug_stream_group'], $app['ug_shift_quota'], $app['ug_deficiency_courses'],
                $app['bachelor_cgpa'], $app['gat_gre_score'], $app['grad_supervisor_name'], $app['grad_research_area'],
                $app['grad_fellowship_tier'], $app['passport_number'], $app['country_of_origin'], $app['intl_hostel_room'],
                $app['cnic_doc'], $app['matric_doc'], $app['inter_doc']
            );
            $insert_student->execute();
            $insert_student->close();

            $create_roles_sql = "CREATE TABLE IF NOT EXISTS `roles` (
              `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
              `role_name` VARCHAR(50) NOT NULL UNIQUE,
              `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
              `description` TEXT DEFAULT NULL,
              `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $conn->query($create_roles_sql);

            $role_name_str = $student_type . ' Student';
            $role_desc     = 'System generated role for ' . $student_type . ' category students';

            $check_role = $conn->prepare("SELECT id FROM roles WHERE role_name = ? LIMIT 1");
            $check_role->bind_param("s", $role_name_str);
            $check_role->execute();
            $role_res = $check_role->get_result()->fetch_assoc();
            $check_role->close();

            if ($role_res) {
                $role_id = $role_res['id'];
            } else {
                $add_role = $conn->prepare("INSERT INTO roles (role_name, status, description) VALUES (?, 'Active', ?)");
                $add_role->bind_param("ss", $role_name_str, $role_desc);
                $add_role->execute();
                $role_id = $conn->insert_id;
                $add_role->close();
            }

            $create_users_sql = "CREATE TABLE IF NOT EXISTS `users` (
              `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(100) NOT NULL,
              `email` VARCHAR(100) NOT NULL UNIQUE,
              `password` VARCHAR(255) NOT NULL,
              `role_id` INT(11) UNSIGNED NOT NULL,
              `role` VARCHAR(50) NOT NULL,
              `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
              `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $conn->query($create_users_sql);

            $default_pass = password_hash('123456', PASSWORD_BCRYPT);
            
            $check_user = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $check_user->bind_param("s", $app['email']);
            $check_user->execute();
            $u_exists = $check_user->get_result()->num_rows > 0;
            $check_user->close();
            
            if (!$u_exists) {
                $add_user = $conn->prepare("INSERT INTO users (name, email, password, role_id, role, status) VALUES (?, ?, ?, ?, ?, 'Active')");
                $add_user->bind_param("sssis", $app['full_name'], $app['email'], $default_pass, $role_id, $role_name_str);
                $add_user->execute();
                $add_user->close();
            }

            $msg = "Success! Offer Accepted (Roll#: {$roll_number}). Table '{$target_table}' created dynamically, data migrated & User Account registered (Password: 123456).";
            $msg_type = "success";
        }
    } catch (mysqli_sql_exception $e) {
        $msg = "Database Action Error: " . $e->getMessage();
        $msg_type = "danger";
    }

    // Re-fetch ONLY the logged-in student's fresh row
    $app = fetchLoggedApplicant($conn, $applicant_id);
    $current_step = (int)($app['current_step'] ?? 1);
    if (!empty($app['student_type'])) {
        $selected_type = $app['student_type'];
    }
}

$active_cat = !empty($app['student_type']) ? $app['student_type'] : $selected_type;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>University Admission Portal - 9 Step Wizard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .step-wizard-list { display: flex; padding: 0; list-style: none; overflow-x: auto; }
        .step-wizard-item { flex: 1; min-width: 110px; text-align: center; position: relative; }
        .step-wizard-item .step-number { width: 36px; height: 36px; border-radius: 50%; background: #ccc; color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto 5px; font-weight: bold; }
        .step-wizard-item.active .step-number { background: #0d6efd; }
        .step-wizard-item.completed .step-number { background: #198754; }
        .step-wizard-item.locked .step-number { background: #6c757d; opacity: 0.5; }
        .step-title { font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .step-card { display: none; }
        .step-card.active-step { display: block; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">NUTS Admission Portal</a>
        <div class="d-flex align-items-center text-white">
            <span class="badge bg-warning text-dark me-2">Applicant ID: #<?= $applicant_id; ?></span>
            <span class="badge bg-info text-dark me-3"><?= htmlspecialchars($active_cat); ?> Track</span>
            <span class="me-3"><i class="fa-solid fa-user me-1"></i> Welcome, <strong><?= htmlspecialchars($app['full_name'] ?? $applicant_name); ?></strong></span>
            <a href="applicant_login.php" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>
</nav>

<div class="container py-4">

    <!-- 9 Step Header -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <ul class="step-wizard-list mb-0">
                <?php
                $steps_titles = [
                    1 => "Basic Info", 2 => "Home Address", 3 => "Education", 
                    4 => "Program", 5 => "Documents", 6 => "Fee Voucher", 
                    7 => "Entry Test", 8 => "Interview", 9 => "Offer Letter"
                ];

                for ($i = 1; $i <= 9; $i++) {
                    $class = "locked";
                    if ($i < $current_step) $class = "completed";
                    elseif ($i == $current_step) $class = "active";
                    
                    echo '<li class="step-wizard-item '.$class.'">
                            <div class="step-number">'.($class == "completed" ? '<i class="fa-solid fa-check"></i>' : ($class == "locked" ? '<i class="fa-solid fa-lock"></i>' : $i)).'</div>
                            <div class="step-title">'.$steps_titles[$i].'</div>
                          </li>';
                }
                ?>
            </ul>
        </div>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= $msg_type; ?> alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-info me-2"></i><?= htmlspecialchars($msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- STEP 1: Basic Information -->
    <div class="card shadow-sm border-0 step-card <?= $current_step == 1 ? 'active-step' : ''; ?>">
        <div class="card-header bg-primary text-white fw-bold"><i class="fa-solid fa-user me-2"></i>Step 1: Student Category & Basic Details</div>
        <div class="card-body p-4">
            <form method="POST">
                <input type="hidden" name="step_number" value="1">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Select Student Type / Category</label>
                        <select name="student_type" class="form-select bg-warning-subtle fw-bold" onchange="window.location.href='apply.php?type='+this.value">
                            <option value="Undergraduate" <?= $selected_type=='Undergraduate'?'selected':''; ?>>Undergraduate Candidate (BS / BBA)</option>
                            <option value="Postgraduate" <?= $selected_type=='Postgraduate'?'selected':''; ?>>Postgraduate Candidate (MS / MPhil)</option>
                            <option value="PhD" <?= $selected_type=='PhD'?'selected':''; ?>>PhD Candidate (Doctoral)</option>
                            <option value="International" <?= $selected_type=='International'?'selected':''; ?>>International / Overseas Candidate</option>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Date of Birth</label><input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($app['dob'] ?? ''); ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Gender</label>
                        <select name="gender" class="form-select" required>
                            <option value="Male" <?= ($app['gender']??'')=='Male'?'selected':''; ?>>Male</option>
                            <option value="Female" <?= ($app['gender']??'')=='Female'?'selected':''; ?>>Female</option>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Father's Name</label><input type="text" name="father_name" class="form-control" value="<?= htmlspecialchars($app['father_name'] ?? ''); ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Guardian Phone</label><input type="text" name="guardian_phone" class="form-control" value="<?= htmlspecialchars($app['guardian_phone'] ?? ''); ?>" required></div>

                    <?php if ($selected_type === 'International'): ?>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-success">Valid Passport Number</label>
                            <input type="text" name="passport_number" class="form-control" placeholder="e.g. A12345678" value="<?= htmlspecialchars($app['passport_number'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-success">Country of Origin / Nationality</label>
                            <input type="text" name="country_of_origin" class="form-control" placeholder="e.g. Saudi Arabia / UAE / UK" value="<?= htmlspecialchars($app['country_of_origin'] ?? ''); ?>" required>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primary mt-3 fw-bold">Save & Continue to Step 2 &rarr;</button>
            </form>
        </div>
    </div>

    <!-- STEP 2: Address Information -->
    <div class="card shadow-sm border-0 step-card <?= $current_step == 2 ? 'active-step' : ''; ?>">
        <div class="card-header bg-primary text-white fw-bold"><i class="fa-solid fa-house me-2"></i>Step 2: Address Information</div>
        <div class="card-body p-4">
            <form method="POST">
                <input type="hidden" name="step_number" value="2">
                <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" required><?= htmlspecialchars($app['address'] ?? ''); ?></textarea></div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">City</label><input type="text" name="city" class="form-control" value="<?= htmlspecialchars($app['city'] ?? ''); ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Province / State</label><input type="text" name="province" class="form-control" value="<?= htmlspecialchars($app['province'] ?? ''); ?>" required></div>
                </div>
                <button type="submit" class="btn btn-primary mt-3 fw-bold">Save & Continue to Step 3 &rarr;</button>
            </form>
        </div>
    </div>

    <!-- STEP 3: Education History -->
    <div class="card shadow-sm border-0 step-card <?= $current_step == 3 ? 'active-step' : ''; ?>">
        <div class="card-header bg-primary text-white fw-bold"><i class="fa-solid fa-graduation-cap me-2"></i>Step 3: Academic & Qualification History (<?= htmlspecialchars($active_cat); ?>)</div>
        <div class="card-body p-4">
            <form method="POST">
                <input type="hidden" name="step_number" value="3">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Matric / O-Level Obtained</label>
                        <input type="number" step="0.01" name="matric_marks" class="form-control" value="<?= htmlspecialchars($app['matric_marks'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Matric Total Marks</label>
                        <input type="number" step="0.01" name="matric_total" class="form-control" value="<?= htmlspecialchars($app['matric_total'] ?? '1100'); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Inter / A-Level Obtained</label>
                        <input type="number" step="0.01" name="inter_marks" class="form-control" value="<?= htmlspecialchars($app['inter_marks'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Inter Total Marks</label>
                        <input type="number" step="0.01" name="inter_total" class="form-control" value="<?= htmlspecialchars($app['inter_total'] ?? '1100'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Intermediate / Qualification Board</label>
                        <input type="text" name="inter_board" class="form-control" placeholder="e.g. BIEK Karachi / FBISE / Cambridge" value="<?= htmlspecialchars($app['inter_board'] ?? ''); ?>" required>
                    </div>

                    <?php if ($active_cat === 'Postgraduate' || $active_cat === 'PhD'): ?>
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-primary">16-Year Bachelor CGPA (Out of 4.00)</label>
                            <input type="number" step="0.01" min="0" max="4.00" name="bachelor_cgpa" class="form-control" value="<?= htmlspecialchars($app['bachelor_cgpa'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-primary">NTS GAT / GRE Score (If Taken)</label>
                            <input type="number" step="0.5" min="0" max="100" name="gat_gre_score" class="form-control" value="<?= htmlspecialchars($app['gat_gre_score'] ?? ''); ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-primary">Proposed Research Area / Thesis Interest</label>
                            <input type="text" name="grad_research_area" class="form-control" placeholder="e.g. Artificial Intelligence, Cyber Security, Data Science" value="<?= htmlspecialchars($app['grad_research_area'] ?? ''); ?>" required>
                        </div>
                    <?php else: ?>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-primary">HSSC / Intermediate Subject Stream</label>
                            <select name="ug_stream_group" class="form-select" required>
                                <option value="Pre-Engineering" <?= ($app['ug_stream_group']??'')=='Pre-Engineering'?'selected':''; ?>>FSc Pre-Engineering</option>
                                <option value="ICS (Computer Science)" <?= ($app['ug_stream_group']??'')=='ICS (Computer Science)'?'selected':''; ?>>ICS (Computer Science)</option>
                                <option value="Pre-Medical" <?= ($app['ug_stream_group']??'')=='Pre-Medical'?'selected':''; ?>>FSc Pre-Medical</option>
                                <option value="Cambridge A-Levels" <?= ($app['ug_stream_group']??'')=='Cambridge A-Levels'?'selected':''; ?>>Cambridge A-Levels</option>
                                <option value="I.Com / FA / DAE" <?= ($app['ug_stream_group']??'')=='I.Com / FA / DAE'?'selected':''; ?>>I.Com / FA / DAE Diploma</option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primary mt-3 fw-bold">Save & Continue to Step 4 &rarr;</button>
            </form>
        </div>
    </div>

    <!-- STEP 4: Program Selection -->
    <div class="card shadow-sm border-0 step-card <?= $current_step == 4 ? 'active-step' : ''; ?>">
        <div class="card-header bg-primary text-white fw-bold"><i class="fa-solid fa-list-check me-2"></i>Step 4: Select Degree Program (Dynamic Database)</div>
        <div class="card-body p-4">
            <form method="POST">
                <input type="hidden" name="step_number" value="4">
                <div class="mb-3">
                    <label class="form-label fw-bold">Available <?= htmlspecialchars($selected_type); ?> Programs</label>
                    <select name="program_id" class="form-select" required>
                        <option value="">-- Select Program from Database --</option>
                        <?php foreach ($db_programs as $prog): ?>
                            <option value="<?= $prog['id']; ?>" <?= isset($app['program_id']) && $app['program_id'] == $prog['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($prog['program_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="terms_confirmed" value="1" id="confirmCheck" <?= !empty($app['terms_confirmed']) ? 'checked':''; ?> required>
                    <label class="form-check-label" for="confirmCheck">I confirm that all details provided are correct.</label>
                </div>
                <button type="submit" class="btn btn-primary fw-bold">Save & Continue to Step 5 &rarr;</button>
            </form>
        </div>
    </div>

    <!-- STEP 5: Upload Documents -->
    <div class="card shadow-sm border-0 step-card <?= $current_step == 5 ? 'active-step' : ''; ?>">
        <div class="card-header bg-primary text-white fw-bold"><i class="fa-solid fa-cloud-arrow-up me-2"></i>Step 5: Upload Scanned Documents</div>
        <div class="card-body p-4">
            <?php if (!empty($app['doc_audit_status']) && $app['doc_audit_status'] !== 'Pending'): ?>
                <div class="alert alert-info mb-3">
                    <strong>Document Audit Status (Admissions Evaluator):</strong> <?= htmlspecialchars($app['doc_audit_status']); ?>
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="step_number" value="5">
                <div class="mb-3"><label class="form-label">CNIC / B-Form <?= $active_cat==='International'?'/ Passport Copy':''; ?></label><input type="file" name="cnic_doc" class="form-control" <?= empty($app['cnic_doc'])?'required':''; ?>></div>
                <div class="mb-3"><label class="form-label">Matric / O-Level Marksheet</label><input type="file" name="matric_doc" class="form-control" <?= empty($app['matric_doc'])?'required':''; ?>></div>
                <div class="mb-3"><label class="form-label">Intermediate / <?= ($active_cat==='Postgraduate'||$active_cat==='PhD')?'Bachelor Transcript':'A-Level Marksheet'; ?></label><input type="file" name="inter_doc" class="form-control" <?= empty($app['inter_doc'])?'required':''; ?>></div>
                <button type="submit" class="btn btn-primary fw-bold">Upload & Unlock Fee Voucher &rarr;</button>
            </form>
        </div>
    </div>

    <!-- STEP 6: Fee Voucher -->
    <div class="card shadow-sm border-0 step-card <?= $current_step == 6 ? 'active-step' : ''; ?>">
        <div class="card-header bg-warning text-dark fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Step 6: Entry Test Fee Voucher</div>
        <div class="card-body p-4 text-center">
            <h5>Download & Pay Fee Voucher</h5>
            <p class="text-muted">Download entry test challan PDF, pay PKR 2,000 in bank, and upload receipt image.</p>
            
            <a href="generate_voucher.php" target="_blank" class="btn btn-outline-dark fw-bold mb-4">
                <i class="fa-solid fa-file-pdf text-danger me-2"></i> Download Fee Voucher PDF
            </a>

            <?php if (($app['voucher_status']??'') == 'Verified'): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check me-2"></i>Fee Verified by <strong>Enrollment Management Coordinator</strong>! Automatically moving to Step 7...
                </div>
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data" class="col-md-6 mx-auto text-start bg-white p-3 border rounded mb-3">
                    <input type="hidden" name="step_number" value="6">
                    <label class="form-label fw-bold">Upload Receipt</label>
                    <input type="file" name="voucher_receipt" class="form-control mb-3" required>
                    <button type="submit" class="btn btn-success w-100 fw-bold">Upload Receipt</button>
                </form>
                <div class="badge bg-info text-dark p-2 fs-6">
                    Current Status: <strong><?= htmlspecialchars($app['voucher_status']??'Pending'); ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- STEP 7: Entry Test Schedule & Specialized Officer Desk Review -->
    <div class="card shadow-sm border-0 step-card <?= $current_step == 7 ? 'active-step' : ''; ?>">
        <div class="card-header bg-info text-white fw-bold"><i class="fa-solid fa-id-card me-2"></i>Step 7: Entry Test Schedule & Specialized Desk Review</div>
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-6 border-end">
                    <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-calendar-check me-2"></i>Entry Test Slip & Schedule</h5>
                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item"><strong>Assigned Officer:</strong> <?= htmlspecialchars($active_cat); ?> Admissions Officer</li>
                        <li class="list-group-item"><strong>Test Date:</strong> <?= htmlspecialchars($app['entry_test_date'] ?: 'To Be Scheduled'); ?></li>
                        <li class="list-group-item"><strong>Test Time:</strong> <?= htmlspecialchars($app['entry_test_time'] ?: 'To Be Scheduled'); ?></li>
                        <li class="list-group-item"><strong>Test Venue / Location:</strong> <?= htmlspecialchars($app['entry_test_venue'] ?: 'Main Campus Test Hall B'); ?></li>
                    </ul>

                    <?php if ($active_cat === 'Undergraduate'): ?>
                        <div class="p-3 bg-light border rounded small">
                            <strong>UG Desk Status:</strong> Quota/Shift: <span class="text-primary fw-bold"><?= htmlspecialchars($app['ug_shift_quota'] ?? 'Open Merit (Morning Shift)'); ?></span>
                            <?php if (!empty($app['ug_deficiency_courses'])): ?>
                                <br>Bridge Courses: <span class="text-danger fw-bold"><?= htmlspecialchars($app['ug_deficiency_courses']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($active_cat === 'Postgraduate' || $active_cat === 'PhD'): ?>
                        <div class="p-3 bg-light border rounded small">
                            <strong>Graduate DRC Status:</strong> Supervisor Approval: <span class="text-primary fw-bold"><?= htmlspecialchars($app['supervisor_approved'] ?? 'Pending'); ?></span>
                            <?php if (!empty($app['grad_supervisor_name'])): ?>
                                <br>Assigned Supervisor: <strong><?= htmlspecialchars($app['grad_supervisor_name']); ?></strong>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($active_cat === 'International'): ?>
                        <div class="p-3 bg-light border rounded small">
                            <strong>International Desk Status:</strong> Visa/NOC: <span class="text-success fw-bold"><?= htmlspecialchars($app['visa_noc_status'] ?? 'Pending'); ?></span>
                            <?php if (!empty($app['intl_hostel_room'])): ?>
                                <br>Hostel Allotment: <strong><?= htmlspecialchars($app['intl_hostel_room']); ?></strong>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 ps-4">
                    <h5 class="fw-bold mb-3"><i class="fa-solid fa-square-poll-vertical me-2"></i>Evaluated Result</h5>
                    <?php if (isset($app['entry_test_score']) && $app['entry_test_score'] !== null): ?>
                        <div class="alert alert-<?= ($app['entry_test_status']=='Pass') ? 'success' : 'danger'; ?>">
                            <h4>Score: <?= $app['entry_test_score']; ?> / 100</h4>
                            <p class="mb-0">Test Result Status: <strong><?= $app['entry_test_status']; ?></strong></p>
                            <small>Evaluated by: <strong><?= htmlspecialchars($app['test_evaluated_by'] ?: 'Admissions Evaluator'); ?></strong></small>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fa-solid fa-clock me-2"></i>Test Score Pending Publication by Admissions Evaluator.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 8: Interview & Merit Clearance -->
    <div class="card shadow-sm border-0 step-card <?= $current_step == 8 ? 'active-step' : ''; ?>">
        <div class="card-header bg-secondary text-white fw-bold"><i class="fa-solid fa-comments me-2"></i>Step 8: Interview Schedule & Merit Evaluation</div>
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-6 border-end">
                    <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-calendar-days me-2"></i>Interview Schedule</h5>
                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item"><strong>Interview Date:</strong> <?= htmlspecialchars($app['interview_date'] ?: 'To Be Announced'); ?></li>
                        <li class="list-group-item"><strong>Interview Time:</strong> <?= htmlspecialchars($app['interview_time'] ?: 'To Be Announced'); ?></li>
                        <li class="list-group-item"><strong>Interview Venue:</strong> <?= htmlspecialchars($app['interview_venue'] ?: 'Faculty Conference Room A'); ?></li>
                    </ul>
                </div>
                <div class="col-md-6 ps-4">
                    <h5 class="fw-bold mb-3"><i class="fa-solid fa-user-check me-2"></i>Interview & Aggregate Clearance</h5>
                    <div class="alert alert-info">
                        <h5>Interview Status: <strong><?= htmlspecialchars($app['interview_status']??'Scheduled'); ?></strong></h5>
                        <?php if (!empty($app['interview_score'])): ?>
                            <p class="mb-1">Interview Score: <strong><?= htmlspecialchars($app['interview_score']); ?></strong></p>
                        <?php endif; ?>
                        <?php if (!empty($app['calculated_aggregate'])): ?>
                            <p class="mb-1">Calculated Aggregate: <strong><?= htmlspecialchars($app['calculated_aggregate']); ?>%</strong> (Merit: <strong><?= htmlspecialchars($app['merit_status'] ?? 'Pending'); ?></strong>)</p>
                        <?php endif; ?>
                        <p class="mb-0">Panel Remarks: <?= htmlspecialchars($app['interview_remarks'] ?: 'Pending Panel Evaluation'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 9: Offer Letter & Acceptance -->
    <div class="card shadow-sm border-0 step-card <?= $current_step == 9 ? 'active-step' : ''; ?>">
        <div class="card-header bg-success text-white fw-bold"><i class="fa-solid fa-envelope-open-text me-2"></i>Step 9: Official Admission Offer Letter</div>
        <div class="card-body p-4 text-center">
            <i class="fa-solid fa-circle-check fa-4x text-success mb-3"></i>
            <h3 class="fw-bold">Congratulations!</h3>
            <p class="lead">Your Admission Offer Letter has been authorized by the <strong><?= htmlspecialchars($app['offer_issued_by'] ?: 'Chief Admissions Officer'); ?></strong>.</p>
            <p class="fs-5">Admitted Program: <strong><?= htmlspecialchars($program_name); ?></strong></p>

            <?php if (($active_cat === 'Postgraduate' || $active_cat === 'PhD') && !empty($app['grad_fellowship_tier']) && $app['grad_fellowship_tier'] !== 'None'): ?>
                <p class="badge bg-warning text-dark fs-6 px-3 py-2">Fellowship / Grant Awarded: <?= htmlspecialchars($app['grad_fellowship_tier']); ?></p>
            <?php endif; ?>
            
            <?php if (($app['offer_status']??'') == 'Accepted'): ?>
                <div class="alert alert-success mt-3">
                    <i class="fa-solid fa-user-shield me-2"></i><strong>Offer Accepted!</strong><br>
                    <?php if (!empty($app['roll_number'])): ?>
                        Assigned University Roll Number: <strong class="fs-5 text-danger"><?= htmlspecialchars($app['roll_number']); ?></strong> (Section: <strong><?= htmlspecialchars($app['section_assigned'] ?? 'Section-A'); ?></strong>)<br>
                    <?php endif; ?>
                    Student Record copied to dynamic target table, and official User Account created (Default Password: <strong>123456</strong>).
                </div>
            <?php else: ?>
                <form method="POST" class="mt-4">
                    <input type="hidden" name="step_number" value="9">
                    <button type="submit" class="btn btn-success btn-lg fw-bold px-4 shadow">
                        <i class="fa-solid fa-check-circle me-2"></i> Accept Admission Offer & Register Account
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

</div>

</body>
</html>