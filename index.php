<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>National University of Technology & Sciences</title>
    <!-- Bootstrap 5 CSS & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1541829070764-84a7d30dd3f3?auto=format&fit=crop&w=1350&q=80') center/cover no-repeat;
            min-height: 85vh;
            color: white;
            display: flex;
            align-items: center;
        }
        .top-bar {
            background-color: #0b2545;
            color: #e0e1dd;
            font-size: 0.85rem;
        }
        .navbar-custom {
            background-color: #134074;
        }
        .btn-admission {
            background-color: #ffb703;
            color: #000;
            font-weight: bold;
            padding: 10px 25px;
            border-radius: 25px;
            box-shadow: 0 4px 10px rgba(255, 183, 3, 0.4);
            transition: all 0.3s ease;
        }
        .btn-admission:hover {
            background-color: #fb8500;
            color: #fff;
            transform: translateY(-2px);
        }
        .feature-card {
            border: none;
            border-radius: 12px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }
    </style>
</head>
<body>

    <!-- Top Info Bar -->
    <div class="top-bar py-2">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <span class="me-3"><i class="fa-solid fa-envelope me-1"></i> info@nuts.edu.pk</span>
                <span><i class="fa-solid fa-phone me-1"></i> +92 (21) 111-888-999</span>
            </div>
            <div>
                <a href="#" class="text-light me-3"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" class="text-light me-3"><i class="fa-brands fa-twitter"></i></a>
                <a href="#" class="text-light"><i class="fa-brands fa-linkedin-in"></i></a>
            </div>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="#">
                <i class="fa-solid fa-graduation-cap fa-2x me-2 text-warning"></i>
                <span>NUTS UNIVERSITY</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="#programs">Programs</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                </ul>
                <!-- Admission Portal Link -->
                <div class="d-flex align-items-center">
                    <a href="apply.php" class="btn btn-admission">
                        <i class="fa-solid fa-paper-plane me-1"></i> Apply For Admission
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Banner Section -->
    <header class="hero-section text-center text-lg-start">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-warning text-dark px-3 py-2 mb-3 fw-bold fs-6">Fall 2026 Admissions Open</span>
                    <h1 class="display-3 fw-bold mb-3">Shape Your Future With Quality Education</h1>
                    <p class="lead mb-4">Join a world-class institution dedicated to research, innovation, and leadership. Build your professional carrier with our top-tier degree programs.</p>
                    <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                        <a href="apply.php" class="btn btn-warning btn-lg fw-bold px-4">
                            <i class="fa-solid fa-user-plus me-2"></i>New Student Admission
                        </a>
                        <a href="#programs" class="btn btn-outline-light btn-lg px-4">
                            Explore Programs
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Quick Quick Stats & Highlights -->
    <section class="py-5 bg-light" id="about">
        <div class="container">
            <div class="row g-4 text-center">
                <div class="col-md-3">
                    <div class="card p-4 shadow-sm feature-card bg-white">
                        <i class="fa-solid fa-user-graduate fa-3x text-primary mb-3"></i>
                        <h3 class="fw-bold">15,000+</h3>
                        <p class="text-muted mb-0">Graduated Students</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-4 shadow-sm feature-card bg-white">
                        <i class="fa-solid fa-book-open-reader fa-3x text-success mb-3"></i>
                        <h3 class="fw-bold">45+</h3>
                        <p class="text-muted mb-0">Degree Programs</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-4 shadow-sm feature-card bg-white">
                        <i class="fa-solid fa-chalkboard-user fa-3x text-warning mb-3"></i>
                        <h3 class="fw-bold">350+</h3>
                        <p class="text-muted mb-0">Expert Faculty</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-4 shadow-sm feature-card bg-white">
                        <i class="fa-solid fa-award fa-3x text-danger mb-3"></i>
                        <h3 class="fw-bold">Top 5</h3>
                        <p class="text-muted mb-0">HEC Ranked</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Offered Programs Section -->
    <section class="py-5" id="programs">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Our Degree Programs</h2>
                <p class="text-muted">Choose from a variety of discipline choices for your career path</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm feature-card">
                        <div class="card-body p-4">
                            <div class="badge bg-primary mb-2">Faculty of IT</div>
                            <h4 class="card-title fw-bold">Computer Science</h4>
                            <p class="card-text text-muted">BS CS, BS Software Engineering, MS Data Science</p>
                            <a href="apply.php" class="btn btn-outline-primary btn-sm fw-bold">Apply Now &rarr;</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm feature-card">
                        <div class="card-body p-4">
                            <div class="badge bg-success mb-2">Business School</div>
                            <h4 class="card-title fw-bold">Business Administration</h4>
                            <p class="card-text text-muted">BBA, MBA, BS Accounting & Finance</p>
                            <a href="apply.php" class="btn btn-outline-success btn-sm fw-bold">Apply Now &rarr;</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm feature-card">
                        <div class="card-body p-4">
                            <div class="badge bg-danger mb-2">Engineering</div>
                            <h4 class="card-title fw-bold">Electrical & Cyber</h4>
                            <p class="card-text text-muted">BS Electrical Engineering, BS Cyber Security</p>
                            <a href="apply.php" class="btn btn-outline-danger btn-sm fw-bold">Apply Now &rarr;</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 text-center">
        <div class="container">
            <p class="mb-0">&copy; 2026 National University of Technology & Sciences. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>