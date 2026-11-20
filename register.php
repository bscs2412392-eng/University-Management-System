<?php
session_start();
require_once 'db.php'; // AAP KI PURANI db.php HI INCLUDED HAI

$msg = "";
$msg_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name  = trim($_POST['full_name']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];
    $phone      = trim($_POST['phone']);
    $cnic_bform = trim($_POST['cnic_bform']);

    if (!empty($full_name) && !empty($email) && !empty($password) && !empty($cnic_bform)) {
        
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Check if Email or CNIC already exists using MySQLi
        $check_stmt = $conn->prepare("SELECT id FROM applicants WHERE email = ? OR cnic_bform = ?");
        $check_stmt->bind_param("ss", $email, $cnic_bform);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $msg = "Email or CNIC/B-Form is already registered!";
            $msg_type = "danger";
        } else {
            // Insert applicant data using MySQLi
            $stmt = $conn->prepare("INSERT INTO applicants (full_name, email, password, phone, cnic_bform) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $full_name, $email, $hashed_password, $phone, $cnic_bform);

            if ($stmt->execute()) {
                $msg = "Registration successful! Please login to continue.";
                $msg_type = "success";
            } else {
                $msg = "Registration failed: " . $conn->error;
                $msg_type = "danger";
            }
        }
    } else {
        $msg = "Please fill all required fields!";
        $msg_type = "warning";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Applicant Register - University Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body p-4">
                    <h3 class="fw-bold text-center mb-4 text-primary">Create Account</h3>
                    
                    <?php if (!empty($msg)): ?>
                        <div class="alert alert-<?= $msg_type; ?> alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($msg); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Full Name</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">CNIC / B-Form No.</label>
                            <input type="text" name="cnic_bform" class="form-control" placeholder="42101-XXXXXXX-X" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Register Now</button>
                    </form>
                    
                    <hr class="my-4">
                    <p class="text-center mb-0">Already have an account? <a href="applicant_login.php" class="fw-bold">Login Here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>