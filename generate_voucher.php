<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['applicant_id'])) {
    die("Unauthorized Access");
}

$applicant_id = $_SESSION['applicant_id'];

// Fetch Applicant & Application Details
$stmt = $conn->prepare("SELECT app.*, a.full_name, a.email, a.phone, a.cnic_bform, p.program_name 
                        FROM student_applications app 
                        JOIN applicants a ON app.applicant_id = a.id 
                        LEFT JOIN degree_programs p ON app.program_id = p.id 
                        WHERE app.applicant_id = ?");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("No Application Record Found!");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Entry Test Fee Voucher #<?= $data['id']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: Arial, sans-serif; }
        .voucher-card { border: 2px dashed #0d6efd; background: #fff; padding: 25px; border-radius: 8px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body class="py-4">

<div class="container" style="max-width: 750px;">
    <div class="text-end mb-3 no-print">
        <button onclick="window.print()" class="btn btn-primary font-weight-bold"><i class="fa-solid fa-print"></i> Print / Save as PDF</button>
    </div>

    <div class="voucher-card shadow-sm">
        <div class="row align-items-center mb-3">
            <div class="col-8">
                <h3 class="fw-bold text-primary mb-0">NATIONAL UNIVERSITY</h3>
                <small class="text-muted">Admission Entry Test Fee Challan (Bank Copy / Student Copy)</small>
            </div>
            <div class="col-4 text-end">
                <span class="badge bg-danger fs-6">Challan #VCH-<?= str_pad($data['id'], 5, '0', STR_PAD_LEFT); ?></span>
            </div>
        </div>

        <hr>

        <table class="table table-sm table-bordered">
            <tr>
                <th style="width: 35%;">Applicant Name</th>
                <td><?= htmlspecialchars($data['full_name']); ?></td>
            </tr>
            <tr>
                <th>CNIC / B-Form</th>
                <td><?= htmlspecialchars($data['cnic_bform']); ?></td>
            </tr>
            <tr>
                <th>Student Type</th>
                <td><?= htmlspecialchars($data['student_type']); ?></td>
            </tr>
            <tr>
                <th>Applied Program</th>
                <td><?= htmlspecialchars($data['program_name'] ?: 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Contact Phone</th>
                <td><?= htmlspecialchars($data['phone']); ?></td>
            </tr>
            <tr>
                <th>Issue Date</th>
                <td><?= date('Y-m-d'); ?></td>
            </tr>
            <tr>
                <th>Due Date</th>
                <td><?= date('Y-m-d', strtotime('+7 days')); ?></td>
            </tr>
        </table>

        <div class="alert alert-secondary d-flex justify-content-between align-items-center mb-3 py-2">
            <span class="fw-bold">Total Entry Test & Processing Fee:</span>
            <span class="fs-4 fw-bold text-success">PKR 2,000/-</span>
        </div>

        <div class="row mt-4">
            <div class="col-6 text-center">
                <br><br>
                <p class="mb-0 border-top pt-1">Bank Cashier Officer</p>
            </div>
            <div class="col-6 text-center">
                <br><br>
                <p class="mb-0 border-top pt-1">Applicant Signature</p>
            </div>
        </div>
    </div>
</div>

</body>
</html>