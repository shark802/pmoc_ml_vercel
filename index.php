<?php
session_start();
require_once 'includes/image_helper.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>BCPDO | Bago City Population Development Office</title>
    <link href="<?= getSecureImagePath('images/bcpdo.png') ?>" rel="icon" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Bago City Population Development Office Pre-Marriage Counseling System">
    <meta name="keywords" content="pre-marriage counseling, Bago City, relationship counseling, family planning">
    <meta name="author" content="BCPDO">
    
    <!-- Security Meta Tags -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta name="referrer" content="strict-origin-when-cross-origin">

    <!-- Open Graph / Social Media Meta Tags -->
    <meta property="og:title" content="BCPDO Pre-Marriage Counseling">
    <meta property="og:description" content="Bago City Population Development Office Pre-Marriage Counseling System">
    <meta property="og:image" content="<?= getSecureImagePath('images/bcpdo.png') ?>">
    <meta property="og:url" content="https://bcpdo.bagocity.gov.ph">
    <meta property="og:type" content="website">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://bcpdo.bagocity.gov.ph">

    <!-- Preload important resources -->
    <link rel="preload" href="images/mytmcc.jpg" as="image" type="image/jpeg">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" as="style">
    
    <!-- DNS Prefetch for external resources -->
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">

    <!-- Use local Font Awesome (v5) to match AdminLTE and avoid conflicts -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #7209b7;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --text-muted: #6c757d;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background-color: var(--light-color);
            scroll-behavior: smooth;
        }

        /* Skip to Content Link */
        .skip-link {
            position: absolute;
            top: -40px;
            left: 0;
            background: var(--primary-color);
            color: white;
            padding: 8px;
            z-index: 100;
            transition: top 0.3s;
        }

        .skip-link:focus {
            top: 0;
        }

        /* Header Styles */
        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .header.scrolled {
            padding: 0.5rem 0;
            background: rgba(67, 97, 238, 0.95);
            backdrop-filter: blur(10px);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
        }

        .logo img {
            width: 50px;
            height: auto;
            transition: transform 0.3s;
        }

        .logo:hover img {
            transform: rotate(10deg);
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            position: relative;
            padding: 0.5rem 0;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--warning-color);
            transition: width 0.3s;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-links a:hover {
            color: var(--warning-color);
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            display: inline-block;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid white;
            color: white;
        }

        .btn-outline:hover {
            background: white;
            color: var(--primary-color);
        }

        .btn-primary {
            background: var(--warning-color);
            color: var(--dark-color);
        }

        .btn-primary:hover {
            background: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Hamburger Menu */
        .hamburger {
            display: none;
            cursor: pointer;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)),
                url('images/mytmcc.jpg') no-repeat center center;
            background-size: cover;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            margin-top: 0;
            position: relative;
        }

        .hero-content {
            max-width: 800px;
            padding: 0 2rem;
            position: relative;
            z-index: 1;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            line-height: 1.2;
        }

        .hero p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-large {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            border-radius: 10px;
        }

        .btn-white {
            background: white;
            color: var(--primary-color);
            border: 2px solid white;
        }

        .btn-white:hover {
            background: transparent;
            color: white;
            transform: translateY(-2px);
        }

        /* Features Section */
        .features {
            padding: 5rem 0;
            background: white;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark-color);
            position: relative;
            display: inline-block;
            left: 50%;
            transform: translateX(-50%);
        }

        .section-title::after {
            content: '';
            position: absolute;
            width: 50%;
            height: 4px;
            bottom: -10px;
            left: 25%;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            border-radius: 2px;
        }

        .section-subtitle {
            text-align: center;
            font-size: 1.1rem;
            color: var(--text-muted);
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
            border-color: var(--primary-color);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: white;
            transition: all 0.3s;
        }

        .feature-card:hover .feature-icon {
            transform: rotate(15deg) scale(1.1);
            background: linear-gradient(135deg, var(--accent-color), var(--primary-color));
        }

        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }

        .feature-card p {
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* About Section */
        .about {
            padding: 5rem 0;
            background: var(--light-color);
        }

        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .about-text h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--dark-color);
            position: relative;
            display: inline-block;
        }

        .about-text h2::after {
            content: '';
            position: absolute;
            width: 50%;
            height: 4px;
            bottom: -10px;
            left: 0;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            border-radius: 2px;
        }

        .about-text p {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
            line-height: 1.8;
        }

        .about-image {
            text-align: center;
            position: relative;
        }

        .about-image img {
            max-width: 100%;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: transform 0.5s;
        }

        .about-image:hover img {
            transform: scale(1.03);
        }

        .about-image::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border: 3px solid var(--primary-color);
            border-radius: 15px;
            top: 20px;
            left: 20px;
            z-index: -1;
            transition: all 0.3s;
        }

        .about-image:hover::before {
            top: 15px;
            left: 15px;
        }

        /* FAQ Section */
        .faq {
            padding: 5rem 0;
            background: white;
        }

        .faq-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-item {
            margin-bottom: 1rem;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }

        .faq-item:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .faq-question {
            padding: 1.5rem;
            background: white;
            color: var(--dark-color);
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }

        .faq-question:hover {
            background: #f8f9fa;
        }

        .faq-question::after {
            content: '+';
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .faq-question.active::after {
            content: '-';
        }

        .faq-answer {
            padding: 0 1.5rem;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out, padding 0.3s ease;
            background: #f8f9fa;
        }

        .faq-answer.show {
            padding: 1.5rem;
            max-height: 500px;
        }

        /* Footer */
        .footer {
            background: var(--dark-color);
            color: white;
            padding: 3rem 0 1rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h3 {
            margin-bottom: 1rem;
            color: var(--warning-color);
            position: relative;
            display: inline-block;
        }

        .footer-section h3::after {
            content: '';
            position: absolute;
            width: 50%;
            height: 2px;
            bottom: -5px;
            left: 0;
            background: var(--warning-color);
        }

        .footer-section p,
        .footer-section a {
            color: #adb5bd;
            text-decoration: none;
            margin-bottom: 0.5rem;
            display: block;
            transition: all 0.3s;
        }

        .footer-section a:hover {
            color: var(--warning-color);
            padding-left: 5px;
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.3s;
        }

        .social-links a:hover {
            background: var(--warning-color);
            color: var(--dark-color);
            transform: translateY(-3px);
        }

        .footer-bottom {
            border-top: 1px solid #495057;
            padding-top: 1rem;
            text-align: center;
            color: #adb5bd;
            font-size: 0.9rem;
        }

        /* Back to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            z-index: 999;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .back-to-top.show {
            opacity: 1;
            visibility: visible;
        }

        .back-to-top:hover {
            background: var(--secondary-color);
            transform: translateY(-3px);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .modal.show {
            opacity: 1;
        }

        .modal-content {
            background-color: white;
            margin: 3% auto;
            padding: 2.5rem;
            border-radius: 15px;
            width: 90%;
            max-width: 450px;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(-20px);
            transition: transform 0.3s;
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .close {
            position: absolute;
            right: 1.5rem;
            top: 1.5rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: color 0.3s;
        }

        .close:hover {
            color: var(--dark-color);
        }

        .modal h3 {
            text-align: center;
            margin-bottom: 1rem;
            color: var(--dark-color);
            font-size: 1.5rem;
        }

        .modal p {
            text-align: center;
            color: var(--text-muted);
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        /* Logo container in modal */
        .logo-container {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .logo-container img {
            width: 80px;
            height: auto;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
            background-color: #fff;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }

        /* Select Dropdown Styling */
        select.form-control {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236c757d' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }

        /* Button Styles */
        .btn-modal {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-modal:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }

        /* Alert Styles */
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            border: 1px solid transparent;
            position: relative;
            padding-left: 3rem;
        }

        .alert i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.2rem;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-muted);
        }

        /* Loading Spinner */
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-left: 10px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Cookie Consent */
        .cookie-consent {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--dark-color);
            color: white;
            padding: 1rem;
            z-index: 1000;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            transform: translateY(100%);
            transition: transform 0.3s;
        }

        .cookie-consent.show {
            transform: translateY(0);
        }

        .cookie-consent p {
            margin: 0.5rem 0;
            flex: 1;
            min-width: 250px;
        }

        .cookie-consent button {
            margin-left: 1rem;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .nav-links {
                position: fixed;
                top: 80px;
                left: -100%;
                width: 80%;
                height: calc(100vh - 80px);
                background: rgba(67, 97, 238, 0.95);
                backdrop-filter: blur(10px);
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 2rem;
                transition: all 0.5s ease;
                z-index: 999;
            }

            .nav-links.active {
                left: 0;
            }

            .hamburger {
                display: block;
            }

            .hero h1 {
                font-size: 2.8rem;
            }

            .about-content {
                grid-template-columns: 1fr;
            }

            .about-image {
                order: -1;
            }
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.2rem;
            }

            .hero p {
                font-size: 1rem;
            }

            .section-title {
                font-size: 2rem;
            }

            .nav-container {
                padding: 0 1rem;
            }

            .auth-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }

            .modal-content {
                margin: 10% auto;
                padding: 2rem;
                width: 95%;
            }
        }

        @media (max-width: 576px) {
            .hero h1 {
                font-size: 1.8rem;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .footer-content {
                grid-template-columns: 1fr;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fadeInUp {
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            20%,
            60% {
                transform: translateX(-5px);
            }

            40%,
            80% {
                transform: translateX(5px);
            }
        }
    </style>
</head>

<body>
    <!-- Skip to Content Link for Accessibility -->
    <a href="#main-content" class="skip-link">Skip to main content</a>
    


    <!-- Cookie Consent -->
    <div class="cookie-consent" id="cookieConsent">
        <p>We use cookies to ensure you get the best experience on our website. By continuing to use our site, you accept our use of cookies.</p>
        <button class="btn btn-primary" id="acceptCookies">Accept</button>
    </div>

    <!-- Header -->
    <header class="header" id="header">
        <div class="nav-container">
            <a href="#home" class="logo">
                <img src="<?= getSecureImagePath('images/bcpdo.png') ?>" alt="BCPDO Logo">
                <div class="logo-text">BCPDO</div>
            </a>
            <button class="hamburger" id="hamburger">
                <i class="fas fa-bars"></i>
            </button>
            <nav class="nav-links" id="navLinks">
                <a href="#home">Home</a>
                <a href="#features">Features</a>
                <a href="#faq">FAQ</a>
                <a href="#about">About</a>
                <a href="#contact">Contact</a>
            </nav>
            <div class="auth-buttons">
                <a href="#" class="btn btn-outline" onclick="openModal('coupleModal')">Couple Registration</a>
                <a href="#" class="btn btn-primary" onclick="openModal('adminModal')">Login</a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content animate-fadeInUp">
            <h1>Bago City Population Development Office</h1>
            <p>Providing comprehensive pre-marriage orientation and counseling services to prepare couples for a successful married life. Our professional counselors guide couples through essential topics for building strong, healthy relationships and families in Bago City.</p>
            <div class="hero-buttons">
                <a href="#features" class="btn btn-white btn-large">Explore Features</a>
                <a href="#about" class="btn btn-outline btn-large">Learn More</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <h2 class="section-title">Pre-Marriage Orientation and Counseling Services</h2>
            <p class="section-subtitle">Our comprehensive pre-marriage orientation program equips couples with essential knowledge and skills for a successful married life</p>

            <div class="features-grid">
                <div class="feature-card animate-fadeInUp">
                    <div class="feature-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Pre-Marriage Orientation</h3>
                    <p>Comprehensive orientation sessions covering relationship foundations, communication skills, and preparation for married life.</p>
                </div>
                <div class="feature-card animate-fadeInUp">
                    <div class="feature-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3>Relationship Counseling</h3>
                    <p>Professional counseling sessions to address relationship concerns and strengthen couple bonds before marriage.</p>
                </div>
                <div class="feature-card animate-fadeInUp">
                    <div class="feature-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3>Family Planning Education</h3>
                    <p>Educational sessions on responsible parenthood, family planning methods, and reproductive health awareness.</p>
                </div>
                <div class="feature-card animate-fadeInUp">
                    <div class="feature-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>Counseling Records</h3>
                    <p>Secure management of counseling sessions, progress tracking, and completion certificates for pre-marriage requirements.</p>
                </div>
                <div class="feature-card animate-fadeInUp">
                    <div class="feature-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <h3>Professional Counselors</h3>
                    <p>Licensed counselors and social workers providing expert guidance on marriage preparation and relationship building.</p>
                </div>
                <div class="feature-card animate-fadeInUp">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>Flexible Scheduling</h3>
                    <p>Convenient appointment booking system with flexible schedules to accommodate couples' availability.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq" id="faq">
        <div class="container">
            <h2 class="section-title">Frequently Asked Questions</h2>
            <p class="section-subtitle">Find answers to common questions about our pre-marriage counseling program</p>

            <div class="faq-container">
                <div class="faq-item">
                    <div class="faq-question">Is pre-marriage counseling mandatory in Bago City?</div>
                    <div class="faq-answer">
                        <p>Yes, the City Ordinance requires all couples applying for a marriage license to complete the pre-marriage counseling program. This ensures couples are well-prepared for married life.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">How many sessions are required?</div>
                    <div class="faq-answer">
                        <p>The standard program consists of 4 sessions covering different aspects of married life. Each session lasts approximately 2 hours.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">What topics are covered in the counseling?</div>
                    <div class="faq-answer">
                        <p>Topics include communication skills, conflict resolution, financial management, family planning, legal aspects of marriage, and building healthy relationships.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">How do we schedule our sessions?</div>
                    <div class="faq-answer">
                        <p>After registering, you'll receive an access code to log in to our system where you can view available time slots and schedule your sessions.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">Is there a fee for the counseling service?</div>
                    <div class="faq-answer">
                        <p>The basic counseling service is free for Bago City residents. There may be minimal fees for additional services or materials.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about" id="about">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2>About BCPDO Pre-Marriage Program</h2>
                    <p>The Bago City Population Development Office (BCPDO) Pre-Marriage Orientation and Counseling System is a comprehensive digital platform designed to modernize and streamline pre-marriage counseling services for couples in Bago City.</p>
                    <p>This capstone project demonstrates the integration of modern web technologies to create an efficient, secure, and user-friendly system that serves both couples and counselors in managing pre-marriage orientation sessions, counseling appointments, and progress tracking.</p>
                    <p>Built with PHP, MySQL, and modern web technologies, the system provides a robust foundation for managing counseling services while ensuring confidentiality, data security, and seamless appointment scheduling for pre-marriage requirements.</p>
                    <a href="#contact" class="btn btn-primary">Schedule Counseling</a>
                </div>
                <div class="about-image">
                    <img src="images/mytmcc.jpg" alt="BCPDO Office" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>BCPDO Pre-Marriage Program</h3>
                    <p>Building stronger marriages through comprehensive pre-marriage orientation and counseling services for couples in Bago City.</p>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <a href="#home">Home</a>
                    <a href="#features">Services</a>
                    <a href="#faq">FAQ</a>
                    <a href="#about">About</a>
                    <a href="#contact">Contact</a>
                </div>
                <div class="footer-section">
                    <h3>Counseling Services</h3>
                    <a href="#">Pre-Marriage Orientation</a>
                    <a href="#">Relationship Counseling</a>
                    <a href="#">Family Planning Education</a>
                    <a href="#">Appointment Scheduling</a>
                    <a href="#">Counselor Directory</a>
                </div>
                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <p><i class="fas fa-map-marker-alt"></i> Bago City Population Office</p>
                    <p><i class="fas fa-phone"></i> (034) 461-2345</p>
                    <p><i class="fas fa-envelope"></i> premarriage@bcpdo.bagocity.gov.ph</p>
                    <p><i class="fas fa-clock"></i> Mon-Fri: 8:00 AM - 5:00 PM</p>
                </div>
            </div>
            <div class="footer-bottom">
                <?php include 'includes/footer.php' ?>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <div class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </div>

    <!-- Couple Modal -->
    <div id="coupleModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('coupleModal')">&times;</span>
            <h3>Couple Registration</h3>
            <p>Enter your pre-marriage counseling access code</p>
            <?php if (isset($_SESSION['couple_error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_SESSION['couple_error']); ?>
                </div>
                <?php unset($_SESSION['couple_error']); ?>
            <?php endif; ?>
            <form action="verify_access_code.php" method="POST" id="coupleForm">
                <div class="form-group">
                    <input type="text" name="access_code" class="form-control"
                        placeholder="Enter Code (e.g., BCPDO-20241224-001)" autocomplete="off" required>
                </div>
                <div class="form-group">
                    <select name="respondent_type" class="form-control" required>
                        <option value="">Select Respondent</option>
                        <option value="male">Male Partner</option>
                        <option value="female">Female Partner</option>
                    </select>
                </div>
                <button type="submit" class="btn-modal" id="coupleSubmit">
                    CONTINUE
                    <span class="spinner" id="coupleSpinner"></span>
                </button>
            </form>
        </div>
    </div>

    <!-- Admin Login Modal -->
    <div id="adminModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('adminModal')">&times;</span>
            <h3>Login</h3>
            <p>Enter your account to continue</p>

            <?php if (isset($_SESSION['login_error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_SESSION['login_error']); ?>
                </div>
                <?php unset($_SESSION['login_error']); ?>
            <?php endif; ?>

            <form action="login.php" method="POST" id="adminForm">
                <div class="form-group">
                    <input type="text" name="username" class="form-control" placeholder="Username" autocomplete="off" required>
                </div>
                <div class="form-group" style="position: relative;">
                    <input type="password" name="password" class="form-control" placeholder="Password" required id="adminPassword">
                    <span class="password-toggle" onclick="togglePassword('adminPassword', this)">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
                <div class="form-group" style="text-align: right;">
                    <a href="#" onclick="openForgotPassword()" style="color: var(--primary-color); font-size: 0.9rem;">Forgot Password?</a>
                </div>
                <button type="submit" class="btn-modal" id="adminSubmit">
                    SIGN IN
                    <span class="spinner" id="adminSpinner"></span>
                </button>
            </form>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotModal" class="modal">
        <div class="modal-content">
            <div class="logo-container">
                <img src="<?= getSecureImagePath('images/bcpdo.png') ?>" alt="BCPDO Logo">
            </div>
            <span class="close" onclick="closeModal('forgotModal')">&times;</span>
            <h3>Reset Password</h3>
            <p>Enter your email address to receive a password reset link</p>

            <form id="forgotForm">
                <div class="form-group">
                    <input type="email" name="email" class="form-control" placeholder="Enter your email address" required>
                </div>
                <button type="submit" class="btn-modal" id="forgotSubmit">
                    SEND RESET LINK
                    <span class="spinner" id="forgotSpinner"></span>
                </button>
            </form>
            <p style="text-align: center; margin-top: 1rem;">
                Remember your password? <a href="#" onclick="openAdminLogin()" style="color: var(--primary-color);">Login here</a>
            </p>
        </div>
    </div>

    <script>
        // Main content anchor for skip link
        document.addEventListener('DOMContentLoaded', function() {
            const mainContent = document.createElement('div');
            mainContent.id = 'main-content';
            mainContent.tabIndex = -1;
            document.body.insertBefore(mainContent, document.body.firstChild);

            // Show cookie consent if not accepted
            if (!localStorage.getItem('cookieConsent')) {
                setTimeout(() => {
                    document.getElementById('cookieConsent').classList.add('show');
                }, 1000);
            }

            // Check URL parameters and open modal if needed
            const urlParams = new URLSearchParams(window.location.search);
            const showAdminModal = urlParams.get('show_admin_modal');

            if (showAdminModal === '1' || <?php echo isset($_SESSION['login_error']) ? 'true' : 'false'; ?>) {
                openModal('adminModal');
            }
        });

        // Cookie consent
        document.getElementById('acceptCookies').addEventListener('click', function() {
            localStorage.setItem('cookieConsent', 'accepted');
            document.getElementById('cookieConsent').classList.remove('show');
        });

        // Mobile menu toggle
        const hamburger = document.getElementById('hamburger');
        const navLinks = document.getElementById('navLinks');

        hamburger.addEventListener('click', function() {
            navLinks.classList.toggle('active');
            hamburger.innerHTML = navLinks.classList.contains('active') ?
                '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
        });

        // Close mobile menu when clicking a link
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', function() {
                navLinks.classList.remove('active');
                hamburger.innerHTML = '<i class="fas fa-bars"></i>';
            });
        });

        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Back to top button
        const backToTop = document.getElementById('backToTop');

        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });

        backToTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Modal functionality
        function openModal(modalId) {
            const modal = document.getElementById(modalId);

            // Reset modal state when opened
            modal.style.display = 'block';
            setTimeout(() => {
                modal.classList.add('show');

                // Focus on first input for better UX
                const firstInput = modal.querySelector('input, select, textarea');
                if (firstInput) {
                    firstInput.focus();
                }
            }, 10);

            document.body.style.overflow = 'hidden';

            // Special handling for specific modals
            switch (modalId) {
                // In the openModal function's case 'coupleModal':
                case 'coupleModal':
                    // Reset form
                    const coupleForm = document.getElementById('coupleForm');
                    if (coupleForm) {
                        coupleForm.reset();

                        // Clear any existing error messages
                        const errorAlerts = modal.querySelectorAll('.alert-danger');
                        errorAlerts.forEach(alert => alert.remove());
                    }
                    break;

                case 'adminModal':
                    // Clear URL parameter if present
                    if (window.location.search.includes('show_admin_modal=1')) {
                        const newUrl = window.location.pathname +
                            window.location.search.replace(/[?&]show_admin_modal=1/, '');
                        window.history.replaceState({}, document.title, newUrl);
                    }

                    // Reset form
                    const adminForm = document.getElementById('adminForm');
                    if (adminForm) {
                        adminForm.reset();

                        // Clear any existing error messages
                        const errorAlerts = modal.querySelectorAll('.alert-danger');
                        errorAlerts.forEach(alert => alert.remove());

                        // Reset password field visibility
                        const passwordInput = document.getElementById('adminPassword');
                        if (passwordInput) {
                            passwordInput.type = 'password';
                            const eyeIcon = modal.querySelector('.password-toggle i');
                            if (eyeIcon) {
                                eyeIcon.className = 'fas fa-eye';
                            }
                        }
                    }
                    break;

                case 'forgotModal':
                    // Reset form if exists
                    const forgotForm = document.getElementById('forgotForm');
                    if (forgotForm) forgotForm.reset();
                    break;
            }

            // Close other open modals
            document.querySelectorAll('.modal').forEach(otherModal => {
                if (otherModal.id !== modalId && otherModal.style.display === 'block') {
                    closeModal(otherModal.id);
                }
            });
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';

                // Clear errors when closing
                const errorAlerts = modal.querySelectorAll('.alert-danger');
                errorAlerts.forEach(alert => alert.remove());

                // Reset specific modal states
                if (modalId === 'coupleModal') {
                    const statusDiv = document.getElementById('respondentStatus');
                    if (statusDiv) statusDiv.style.display = 'none';
                }

                if (modalId === 'adminModal') {
                    const passwordInput = document.getElementById('adminPassword');
                    if (passwordInput) passwordInput.type = 'password';
                }
            }, 300);
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target === modals[i]) {
                    modals[i].classList.remove('show');
                    setTimeout(() => {
                        modals[i].style.display = 'none';
                    }, 300);
                    document.body.style.overflow = 'auto';
                }
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = document.getElementsByClassName('modal');
                for (let i = 0; i < modals.length; i++) {
                    if (modals[i].style.display === 'block') {
                        modals[i].classList.remove('show');
                        setTimeout(() => {
                            modals[i].style.display = 'none';
                        }, 300);
                        document.body.style.overflow = 'auto';
                    }
                }
            }
        });

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');

                // Ignore plain '#', empty, or invalid selectors (e.g., buttons using href="#")
                if (!href || href === '#') {
                    // Let any explicit onclick handler (e.g., openModal) run
                    e.preventDefault();
                    return;
                }

                // Attempt smooth scroll if a valid target exists
                let target = null;
                try {
                    target = document.querySelector(href);
                } catch (err) {
                    // Invalid selector - do nothing
                }

                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // FAQ accordion
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', function() {
                this.classList.toggle('active');
                const answer = this.nextElementSibling;
                answer.classList.toggle('show');
            });
        });

        // Add animation class when elements come into view
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fadeInUp');
                }
            });
        }, observerOptions);

        // AJAX form submission for admin login
        document.getElementById('adminForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('adminSubmit');
            const spinner = document.getElementById('adminSpinner');
            const modalContent = document.querySelector('#adminModal .modal-content');

            submitBtn.disabled = true;
            spinner.style.display = 'inline-block';

            // Clear any existing error messages
            const errorAlert = modalContent.querySelector('.alert-danger');
            if (errorAlert) {
                errorAlert.remove();
            }

            // Submit form via AJAX
            const formData = new FormData(this);

            fetch('login.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    submitBtn.disabled = false;
                    spinner.style.display = 'none';

                    if (data.success) {
                        window.location.href = data.redirect;
                    } else {
                        // Show error message
                        const errorHtml = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            ${data.message}
                        </div>
                    `;
                        modalContent.insertAdjacentHTML('afterbegin', errorHtml);

                        // Shake the modal for emphasis
                        modalContent.style.animation = 'shake 0.5s';
                        setTimeout(() => {
                            modalContent.style.animation = '';
                        }, 500);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    submitBtn.disabled = false;
                    spinner.style.display = 'none';

                    const errorHtml = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        Network error occurred. Please try again.
                    </div>
                `;
                    modalContent.insertAdjacentHTML('afterbegin', errorHtml);
                });
        });

        // Replace the existing couple form submission handler with this:
        document.getElementById('coupleForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('coupleSubmit');
            const spinner = document.getElementById('coupleSpinner');
            const modalContent = document.querySelector('#coupleModal .modal-content');
            const accessCodeInput = document.querySelector('input[name="access_code"]');
            const respondentSelect = document.querySelector('select[name="respondent_type"]');

            // Clear any existing error messages
            const errorAlert = modalContent.querySelector('.alert-danger');
            if (errorAlert) {
                errorAlert.remove();
            }

            // Client-side validation
            const accessCode = accessCodeInput.value.trim();
            const respondent = respondentSelect.value;

            // Debug: Log the access code length
            console.log('Access code:', accessCode);
            console.log('Access code length:', accessCode.length);
            console.log('Access code characters:', Array.from(accessCode).map(c => c.charCodeAt(0)));

            // Validate access code format - CACHE BUST: 2025-01-10
            if (!accessCode.startsWith('BCPDO-')) {
                const errorHtml = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        Access code must start with "BCPDO-"
                    </div>
                `;
                modalContent.insertAdjacentHTML('afterbegin', errorHtml);
                accessCodeInput.focus();
                return;
            }

            // Validate access code length (BCPDO-YYYYMMDD-SSS-X = 20 characters)
            // Supports both numeric (001-999) and alphanumeric (A00-Z99) sequences
            if (accessCode.length !== 20) {
                const errorHtml = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        Access code should be 20 characters long (e.g., BCPDO-20241224-001-A). Current length: ${accessCode.length}
                    </div>
                `;
                modalContent.insertAdjacentHTML('afterbegin', errorHtml);
                accessCodeInput.focus();
                return;
            }

            // Validate access code format (BCPDO-YYYYMMDD-SSS-X)
            const accessCodePattern = /^BCPDO-\d{8}-\d{3}-[A-Z0-9]$/;
            if (!accessCodePattern.test(accessCode)) {
                const errorHtml = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        Access code format should be BCPDO-YYYYMMDD-SSS-X (e.g., BCPDO-20241224-001-A)
                    </div>
                `;
                modalContent.insertAdjacentHTML('afterbegin', errorHtml);
                accessCodeInput.focus();
                return;
            }

            // Validate respondent selection
            if (!respondent) {
                const errorHtml = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        Please select whether you are the Male or Female partner
                    </div>
                `;
                modalContent.insertAdjacentHTML('afterbegin', errorHtml);
                respondentSelect.focus();
                return;
            }

            submitBtn.disabled = true;
            spinner.style.display = 'inline-block';

            // Submit form via AJAX
            const formData = new FormData(this);

            fetch('verify_access_code.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    submitBtn.disabled = false;
                    spinner.style.display = 'none';

                    if (data.success) {
                        window.location.href = data.redirect;
                    } else {
                        // Show error message in the same style as admin modal
                        const errorHtml = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                ${data.message}
            </div>
        `;
                        modalContent.insertAdjacentHTML('afterbegin', errorHtml);

                        // Shake the modal for emphasis
                        modalContent.style.animation = 'shake 0.5s';
                        setTimeout(() => {
                            modalContent.style.animation = '';
                        }, 500);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    submitBtn.disabled = false;
                    spinner.style.display = 'none';

                    const errorHtml = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    Network error occurred. Please try again.
                </div>
            `;
                    modalContent.insertAdjacentHTML('afterbegin', errorHtml);
                });
        });

        document.getElementById('forgotForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById('forgotSubmit');
            const spinner = document.getElementById('forgotSpinner');
            const modalContent = document.querySelector('#forgotModal .modal-content');

            submitBtn.disabled = true;
            spinner.style.display = 'inline-block';

            // Clear any existing error messages
            const errorAlert = modalContent.querySelector('.alert-danger');
            if (errorAlert) {
                errorAlert.remove();
            }

            // Simulate form submission with better UX
            setTimeout(() => {
                showNotification('If an account exists with this email, a password reset link has been sent.', 'info');
                closeModal('forgotModal');
                submitBtn.disabled = false;
                spinner.style.display = 'none';
            }, 1500);
        });

        // Toggle password visibility
        function togglePassword(inputId, toggle) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                toggle.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                input.type = 'password';
                toggle.innerHTML = '<i class="fas fa-eye"></i>';
            }
        }

        // Forgot password modal
        function openForgotPassword() {
            closeModal('adminModal');
            setTimeout(() => {
                openModal('forgotModal');
            }, 300);
        }

        function openAdminLogin() {
            closeModal('forgotModal');
            setTimeout(() => {
                openModal('adminModal');
            }, 300);
        }

        // Complete registration success message
        <?php if (isset($_GET['complete']) && $_GET['complete'] == '1'): ?>
            // Use a more user-friendly notification instead of alert
            document.addEventListener('DOMContentLoaded', function() {
                showNotification('Both partners have successfully submitted their profiles. The admin will contact you for the next steps.', 'success');
            });
        <?php endif; ?>

        // Enhanced notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="notification-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            // Add notification styles if not already present
            if (!document.getElementById('notification-styles')) {
                const style = document.createElement('style');
                style.id = 'notification-styles';
                style.textContent = `
                    .notification {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        z-index: 10000;
                        max-width: 400px;
                        animation: slideInRight 0.3s ease-out;
                    }
                    .notification-content {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        padding: 15px 20px;
                        border-radius: 8px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                        color: white;
                    }
                            .notification-success { background: var(--success-color); }
        .notification-error { background: var(--danger-color); }
        .notification-info { background: var(--info-color); }
        .notification-warning { background: var(--warning-color); color: #000; }
                    .notification-close {
                        background: none;
                        border: none;
                        color: white;
                        cursor: pointer;
                        margin-left: auto;
                    }
                    @keyframes slideInRight {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                `;
                document.head.appendChild(style);
            }
            
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }
    </script>
</body>

</html>