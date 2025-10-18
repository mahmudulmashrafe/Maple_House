<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maple House - Compassionate Senior Care & Old Age Home Management</title>
    <meta name="description" content="Maple House provides premium senior care services with 24/7 medical support, nutritious meals, and comfortable living for elderly residents in Bangladesh.">
    <meta name="keywords" content="old age home, senior care, elderly care, nursing home, medical care, Bangladesh">
    <link rel="icon" type="image/jpeg" href="images/favicon.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="images/favicon.jpg">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Enhanced minimal and informative styles */
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --light-bg: #f8f9fa;
            --dark-text: #2c3e50;
            --light-text: #6c757d;
            --border-color: #e9ecef;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--dark-text);
            margin: 0;
            padding: 0;
        }

        /* Enhanced Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-logo h2 {
            color: var(--primary-color);
            margin: 0;
            font-weight: 600;
        }

        .nav-logo i {
            color: var(--accent-color);
        }

        .nav-menu {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 2rem;
        }

        .nav-menu a {
            text-decoration: none;
            color: var(--dark-text);
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-menu a:hover {
            color: var(--secondary-color);
        }

        .donate-btn, .login-btn {
            padding: 0.5rem 1rem !important;
            border-radius: 25px !important;
            font-weight: 600 !important;
        }

        .donate-btn {
            background: var(--accent-color) !important;
            color: white !important;
        }

        .login-btn {
            background: var(--secondary-color) !important;
            color: white !important;
        }

        /* Enhanced Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            z-index: 0;
            filter: blur(2px);
        }

        .hero-bg-1 {
            background: #f8f9fa url('images/bg-1.jpg') center/cover no-repeat;
            opacity: 1;
            animation: imageAnimation1 15s infinite;
        }

        .hero-bg-2 {
            background: #f8f9fa url('images/bg-2.jpg') center/cover no-repeat;
            opacity: 0;
            animation: imageAnimation2 15s infinite;
        }

        .hero-bg-3 {
            background: #f8f9fa url('images/bg-3.jpg') center/cover no-repeat;
            opacity: 0;
            animation: imageAnimation3 15s infinite;
        }

        @keyframes imageAnimation1 {
            0% { opacity: 1; }
            33% { opacity: 1; }
            34% { opacity: 0; }
            100% { opacity: 0; }
        }

        @keyframes imageAnimation2 {
            0% { opacity: 0; }
            33% { opacity: 0; }
            34% { opacity: 1; }
            66% { opacity: 1; }
            67% { opacity: 0; }
            100% { opacity: 0; }
        }

        @keyframes imageAnimation3 {
            0% { opacity: 0; }
            66% { opacity: 0; }
            67% { opacity: 1; }
            100% { opacity: 1; }
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5));
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.95;
            line-height: 1.8;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Stats Section */
        .stats-section {
            background: var(--light-bg);
            padding: 4rem 0;
            margin-top: -50px;
            position: relative;
            z-index: 3;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--secondary-color);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--light-text);
            font-weight: 500;
        }

        /* Enhanced Sections */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        section {
            padding: 5rem 0;
        }

        section h2 {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: var(--primary-color);
            font-weight: 600;
        }

        /* Enhanced Service Cards */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .service-card {
            background: white;
            padding: 2.5rem 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-top: 4px solid var(--secondary-color);
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .service-card i {
            font-size: 3rem;
            color: var(--secondary-color);
            margin-bottom: 1.5rem;
        }

        .service-card h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.4rem;
        }

        /* Enhanced Plan Cards */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .plan-card {
            background: white;
            padding: 2.5rem 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .plan-card:hover {
            transform: translateY(-5px);
        }

        .plan-card.highlighted-blue:hover {
            border: 2px solid var(--secondary-color);
            box-shadow: 0 0 0 1px var(--secondary-color);
        }

        .plan-card.highlighted-red:hover {
            border: 2px solid var(--accent-color);
            box-shadow: 0 0 0 1px var(--accent-color);
        }

        .plan-card h3 {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .price {
            font-size: 2rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 2rem;
        }

        .price .month-text {
            font-size: 0.8rem;
            font-weight: 400;
            opacity: 0.8;
        }

        .plan-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .plan-card li {
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
            color: var(--light-text);
            padding-left: 0 !important;
            position: relative;
        }

        .plan-card li:before {
            content: none !important;
            display: none !important;
        }

        .plan-card li:last-child {
            border-bottom: none;
        }

        /* Button Enhancements */
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--secondary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-secondary:hover {
            background: white;
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        /* Enhanced Contact Section */
        .contact {
            background: var(--light-bg);
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-top: 3rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .contact-item i {
            font-size: 2rem;
            color: var(--secondary-color);
            margin-right: 1rem;
            width: 50px;
        }

        .contact-form {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--secondary-color);
        }

        /* Footer Enhancement */
        .footer {
            background: var(--primary-color);
            color: white;
            padding: 3rem 0 1rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h3,
        .footer-section h4 {
            margin-bottom: 1rem;
            color: white;
        }

        .footer-section ul {
            list-style: none;
            padding: 0;
        }

        .footer-section ul li {
            margin-bottom: 0.5rem;
        }

        .footer-section ul li a {
            color: #bdc3c7;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-section ul li a:hover {
            color: white;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid #34495e;
            color: #bdc3c7;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.1rem;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .nav-menu {
                display: none;
            }

            .contact-grid {
                grid-template-columns: 1fr;
            }

            .plans-grid {
                grid-template-columns: 1fr;
            }

            .plan-card.highlighted-blue,
            .plan-card.highlighted-red {
                transform: none;
            }
        }

        /* Enhanced About Section */
        .about-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: start;
            margin-top: 3rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            background: var(--light-bg);
            border-radius: 10px;
            transition: transform 0.3s ease;
        }

        .feature-item:hover {
            transform: translateY(-2px);
        }

        .feature-item i {
            font-size: 1.5rem;
            color: var(--secondary-color);
            margin-top: 0.2rem;
        }

        .feature-item h4 {
            margin: 0 0 0.5rem 0;
            color: var(--primary-color);
            font-size: 1rem;
        }

        .feature-item p {
            margin: 0;
            color: var(--light-text);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .trust-indicators {
            display: flex;
            gap: 1.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .trust-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.7rem 1rem;
            background: white;
            border-radius: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--primary-color);
        }

        .trust-item i {
            color: var(--success-color);
            font-size: 1rem;
        }

        .image-placeholder {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 3rem 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .image-placeholder i {
            font-size: 4rem;
            color: var(--accent-color);
            margin-bottom: 1.5rem;
        }

        .image-placeholder h4 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .image-placeholder p {
            color: var(--light-text);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .mission-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin: 2rem 0;
        }

        .mission-stat {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .mission-stat .number {
            display: block;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 0.3rem;
        }

        .mission-stat .label {
            font-size: 0.8rem;
            color: var(--light-text);
            font-weight: 500;
        }

        .about-cta {
            margin-top: 1.5rem;
        }

        /* Accessibility */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <h2><i class="fas fa-home"></i> Maple House</h2>
            </div>
            <ul class="nav-menu">
                <li><a href="#home">Home</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="donate.php" class="donate-btn">Donate</a></li>
                <li><a href="login.php" class="login-btn">Login</a></li>
            </ul>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-bg hero-bg-1"></div>
        <div class="hero-bg hero-bg-2"></div>
        <div class="hero-bg hero-bg-3"></div>
        <div class="hero-content">
            <h1>Welcome to Maple House</h1>
            <p>A caring community for senior citizens providing comprehensive care, health services, and a comfortable home environment.</p>
            <div class="hero-buttons">
                <a href="#about" class="btn btn-primary">Learn More</a>
                <a href="donate.php" class="btn btn-secondary">Make a Donation</a>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number">150+</div>
                    <div class="stat-label">Happy Residents</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="stat-number">25+</div>
                    <div class="stat-label">Medical Staff</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Care Available</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-number">10+</div>
                    <div class="stat-label">Years Experience</div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about">
        <div class="container">
            <h2>About Maple House</h2>
            <div class="about-grid">
                <div class="about-text">
                    <p>Maple House is a premier senior care facility in Bangladesh, providing dignified living and comprehensive healthcare for elderly residents. With over a decade of experience, we combine modern medical technology with traditional Bangladeshi values of family care and respect for elders.</p>
                    
                    <div class="features-grid">
                        <div class="feature-item">
                            <i class="fas fa-user-md"></i>
                            <div>
                                <h4>Licensed Medical Staff</h4>
                                <p>Qualified doctors, nurses, and therapists available round-the-clock</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-shield-alt"></i>
                            <div>
                                <h4>Safety & Security</h4>
                                <p>24/7 security, emergency response, and safety monitoring systems</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-utensils"></i>
                            <div>
                                <h4>Nutritious Meals</h4>
                                <p>Doctor-approved Bengali cuisine prepared by professional chefs</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-wifi"></i>
                            <div>
                                <h4>Modern Amenities</h4>
                                <p>WiFi, family communication, recreation facilities, and more</p>
                            </div>
                        </div>
                    </div>
                    
                    
                </div>
                <div class="about-image">
                    <div class="image-placeholder">
                        <i class="fas fa-heart"></i>
                        <h4>Our Mission</h4>
                        <p><strong>"Providing dignified care with family-like love and professional medical support for every elderly resident."</strong></p>
                        
                        <div class="mission-stats">
                            <div class="mission-stat">
                                <span class="number">98%</span>
                                <span class="label">Family Satisfaction</span>
                            </div>
                            <div class="mission-stat">
                                <span class="number">4.8/5</span>
                                <span class="label">Care Rating</span>
                            </div>
                        </div>
                        
                        <div class="about-cta">
                            <a href="#contact" class="btn btn-primary">Schedule a Visit</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="services">
        <div class="container">
            <h2>Our Services</h2>
            <div class="services-grid">
                <div class="service-card">
                    <i class="fas fa-user-md"></i>
                    <h3>Medical Care</h3>
                    <p>Professional medical staff available 24/7 with regular health monitoring and emergency care.</p>
                </div>
                <div class="service-card">
                    <i class="fas fa-utensils"></i>
                    <h3>Nutrition & Meals</h3>
                    <p>Doctor-approved meal plans prepared by professional chefs with dietary restrictions consideration.</p>
                </div>
                <div class="service-card">
                    <i class="fas fa-hands-helping"></i>
                    <h3>Daily Care</h3>
                    <p>Laundry, room cleaning, transportation, and personal care services based on your plan.</p>
                </div>
                <div class="service-card">
                    <i class="fas fa-heart"></i>
                    <h3>Mental Health</h3>
                    <p>Professional psychological support and mental health monitoring for overall wellbeing.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Plans Section -->
    <section class="plans">
        <div class="container">
            <h2>Care Plans</h2>
            <div class="plans-grid">
                <div class="plan-card highlighted-blue">
                    <h3>Basic Plan</h3>
                    <div class="price">Free</div>
                    <ul>
                        <li>Basic accommodation</li>
                        <li>3 meals per day</li>
                        <li>Limited services</li>
                        <li>Basic medical care</li>
                    </ul>
                </div>
                <div class="plan-card">
                    <h3>Plan 1</h3>
                    <div class="price">৳15,000<span class="month-text">/month</span></div>
                    <ul>
                        <li>Enhanced room</li>
                        <li>50 free laundry items</li>
                        <li>20 free room cleanings</li>
                        <li>Priority medical care</li>
                    </ul>
                </div>
                <div class="plan-card">
                    <h3>Plan 2</h3>
                    <div class="price">৳25,000<span class="month-text">/month</span></div>
                    <ul>
                        <li>Premium room</li>
                        <li>100 free laundry items</li>
                        <li>25 free room cleanings</li>
                        <li>24/7 medical support</li>
                    </ul>
                </div>
                <div class="plan-card highlighted-red">
                    <h3>Plan 3</h3>
                    <div class="price">৳35,000<span class="month-text">/month</span></div>
                    <ul>
                        <li>Deluxe room</li>
                        <li>150 free laundry items</li>
                        <li>30 free room cleanings</li>
                        <li>Personal care assistant</li>
                    </ul>
                </div>
                <div class="plan-card">
                    <h3>Plan 4</h3>
                    <div class="price">৳50,000<span class="month-text">/month</span></div>
                    <ul>
                        <li>Suite accommodation</li>
                        <li>Unlimited services</li>
                        <li>Daily room cleaning</li>
                        <li>Dedicated medical team</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact">
        <div class="container">
            <h2>Contact Us</h2>
            <div class="contact-grid">
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <h4>Address</h4>
                            <p>H - 92, United City, Madani Ave, Dhaka 1212</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <h4>Phone</h4>
                            <p>+880 1714 567890</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <h4>Email</h4>
                            <p>info@maplehouse.com</p>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($_GET['contact'])): ?>
                <div class="contact-alert" style="margin-bottom: 20px; padding: 15px; border-radius: 8px; <?= $_GET['contact'] === 'success' ? 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;' ?>">
                    <i class="fas <?= $_GET['contact'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                    <?= htmlspecialchars($_GET['msg'] ?? ($_GET['contact'] === 'success' ? 'Message sent successfully!' : 'An error occurred.')) ?>
                </div>
                <?php endif; ?>
                
                <form class="contact-form" id="contactForm" action="contact_process.php" method="POST">
                    <div class="form-group">
                        <input type="text" name="name" placeholder="Your Name" required>
                    </div>
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Your Email" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="subject" placeholder="Subject" required>
                    </div>
                    <div class="form-group">
                        <textarea name="message" placeholder="Your Message" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <span id="submitText">Send Message</span>
                        <span id="submitLoader" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i> Sending...
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Maple House</h3>
                    <p>Providing compassionate care and a loving home for our elderly community.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="donate.php">Donate</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact Info</h4>
                    <p><i class="fas fa-phone"></i> +880 1234 567890</p>
                    <p><i class="fas fa-envelope"></i> info@maplehouse.com</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Maple House. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
    
    <!-- Contact Form JavaScript -->
    <script>
        // Popup notification styles
        const popupStyles = `
            .popup-notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 20px 25px;
                border-radius: 8px;
                color: white;
                font-weight: 600;
                font-size: 16px;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                transform: translateX(100%);
                transition: transform 0.3s ease;
                max-width: 400px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .popup-notification.success {
                background: linear-gradient(135deg, #28a745, #20c997);
            }
            .popup-notification.error {
                background: linear-gradient(135deg, #dc3545, #e74c3c);
            }
            .popup-notification.show {
                transform: translateX(0);
            }
            .popup-notification .close-btn {
                margin-left: auto;
                cursor: pointer;
                font-size: 18px;
                opacity: 0.8;
            }
            .popup-notification .close-btn:hover {
                opacity: 1;
            }
        `;
        
        // Add styles to head
        const styleSheet = document.createElement('style');
        styleSheet.textContent = popupStyles;
        document.head.appendChild(styleSheet);
        
        // Show popup notification
        function showPopup(message, type = 'success') {
            // Remove any existing popups
            const existingPopup = document.querySelector('.popup-notification');
            if (existingPopup) {
                existingPopup.remove();
            }
            
            // Create popup
            const popup = document.createElement('div');
            popup.className = `popup-notification ${type}`;
            popup.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'}"></i>
                <span>${message}</span>
                <span class="close-btn" onclick="this.parentElement.remove()">&times;</span>
            `;
            
            // Add to page
            document.body.appendChild(popup);
            
            // Show with animation
            setTimeout(() => popup.classList.add('show'), 100);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                if (popup.parentElement) {
                    popup.classList.remove('show');
                    setTimeout(() => popup.remove(), 300);
                }
            }, 5000);
        }
        
        // Handle contact form submission
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const submitLoader = document.getElementById('submitLoader');
            const formData = new FormData(form);
            
            // Show loading state
            submitBtn.disabled = true;
            submitText.style.display = 'none';
            submitLoader.style.display = 'inline';
            
            // Send AJAX request
            fetch('contact_process.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showPopup(data.message || 'Thank you for your message! We will get back to you soon.', 'success');
                    form.reset(); // Clear the form
                } else {
                    showPopup(data.message || 'Sorry, there was an error sending your message. Please try again.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showPopup('Sorry, there was an error sending your message. Please try again.', 'error');
            })
            .finally(() => {
                // Reset button state
                submitBtn.disabled = false;
                submitText.style.display = 'inline';
                submitLoader.style.display = 'none';
            });
        });
        
        // Handle URL parameters for backward compatibility
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('contact')) {
            const isSuccess = urlParams.get('contact') === 'success';
            const message = urlParams.get('msg') || (isSuccess ? 'Message sent successfully!' : 'An error occurred.');
            showPopup(message, isSuccess ? 'success' : 'error');
            
            // Clean URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    </script>
</body>
</html>
