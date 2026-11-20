<?php
session_start();
require_once 'db.php'; // AAP KI PURANI db.php HI INCLUDED HAI

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        // Fetch applicant using MySQLi
        $stmt = $conn->prepare("SELECT * FROM applicants WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $applicant = $result->fetch_assoc();
            
            if (password_verify($password, $applicant['password'])) {
                $_SESSION['applicant_id']   = $applicant['id'];
                $_SESSION['applicant_name'] = $applicant['full_name'];
                $_SESSION['applicant_email'] = $applicant['email'];

                header("Location: apply.php");
                exit;
            } else {
                $msg = "Invalid email address or password!";
            }
        } else {
            $msg = "Invalid email address or password!";
        }
    } else {
        $msg = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Applicant Login - Admission Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center my-4">
        <div class="col-md-5 col-lg-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-primary text-white text-center p-4">
                    <i class="fa-solid fa-graduation-cap fa-3x mb-2"></i>
                    <h4 class="fw-bold mb-0">Candidate Portal</h4>
                    <small>Sign in to start your application</small>
                </div>
                <div class="card-body p-4">
                    
                    <?php if (!empty($msg)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($msg); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 font-weight-bold">
                            <i class="fa-solid fa-right-to-bracket me-1"></i> Sign In
                        </button>
                    </form>
                    
                    <hr class="my-4">
                    <div class="text-center">
                        <p class="mb-0 text-muted">Don't have an account yet?</p>
                        <a href="register.php" class="fw-bold">Create New Candidate Account</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>