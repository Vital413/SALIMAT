<?php
// index.php - Main Entry Point / Landing Page

// db.php is included in config.php, which also starts the session and sets up the database connection
// require_once 'config/config.php';
session_start();

// Future logic: If a user is already logged in, redirect them to their respective module
// if (isset($_SESSION['role'])) {
//     if ($_SESSION['role'] === 'patient') header("Location: patient/dashboard.php");
//     if ($_SESSION['role'] === 'doctor') header("Location: doctor/dashboard.php");
//     if ($_SESSION['role'] === 'admin') header("Location: admin/dashboard.php");
//     exit();
// }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LuminaCare - Maternal Health Remote Monitoring</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* Custom Styling for the Landing Page */
        :root {
            --primary-color: #2b7a78;
            --secondary-color: #3aafa9;
            --accent-color: #ff9a9e;
            --accent-hover: #ff7e84;
            --text-dark: #17252a;
            --text-muted: #5a6c72;
            --text-light: #f1f2f6;
            --bg-light: #f8fafb;
            --bg-white: #ffffff;
        }

        /* Prevent all horizontal scrolling globally */
        html,
        body {
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
            background-color: var(--bg-light);
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .navbar-brand {
            font-family: 'Poppins', sans-serif;
        }

        /* Emergency Banner */
        .emergency-banner {
            background-color: #ff4d4d;
            color: white;
            text-align: center;
            padding: 12px 15px;
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            width: 100%;
            z-index: 1050;
        }

        /* Navbar - Sticky instead of fixed to prevent overlap issues */
        .navbar {
            background-color: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
            padding: 15px 0;
            z-index: 1040;
        }

        .navbar-brand {
            font-weight: 800;
            color: var(--primary-color) !important;
            font-size: 1.75rem;
            letter-spacing: -0.5px;
        }

        .navbar-brand span {
            color: var(--accent-color);
        }

        .nav-link {
            font-weight: 500;
            color: var(--text-dark) !important;
            margin: 0 10px;
            position: relative;
            transition: color 0.3s;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background-color: var(--secondary-color);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .nav-link:hover {
            color: var(--secondary-color) !important;
        }

        .btn-primary-custom {
            background-color: var(--primary-color);
            color: white;
            border-radius: 50px;
            padding: 10px 28px;
            font-weight: 600;
            border: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-primary-custom:hover {
            background-color: var(--secondary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(43, 122, 120, 0.3);
        }

        .btn-accent-custom {
            background-color: var(--accent-color);
            color: white;
            border-radius: 50px;
            padding: 10px 28px;
            font-weight: 600;
            border: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-accent-custom:hover {
            background-color: var(--accent-hover);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 154, 158, 0.4);
        }

        /* Hero Section */
        .hero-section {
            padding: 100px 0;
            background: linear-gradient(135deg, rgba(58, 175, 169, 0.05) 0%, rgba(255, 154, 158, 0.1) 100%);
            min-height: 85vh;
            display: flex;
            align-items: center;
            position: relative;
        }

        /* Background decorative blobs */
        .blob-1,
        .blob-2 {
            position: absolute;
            border-radius: 50%;
            z-index: -1;
            pointer-events: none;
        }

        .blob-1 {
            top: 10%;
            left: -10%;
            width: clamp(250px, 40vw, 400px);
            height: clamp(250px, 40vw, 400px);
            background: rgba(58, 175, 169, 0.1);
            filter: blur(60px);
        }

        .blob-2 {
            bottom: -10%;
            right: -5%;
            width: clamp(300px, 50vw, 500px);
            height: clamp(300px, 50vw, 500px);
            background: rgba(255, 154, 158, 0.15);
            filter: blur(80px);
        }

        .hero-title {
            font-weight: 800;
            font-size: clamp(2rem, 5vw, 4rem);
            line-height: 1.15;
            margin-bottom: 24px;
            color: var(--text-dark);
            letter-spacing: -1px;
        }

        .hero-title span {
            color: var(--primary-color);
            position: relative;
            display: inline-block;
        }

        .hero-title span::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 30%;
            background: rgba(255, 154, 158, 0.3);
            bottom: 5px;
            left: 0;
            z-index: -1;
            border-radius: 4px;
        }

        .hero-subtitle {
            font-size: 1.15rem;
            color: var(--text-muted);
            margin-bottom: 40px;
            line-height: 1.7;
            max-width: 90%;
        }

        .hero-img-wrapper {
            position: relative;
            z-index: 1;
        }

        .hero-img {
            border-radius: 30px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            width: 100%;
            height: auto;
            object-fit: cover;
            border: 8px solid white;
        }

        /* Trust Badges */
        .trust-badges {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            align-items: center;
            flex-wrap: wrap;
        }

        .trust-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
            background: white;
            padding: 8px 16px;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            white-space: nowrap;
        }

        /* Floating Elements */
        .floating-card {
            position: absolute;
            background: white;
            padding: 16px 24px;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 15px;
            animation: float 4s ease-in-out infinite;
            z-index: 2;
            border: 1px solid rgba(0, 0, 0, 0.02);
        }

        .floating-card.top-right {
            top: 30px;
            right: -30px;
        }

        .floating-card.bottom-left {
            bottom: 50px;
            left: -40px;
            animation-delay: 2s;
        }

        .floating-icon {
            font-size: 2rem;
            background: var(--bg-light);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            flex-shrink: 0;
        }

        @keyframes float {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-15px);
            }

            100% {
                transform: translateY(0px);
            }
        }

        /* Stats Section */
        .stats-section {
            padding: 60px 0;
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .stat-item h2 {
            font-size: clamp(2rem, 4vw, 2.5rem);
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-item p {
            color: var(--text-muted);
            font-weight: 500;
            margin: 0;
        }

        /* Features Section */
        .features-section {
            padding: 100px 0;
            background-color: var(--bg-light);
        }

        .section-header {
            text-align: center;
            margin-bottom: 60px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            padding: 0 15px;
        }

        .section-subtitle {
            color: var(--primary-color);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-size: 0.9rem;
            display: block;
            margin-bottom: 10px;
        }

        .section-title {
            font-weight: 800;
            font-size: clamp(1.75rem, 4vw, 2.75rem);
            color: var(--text-dark);
            margin-bottom: 20px;
        }

        .feature-card {
            padding: 40px 30px;
            border-radius: 24px;
            background: white;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.03);
            transition: all 0.4s ease;
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.02);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-color);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-icon-wrapper {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            font-size: 2rem;
        }

        .icon-teal {
            background: rgba(43, 122, 120, 0.1);
            color: var(--primary-color);
        }

        .icon-pink {
            background: rgba(255, 154, 158, 0.15);
            color: #ff6b72;
        }

        .icon-dark {
            background: rgba(23, 37, 42, 0.08);
            color: var(--text-dark);
        }

        .feature-card h4 {
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 1.35rem;
        }

        .feature-card p {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 0;
        }

        /* How it Works Section */
        .how-it-works {
            padding: 100px 0;
            background-color: white;
        }

        .step-card {
            text-align: center;
            padding: 20px;
            position: relative;
        }

        .step-number {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 auto 25px;
            position: relative;
            z-index: 2;
            box-shadow: 0 10px 20px rgba(43, 122, 120, 0.2);
        }

        .step-connector {
            position: absolute;
            top: 50px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: dashed 2px rgba(43, 122, 120, 0.2);
            z-index: 1;
        }

        @media (max-width: 991px) {
            .step-connector {
                display: none;
            }
        }

        /* CTA Section */
        .cta-section {
            padding: 80px 0;
            background: var(--primary-color);
            background-image: radial-gradient(circle at 100% 0%, #3aafa9 0%, transparent 50%),
                radial-gradient(circle at 0% 100%, #17252a 0%, transparent 50%);
            color: white;
            text-align: center;
        }

        .cta-section h2 {
            font-weight: 800;
            margin-bottom: 20px;
            padding: 0 15px;
        }

        .cta-section p {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            padding: 0 15px;
        }

        /* Footer */
        .footer {
            background-color: #111a1e;
            color: white;
            padding: 80px 0 30px;
        }

        .footer-brand {
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
            margin-bottom: 20px;
            display: inline-block;
            text-decoration: none;
        }

        .footer-brand span {
            color: var(--accent-color);
        }

        .footer p {
            color: #8a99a0;
            line-height: 1.7;
        }

        .footer h5 {
            color: white;
            font-weight: 700;
            margin-bottom: 25px;
            font-size: 1.1rem;
        }

        .footer ul li {
            margin-bottom: 15px;
        }

        .footer a {
            color: #8a99a0;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer a:hover {
            color: var(--secondary-color);
            padding-left: 5px;
        }

        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            margin-right: 10px;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-3px);
        }

        /* Mobile Responsive Adjustments */
        @media (max-width: 991px) {
            .hero-section {
                padding: 60px 0;
                text-align: center;
            }

            .hero-subtitle {
                margin: 0 auto 30px;
                max-width: 100%;
            }

            .trust-badges {
                justify-content: center;
            }

            .hero-img-wrapper {
                margin-top: 50px;
            }

            /* Hide decorative absolute cards on smaller screens to prevent overflow */
            .floating-card {
                display: none;
            }

            .navbar-collapse {
                background: white;
                padding: 20px;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                margin-top: 15px;
            }

            .features-section {
                padding: 60px 0;
            }

            .how-it-works {
                padding: 60px 0;
            }

            .cta-section {
                padding: 60px 0;
            }
        }

        @media (max-width: 576px) {

            .btn-accent-custom,
            .btn-light,
            .btn-outline-light {
                width: 100%;
                /* Full width buttons on very small screens */
                margin-bottom: 10px;
            }

            .trust-badge {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>

    <!-- Emergency Disclaimer: Changed to relative block so it flows naturally and doesn't overlap the navbar -->
    <div class="emergency-banner">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> In case of a medical emergency, please call your local emergency services or go to the nearest hospital immediately.
    </div>

    <!-- Navigation: Changed from fixed-top to sticky-top to prevent overlaps -->
    <nav class="navbar navbar-expand-lg sticky-top" id="mainNav">
        <div class="container">
            <a class="navbar-brand" href="#">Lumina<span>Care</span></a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarNav">
                <i class="bi bi-list fs-1 text-dark"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#how-it-works">How it Works</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row gap-3 align-items-lg-center">
                    <div class="dropdown">
                        <!-- Removed w-100 on desktop, letting flexbox handle it gracefully -->
                        <button class="btn btn-primary-custom dropdown-toggle w-100" type="button"
                            data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-2"></i> Access Portals
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-3 rounded-3 p-2 w-100">
                            <li>
                                <h6 class="dropdown-header fw-bold text-uppercase text-muted">Select Portal</h6>
                            </li>
                            <li><a class="dropdown-item py-2 rounded" href="patient/login.php"><i
                                        class="bi bi-heart-pulse text-danger me-2"></i> Patient Login</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item py-2 rounded" href="doctor/login.php"><i
                                        class="bi bi-stethoscope text-primary me-2"></i> Doctor Login</a></li>
                            <li><a class="dropdown-item py-2 rounded" href="admin/login.php"><i
                                        class="bi bi-shield-lock text-dark me-2"></i> Administrator</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="blob-1"></div>
        <div class="blob-2"></div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <span class="badge bg-white text-primary mb-3 shadow-sm px-3 py-2 border rounded-pill fw-semibold">
                        <i class="bi bi-shield-check me-1"></i> HIPAA Compliant & Secure
                    </span>
                    <h1 class="hero-title">Empowering Maternal Health, <span>Anywhere, Anytime.</span></h1>
                    <p class="hero-subtitle">Experience peace of mind during your pregnancy. LuminaCare bridges the gap
                        between expectant mothers and doctors through real-time vitals tracking, smart alerts, and
                        secure messaging.</p>

                    <div class="d-flex flex-column flex-sm-row gap-3 justify-content-lg-start justify-content-center">
                        <a href="patient/register.php" class="btn btn-accent-custom fs-5"><i
                                class="bi bi-person-plus me-2"></i> Join as a Patient</a>
                        <a href="#features"
                            class="btn btn-light fs-5 px-4 py-2 fw-semibold rounded-pill border shadow-sm">Explore
                            Features</a>
                    </div>

                    <div class="trust-badges">
                        <div class="trust-badge"><i class="bi bi-check-circle-fill text-success"></i> 24/7 Monitoring</div>
                        <div class="trust-badge"><i class="bi bi-lock-fill text-secondary"></i> Encrypted Data</div>
                        <div class="trust-badge"><i class="bi bi-heart-fill text-danger"></i> Trusted by Doctors</div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="hero-img-wrapper">
                        <img src="https://images.unsplash.com/photo-1555252333-9f8e92e65df9?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80"
                            alt="Pregnant woman reviewing health data on a tablet" class="hero-img img-fluid">

                        <!-- Decorative UI elements demonstrating the app's function -->
                        <div class="floating-card top-right">
                            <div class="floating-icon text-success"><i class="bi bi-activity"></i></div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Vitals Logged</h6>
                                <small class="text-muted">BP: 120/80 - Normal</small>
                            </div>
                        </div>

                        <div class="floating-card bottom-left">
                            <div class="floating-icon text-primary"><i class="bi bi-chat-dots"></i></div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Dr. Sarah Jenkins</h6>
                                <small class="text-muted">"Your readings look great today!"</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Stats -->
    <section class="stats-section">
        <div class="container">
            <div class="row g-4 text-center">
                <div class="col-md-4 stat-item">
                    <h2>10k+</h2>
                    <p>Mothers Monitored</p>
                </div>
                <div class="col-md-4 stat-item">
                    <h2>500+</h2>
                    <p>Verified Healthcare Providers</p>
                </div>
                <div class="col-md-4 stat-item">
                    <h2>99.9%</h2>
                    <p>System Uptime & Reliability</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <div class="section-header">
                <span class="section-subtitle">Why Choose LuminaCare</span>
                <h2 class="section-title">Comprehensive Care Solutions Designed for You</h2>
                <p class="text-muted fs-5">We provide the tools necessary to ensure a healthy pregnancy journey,
                    combining modern technology with compassionate care.</p>
            </div>

            <div class="row g-4">
                <!-- Feature 1 -->
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper icon-teal">
                            <i class="bi bi-clipboard2-pulse"></i>
                        </div>
                        <h4>Vitals Monitoring</h4>
                        <p>Easily log daily metrics like blood pressure, weight, heart rate, and blood sugar. Our
                            intuitive dashboard charts your progress visually.</p>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper icon-pink">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h4>Strict Data Security</h4>
                        <p>Your health data is completely isolated. With separate modules for patients and doctors, we
                            ensure industry-standard privacy and security.</p>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper icon-dark">
                            <i class="bi bi-chat-quote"></i>
                        </div>
                        <h4>Secure Direct Messaging</h4>
                        <p>Have a question about a symptom? Reach out to your assigned healthcare provider directly
                            through our encrypted, real-time chat interface.</p>
                    </div>
                </div>

                <!-- Feature 4 -->
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper icon-pink">
                            <i class="bi bi-bell-fill"></i>
                        </div>
                        <h4>Automated Smart Alerts</h4>
                        <p>If your logged vitals fall outside the normal, safe ranges, the system immediately notifies
                            your doctor so they can intervene quickly.</p>
                    </div>
                </div>

                <!-- Feature 5 -->
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper icon-dark">
                            <i class="bi bi-journal-medical"></i>
                        </div>
                        <h4>Symptom Tracker</h4>
                        <p>Keep a daily log of how you're feeling. Track morning sickness, fetal movement, and other
                            crucial symptoms to discuss at your next visit.</p>
                    </div>
                </div>

                <!-- Feature 6 -->
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper icon-teal">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <h4>Appointment Management</h4>
                        <p>Never miss a checkup. View your upcoming virtual or in-person appointments set by your
                            healthcare provider right from your dashboard.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <div class="section-header">
                <span class="section-subtitle">Simple Process</span>
                <h2 class="section-title">How LuminaCare Works</h2>
            </div>

            <div class="row position-relative g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <div class="step-connector"></div>
                        <h5 class="fw-bold">Create Profile</h5>
                        <p class="text-muted small">Register securely and get linked with your verified healthcare
                            provider.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <div class="step-connector"></div>
                        <h5 class="fw-bold">Log Daily Vitals</h5>
                        <p class="text-muted small">Input your blood pressure, weight, and symptoms using our simple
                            interface.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <div class="step-connector"></div>
                        <h5 class="fw-bold">Doctor Analysis</h5>
                        <p class="text-muted small">Your doctor reviews your data remotely and receives alerts for any
                            anomalies.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="step-card">
                        <div class="step-number">4</div>
                        <h5 class="fw-bold">Stay Connected</h5>
                        <p class="text-muted small">Receive feedback, chat securely, and enjoy a healthier pregnancy
                            journey.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call To Action -->
    <section class="cta-section">
        <div class="container">
            <h2>Ready to Prioritize Your Maternal Health?</h2>
            <p>Join thousands of mothers and doctors who trust LuminaCare for remote, reliable, and secure pregnancy
                monitoring.</p>
            <div class="d-flex flex-wrap gap-3 justify-content-center">
                <a href="patient/register.php" class="btn btn-light btn-lg rounded-pill fw-bold text-primary px-5">Get
                    Started Today</a>
                <a href="doctor/register.php" class="btn btn-outline-light btn-lg rounded-pill fw-bold px-5">Provider
                    Enrollment</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-4 pe-lg-5">
                    <a href="#" class="footer-brand">Lumina<span>Care</span></a>
                    <p>A dedicated remote monitoring system bridging the gap between expectant mothers and medical
                        professionals. Ensuring safer pregnancies through technology.</p>
                    <div class="social-links mt-4">
                        <a href="#"><i class="bi bi-facebook"></i></a>
                        <a href="#"><i class="bi bi-twitter-x"></i></a>
                        <a href="#"><i class="bi bi-instagram"></i></a>
                        <a href="#"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <h5>For Patients</h5>
                    <ul class="list-unstyled">
                        <li><a href="patient/login.php">Patient Login</a></li>
                        <li><a href="patient/register.php">Create Account</a></li>
                        <li><a href="#">How it Works</a></li>
                        <li><a href="#">FAQ</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <h5>For Providers</h5>
                    <ul class="list-unstyled">
                        <li><a href="doctor/login.php">Doctor Login</a></li>
                        <li><a href="#">Join Network</a></li>
                        <li><a href="#">Clinical Guidelines</a></li>
                        <li><a href="admin/login.php">System Admin</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-4">
                    <h5>Legal & Support</h5>
                    <ul class="list-unstyled">
                        <li><a href="#">Privacy Policy (HIPAA)</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Contact Support</a></li>
                    </ul>
                    <div class="mt-4 p-3 bg-dark rounded border border-secondary">
                        <small class="text-light d-block mb-1"><i class="bi bi-telephone-fill me-2 text-primary"></i>
                            24/7 Support Helpline:</small>
                        <strong class="fs-5 text-white">1-800-LUMINA-CARE</strong>
                    </div>
                </div>
            </div>
            <hr class="mt-5 mb-4 border-secondary opacity-25">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <small class="text-muted">&copy;
                        <?php echo date("Y"); ?> LuminaCare Maternal Health System. All rights reserved.
                    </small>
                </div>
                <div class="col-md-6 text-center text-md-end mt-3 mt-md-0">
                    <small class="text-muted">Designed for Secure Remote Healthcare</small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom Interactions -->
    <script>
        // Shrink navbar slightly on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('mainNav');
            if (window.scrollY > 10) {
                navbar.style.padding = '10px 0';
                navbar.style.boxShadow = '0 10px 30px rgba(0,0,0,0.1)';
            } else {
                navbar.style.padding = '15px 0';
                navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.04)';
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>

</html>