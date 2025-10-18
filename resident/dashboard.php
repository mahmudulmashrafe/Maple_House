<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a resident
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get resident information
$resident_query = "SELECT u.*, r.*, pp.plan_name, pp.monthly_fee, pp.laundry_limit, pp.cleaning_limit
                   FROM users u 
                   JOIN residents r ON u.id = r.user_id 
                   JOIN payment_plans pp ON r.plan_id = pp.id
                   WHERE u.id = :user_id";
$resident_stmt = $db->prepare($resident_query);
$resident_stmt->bindParam(':user_id', $_SESSION['user_id']);
$resident_stmt->execute();
$resident = $resident_stmt->fetch(PDO::FETCH_ASSOC);

// Get latest health record
$health_query = "SELECT hr.*, CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                 FROM health_records hr
                 LEFT JOIN doctors doc ON hr.doctor_id = doc.id
                 LEFT JOIN users d ON doc.user_id = d.id
                 WHERE hr.resident_id = :resident_id 
                 ORDER BY hr.checkup_date DESC LIMIT 1";
$health_stmt = $db->prepare($health_query);
$health_stmt->bindParam(':resident_id', $resident['id']);
$health_stmt->execute();
$latest_health = $health_stmt->fetch(PDO::FETCH_ASSOC);

// Get service usage for current month
$current_month = date('Y-m');
$usage_query = "SELECT service_id, COUNT(*) as usage_count
                FROM service_requests sr
                JOIN services s ON sr.service_id = s.id
                WHERE sr.resident_id = :resident_id 
                AND DATE_FORMAT(sr.request_date, '%Y-%m') = :current_month
                AND sr.status = 'Completed'
                GROUP BY service_id";
$usage_stmt = $db->prepare($usage_query);
$usage_stmt->bindParam(':resident_id', $resident['id']);
$usage_stmt->bindParam(':current_month', $current_month);
$usage_stmt->execute();
$service_usage = $usage_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate health score (example calculation)
$health_score = $resident['health_score'] ?? 85;
if ($latest_health && isset($latest_health['blood_pressure'])) {
    // Parse blood pressure (e.g., "120/80")
    $bp_parts = explode('/', $latest_health['blood_pressure']);
    $systolic = isset($bp_parts[0]) ? (int)$bp_parts[0] : 0;
    $diastolic = isset($bp_parts[1]) ? (int)$bp_parts[1] : 0;
    
    // Simple calculation based on available data
    $health_score = min(100, max(0, 
        ($systolic > 0 && $systolic < 140 ? 25 : 0) +
        ($diastolic > 0 && $diastolic < 90 ? 25 : 0) +
        (isset($latest_health['heart_rate']) && $latest_health['heart_rate'] >= 60 && $latest_health['heart_rate'] <= 100 ? 25 : 0) +
        25 // Base score
    ));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Portal - Maple House</title>
    <link rel="icon" type="image/jpeg" href="../images/favicon.jpg">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: fixed;
            height: 100vh;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 30px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .logo i {
            font-size: 2rem;
            color: white;
        }

        .logo span {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
        }

        .user-info {
            text-align: center;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }

        .user-avatar i {
            font-size: 1.5rem;
            color: white;
        }

        .user-details h4 {
            color: white;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }

        .user-details p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .role-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Navigation Styles */
        .nav-menu {
            padding: 20px 0;
        }

        .nav-list {
            list-style: none;
            padding: 0;
            margin: 0;
            flex: 1;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: #3498db;
            color: white;
            text-decoration: none;
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: #3498db;
            color: white;
            font-weight: 600;
        }

        .nav-link i {
            width: 20px;
            margin-right: 15px;
            font-size: 1.1rem;
        }

        .sidebar-footer {
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 20px;
            white-space: nowrap;
        }
        
        .logout-btn {
            color: white !important;
            padding: 12px 20px !important;
            border-left: 3px solid transparent !important;
        }
        
        .logout-btn:hover {
            background: rgba(231,76,60,0.1) !important;
            border-left-color: #e74c3c !important;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 0;
            transition: all 0.3s ease;
        }

        .content-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 25px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .content-header h1 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .content-header p {
            color: #6c757d;
            font-size: 0.95rem;
        }

        .content-body {
            padding: 30px;
        }

        /* Dashboard Content */
        .dashboard-content {
            display: block;
        }

        .page-content {
            display: none;
        }

        .page-content iframe {
            width: 100%;
            height: calc(100vh - 140px);
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .stat-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .stat-card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.3rem;
            color: white;
        }

        .stat-card-icon.health {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
        }

        .stat-card-icon.plan {
            background: linear-gradient(135deg, #4834d4, #686de0);
        }

        .stat-card-icon.services {
            background: linear-gradient(135deg, #00d2d3, #54a0ff);
        }

        .stat-card-icon.meals {
            background: linear-gradient(135deg, #ff9ff3, #f368e0);
        }

        .stat-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .stat-card-subtitle {
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* Health Progress Circle */
        .health-progress {
            position: relative;
            width: 80px;
            height: 80px;
            margin-left: auto;
        }

        .progress-circle {
            width: 80px;
            height: 80px;
            transform: rotate(-90deg);
        }

        .progress-circle-bg {
            fill: none;
            stroke: #e9ecef;
            stroke-width: 8;
        }

        .progress-circle-fill {
            fill: none;
            stroke: #28a745;
            stroke-width: 8;
            stroke-linecap: round;
            stroke-dasharray: 188.4;
            stroke-dashoffset: 188.4;
            transition: stroke-dashoffset 1s ease;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 0.9rem;
            font-weight: 600;
            color: #2c3e50;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .quick-action {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s ease;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
        }

        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            text-decoration: none;
            color: #667eea;
        }

        .quick-action i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #667eea;
        }

        .quick-action span {
            display: block;
            font-weight: 600;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .content-header {
                padding: 20px;
            }

            .content-body {
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Mobile Menu Toggle */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-size: 1.2rem;
            color: #2c3e50;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .mobile-toggle {
                display: block;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-home"></i>
                    <span>Maple House</span>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></h4>
                        <p>Room <?php echo htmlspecialchars($resident['room_number']); ?></p>
                        <span class="role-badge">Resident</span>
                    </div>
                </div>
            </div>

            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="#" class="nav-link active" onclick="showDashboard(); return false;">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="loadPage('health.php'); return false;">
                        <i class="fas fa-heartbeat"></i>
                        <span>Health Records</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=meals" onclick="event.preventDefault(); loadPage('meals.php')" class="nav-link" data-page="meals">
                        <i class="fas fa-utensils"></i>
                        Meals
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=meal_preferences" onclick="event.preventDefault(); loadPage('meal_preferences.php')" class="nav-link" data-page="meal_preferences">
                        <i class="fas fa-heart"></i>
                        Meal Preferences
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="loadPage('services.php'); return false;">
                        <i class="fas fa-concierge-bell"></i>
                        <span>Services</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="loadPage('buy_services.php'); return false;">
                        <i class="fas fa-shopping-bag"></i>
                        <span>Buy Services</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="loadPage('profile.php'); return false;">
                        <i class="fas fa-user-cog"></i>
                        <span>Profile</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-footer">
                <a href="../logout.php" class="nav-link logout-btn">
                    <i class="fas fa-sign-out-alt"></i> <span class="nav-text">Logout</span>
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Content Header -->
            <div class="content-header">
                <h1 id="page-title">Dashboard</h1>
                <p id="page-subtitle">Welcome back, <?php echo htmlspecialchars($resident['first_name']); ?>! Here's your overview.</p>
            </div>

            <!-- Content Body -->
            <div class="content-body">
                <!-- Dashboard Content -->
                <div class="dashboard-content" id="dashboard-content">
                    <!-- Welcome Section -->
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 15px; color: white; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
                        <h1 style="margin: 0 0 10px 0; font-size: 2rem; font-weight: 700;">Welcome back, <?php echo htmlspecialchars($resident['first_name']); ?>! 👋</h1>
                        <p style="margin: 0; font-size: 1.1rem; opacity: 0.9;">Room <?php echo htmlspecialchars($resident['room_number']); ?> • <?php echo htmlspecialchars($resident['plan_name']); ?> Plan</p>
                    </div>

                    <!-- Stats Grid -->
                    <div class="stats-grid" style="margin-bottom: 35px;">
                        <!-- Health Card -->
                        <div class="stat-card" style="border-left: 4px solid #667eea;">
                            <div class="stat-card-header">
                                <div class="stat-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <i class="fas fa-heartbeat"></i>
                                </div>
                                <div style="flex: 1;">
                                    <div class="stat-card-title">Health Score</div>
                                    <div class="stat-card-value" style="font-size: 2rem; color: #667eea;"><?php echo $health_score; ?>%</div>
                                    <div class="stat-card-subtitle">Overall wellness</div>
                                </div>
                            </div>
                        </div>

                        <!-- Plan Card -->
                        <div class="stat-card" style="border-left: 4px solid #f093fb;">
                            <div class="stat-card-header">
                                <div class="stat-card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                    <i class="fas fa-crown"></i>
                                </div>
                                <div style="flex: 1;">
                                    <div class="stat-card-title"><?php echo htmlspecialchars($resident['plan_name']); ?></div>
                                    <div class="stat-card-value" style="font-size: 2rem; color: #f093fb;">৳<?php echo number_format($resident['monthly_fee']); ?></div>
                                    <div class="stat-card-subtitle">Monthly plan</div>
                                </div>
                            </div>
                        </div>

                        <!-- Services Card -->
                        <div class="stat-card" style="border-left: 4px solid #43e97b;">
                            <div class="stat-card-header">
                                <div class="stat-card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                                    <i class="fas fa-concierge-bell"></i>
                                </div>
                                <div style="flex: 1;">
                                    <div class="stat-card-title">Services Used</div>
                                    <div class="stat-card-value" style="font-size: 2rem; color: #43e97b;"><?php echo count($service_usage); ?></div>
                                    <div class="stat-card-subtitle">This month</div>
                                </div>
                            </div>
                        </div>

                        <!-- Last Checkup Card -->
                        <div class="stat-card" style="border-left: 4px solid #fa709a;">
                            <div class="stat-card-header">
                                <div class="stat-card-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                                    <i class="fas fa-stethoscope"></i>
                                </div>
                                <div style="flex: 1;">
                                    <div class="stat-card-title">Last Checkup</div>
                                    <div class="stat-card-value" style="font-size: 1.5rem; color: #fa709a;">
                                        <?php 
                                        if ($latest_health) {
                                            echo date('M j', strtotime($latest_health['checkup_date']));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </div>
                                    <div class="stat-card-subtitle">
                                        <?php 
                                        if ($latest_health && $latest_health['doctor_name']) {
                                            echo 'Dr. ' . htmlspecialchars($latest_health['doctor_name']);
                                        } else {
                                            echo 'No recent checkup';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Page Content -->
                <div class="page-content" id="page-content">
                    <iframe id="content-frame" src=""></iframe>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Navigation Functions
        function updateURL(page) {
            const newURL = page === 'dashboard' ? 
                window.location.pathname + '?page=dashboard' : 
                window.location.pathname + '?page=' + page.replace('.php', '');
            window.history.pushState({page: page}, '', newURL);
        }

        function showDashboard(updateHistory = true) {
            // Hide page content, show dashboard
            document.getElementById('page-content').style.display = 'none';
            document.getElementById('dashboard-content').style.display = 'block';
            
            // Update header
            document.getElementById('page-title').textContent = 'Dashboard';
            document.getElementById('page-subtitle').textContent = 'Welcome back, <?php echo htmlspecialchars($resident['first_name']); ?>! Here\'s your overview.';
            
            // Update active nav
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            document.querySelector('a[onclick*="showDashboard"]').classList.add('active');
            
            // Update URL
            if (updateHistory) {
                updateURL('dashboard');
            }
        }

        function loadPage(pageUrl, updateHistory = true) {
            // Ensure pageUrl has .php extension
            if (!pageUrl.endsWith('.php')) {
                pageUrl = pageUrl + '.php';
            }
            
            // Show page content, hide dashboard
            document.getElementById('dashboard-content').style.display = 'none';
            document.getElementById('page-content').style.display = 'block';
            
            // Load page in iframe with error handling
            const iframe = document.getElementById('content-frame');
            
            // Add loading state
            iframe.style.opacity = '0.5';
            
            iframe.onload = function() {
                iframe.style.opacity = '1';
            };
            
            iframe.onerror = function() {
                console.error('Failed to load page:', pageUrl);
                iframe.style.opacity = '1';
                // Show error message in iframe
                iframe.srcdoc = `
                    <div style="padding: 20px; text-align: center; font-family: Arial, sans-serif;">
                        <h3 style="color: #e74c3c;">Page Not Found</h3>
                        <p>Could not load: ${pageUrl}</p>
                        <p>Please check if the file exists or try refreshing the page.</p>
                    </div>
                `;
            };
            
            console.log('Loading page:', pageUrl);
            iframe.src = pageUrl;
            
            // Update header based on page
            let title, subtitle;
            switch(pageUrl) {
                case 'health.php':
                    title = 'Health Records';
                    subtitle = 'Your medical history and wellness tracking';
                    break;
                case 'meals.php':
                    title = 'Meals';
                    subtitle = 'Weekly meal schedules and nutrition information';
                    break;
                case 'meal_preferences.php':
                    title = 'Meal Preferences';
                    subtitle = 'Set your taste preferences for upcoming meals';
                    break;
                case 'services.php':
                    title = 'Services';
                    subtitle = 'Request and manage facility services';
                    break;
                case 'buy_services.php':
                    title = 'Buy Services';
                    subtitle = 'Purchase additional service packs';
                    break;
                case 'profile.php':
                    title = 'Profile Settings';
                    subtitle = 'Manage your personal information';
                    break;
                default:
                    title = pageUrl.replace('.php', '').charAt(0).toUpperCase() + pageUrl.replace('.php', '').slice(1);
                    subtitle = 'Page details';
                    break;
            }
            
            document.getElementById('page-title').textContent = title;
            document.getElementById('page-subtitle').textContent = subtitle;
            
            // Update active nav
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            const activeLink = document.querySelector(`a[onclick*="loadPage('${pageUrl}')"]`) || 
                              document.querySelector(`a[onclick*="loadPage('${pageUrl.replace('.php', '')}')"]`);
            if (activeLink) {
                activeLink.classList.add('active');
            }
            
            // Update URL
            if (updateHistory) {
                updateURL(pageUrl.replace('.php', ''));
            }
        }

        // Mobile sidebar toggle
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        // Handle browser back/forward
        window.addEventListener('popstate', function(event) {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page');
            
            if (page === 'dashboard' || !page) {
                showDashboard(false);
            } else {
                loadPage(page + '.php', false);
            }
        });

        // Initialize page based on URL
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize health progress circle
            const progressCircle = document.querySelector('.progress-circle-fill');
            if (progressCircle) {
                const score = parseInt(progressCircle.dataset.score) || 0;
                const circumference = 2 * Math.PI * 30; // radius = 30
                const offset = circumference - (score / 100) * circumference;
                
                setTimeout(() => {
                    progressCircle.style.strokeDashoffset = offset;
                    
                    // Color based on score
                    if (score >= 80) {
                        progressCircle.style.stroke = '#28a745';
                    } else if (score >= 60) {
                        progressCircle.style.stroke = '#ffc107';
                    } else {
                        progressCircle.style.stroke = '#dc3545';
                    }
                }, 500);
            }
            
            // Handle initial page load
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page');
            
            if (currentPage && currentPage !== 'dashboard') {
                const validPages = ['health', 'meals', 'services', 'buy_services', 'profile'];
                if (validPages.includes(currentPage)) {
                    loadPage(currentPage + '.php', false);
                }
            } else {
                showDashboard(false);
            }
        });
    </script>
</body>
</html>
