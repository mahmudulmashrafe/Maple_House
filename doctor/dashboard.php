<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get doctor information
$doctor_query = "SELECT d.*, u.first_name, u.last_name FROM doctors d 
                 JOIN users u ON d.user_id = u.id 
                 WHERE d.user_id = :user_id";
$doctor_stmt = $db->prepare($doctor_query);
$doctor_stmt->bindParam(':user_id', $_SESSION['user_id']);
$doctor_stmt->execute();
$doctor = $doctor_stmt->fetch(PDO::FETCH_ASSOC);

// Get assigned residents
$residents_query = "SELECT r.*, u.first_name, u.last_name, u.date_of_birth, u.gender, 
                           pp.plan_name, hr.checkup_date as last_checkup
                    FROM residents r
                    JOIN users u ON r.user_id = u.id
                    JOIN payment_plans pp ON r.plan_id = pp.id
                    LEFT JOIN (
                        SELECT resident_id, MAX(checkup_date) as checkup_date
                        FROM health_records 
                        WHERE doctor_id = :doctor_id
                        GROUP BY resident_id
                    ) hr ON r.id = hr.resident_id
                    ORDER BY u.first_name, u.last_name";
$residents_stmt = $db->prepare($residents_query);
$residents_stmt->bindParam(':doctor_id', $doctor['id']);
$residents_stmt->execute();
$residents = $residents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's appointments
$appointments_query = "SELECT hr.*, CONCAT(u.first_name, ' ', u.last_name) as resident_name, r.room_number
                       FROM health_records hr
                       JOIN residents res ON hr.resident_id = res.id
                       JOIN users u ON res.user_id = u.id
                       LEFT JOIN residents r ON res.id = r.id
                       WHERE hr.doctor_id = :doctor_id 
                       AND hr.next_checkup_date = CURDATE()
                       ORDER BY hr.next_checkup_date";
$appointments_stmt = $db->prepare($appointments_query);
$appointments_stmt->bindParam(':doctor_id', $doctor['id']);
$appointments_stmt->execute();
$today_appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent health records
$recent_records_query = "SELECT hr.*, CONCAT(u.first_name, ' ', u.last_name) as resident_name, r.room_number
                         FROM health_records hr
                         JOIN residents res ON hr.resident_id = res.id
                         JOIN users u ON res.user_id = u.id
                         LEFT JOIN residents r ON res.id = r.id
                         WHERE hr.doctor_id = :doctor_id
                         ORDER BY hr.checkup_date DESC LIMIT 10";
$recent_records_stmt = $db->prepare($recent_records_query);
$recent_records_stmt->bindParam(':doctor_id', $doctor['id']);
$recent_records_stmt->execute();
$recent_records = $recent_records_stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$stats = [];
$stats['total_patients'] = count($residents);
$stats['today_appointments'] = count($today_appointments);

// Patients needing attention (high risk indicators)
$attention_query = "SELECT COUNT(*) FROM residents r
                    JOIN health_records hr ON r.id = hr.resident_id
                    WHERE hr.doctor_id = :doctor_id
                    AND (r.health_score < 60 OR r.mental_health_score < 60)";
$attention_stmt = $db->prepare($attention_query);
$attention_stmt->bindParam(':doctor_id', $doctor['id']);
$attention_stmt->execute();
$stats['needs_attention'] = $attention_stmt->fetchColumn();

// Pending meal plan reviews
$meal_plans_query = "SELECT COUNT(*) FROM meal_plans mp
                     JOIN residents r ON mp.resident_id = r.id
                     WHERE mp.doctor_id = :doctor_id
                     AND mp.valid_to < DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                     AND mp.is_active = 1";
$meal_plans_stmt = $db->prepare($meal_plans_query);
$meal_plans_stmt->bindParam(':doctor_id', $doctor['id']);
$meal_plans_stmt->execute();
$stats['pending_meal_plans'] = $meal_plans_stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Maple House</title>
    <link rel="icon" type="image/jpeg" href="../images/favicon.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="../images/favicon.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* General Body and Layout */
        body {
            margin: 0;
            padding: 0;
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            height: 100vh;
            overflow: hidden;
        }

        .dashboard-layout {
            display: flex;
            height: 100%;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            z-index: 1000;
            transition: width 0.3s ease;
            overflow-y: auto;
            flex-shrink: 0;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            white-space: nowrap;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
        }
        .sidebar-header h2 i {
            margin-right: 10px;
        }

        .sidebar-menu {
            flex: 1;
            display: flex;
            flex-direction: column;
            list-style: none;
            padding: 20px 0;
            margin: 0;
        }

        .sidebar-menu li {
            position: relative;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            white-space: nowrap;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left-color: #27ae60;
        }

        .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .nav-text {
            transition: opacity 0.1s ease;
        }

        /* Sidebar Footer */
        .sidebar-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 20px;
            white-space: nowrap;
        }
        .logout-btn {
            color: white !important;
        }
        .logout-btn:hover {
            background: rgba(231,76,60,0.1) !important;
            border-left-color: #e74c3c !important;
        }
        
        /* Main Content Area */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f8f9fa;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .top-header {
            background: white;
            padding: 15px 30px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            min-height: 70px;
        }
        .header-left {
            display: flex;
            align-items: center;
        }
        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #34495e;
            cursor: pointer;
            margin-right: 20px;
            padding: 0;
            transition: color 0.2s;
        }
        .sidebar-toggle:hover {
            color: #27ae60;
        }
        .header-titles h1 {
            margin: 0 0 5px 0;
            font-size: 1.8rem;
            color: #2c3e50;
        }
        .header-titles p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        .user-info {
            text-align: right;
        }
        .user-info h3 {
            margin: 0 0 5px 0;
            font-size: 1.1rem;
            color: #2c3e50;
        }
        .role-badge {
            background: #27ae60;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        /* Content Container */
        .content-container {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            position: relative;
        }
        .page-section {
            display: none;
            height: 100%;
            position: relative;
        }
        .page-section.active {
            display: block;
        }

        .page-header {
            padding: 20px 25px;
            background: white;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 0;
        }

        .page-header h1 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-size: 1.8rem;
        }

        .page-header p {
            margin: 0;
            color: #6c757d;
            font-size: 0.95rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        .stat-card i {
            font-size: 2rem;
            padding: 12px;
            border-radius: 50%;
            color: white;
        }
        .stat-card:nth-child(1) i { background: #3498db; }
        .stat-card:nth-child(2) i { background: #27ae60; }
        .stat-card.warning i { background: #ffc107; }
        .stat-card:nth-child(4) i { background: #9b59b6; }
        
        .stat-info h3 {
            margin: 0 0 4px 0;
            font-size: 1.6rem;
            color: #2c3e50;
        }
        .stat-info p {
            margin: 0;
            color: #7f8c8d;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        
        /* Widgets */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        .widget {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-bottom: 30px;
            overflow: hidden;
        }
        .widget-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #27ae60;
            color: white;
            padding: 20px 25px;
        }
        .widget-header h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .widget-content {
            padding: 25px;
        }
        .patients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
        }
        .patient-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .health-score.low, .mental-score.low {
            background: #f8d7da;
            color: #721c24;
        }
        .health-score.medium, .mental-score.medium {
            background: #fff3cd;
            color: #856404;
        }
        .health-score.high, .mental-score.high {
            background: #d4edda;
            color: #155724;
        }
        .btn-primary { background-color: #27ae60; border-color: #27ae60; }
        .btn-primary:hover { background-color: #229954; border-color: #229954; }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-user-md"></i><span class="nav-text"> Medical Portal</span></h2>
            </div>
            
            <div class="sidebar-menu">
                <ul class="nav-items">
                    <li><a href="#" onclick="showDashboard()" class="nav-link active"><i class="fas fa-tachometer-alt"></i> <span class="nav-text">Dashboard</span></a></li>
                    <li><a href="#" onclick="loadPage('patients.php')" class="nav-link"><i class="fas fa-users"></i> <span class="nav-text">Patients</span></a></li>
                    <li><a href="#" onclick="loadPage('health_records.php')" class="nav-link"><i class="fas fa-file-medical"></i> <span class="nav-text">Health Records</span></a></li>
                    <li><a href="#" onclick="loadPage('meals.php')" class="nav-link"><i class="fas fa-utensils"></i> <span class="nav-text">Meals</span></a></li>
                    <li><a href="#" onclick="loadPage('appointments.php')" class="nav-link"><i class="fas fa-calendar-check"></i> <span class="nav-text">Appointments</span></a></li>
                    <li><a href="#" onclick="loadPage('reports.php')" class="nav-link"><i class="fas fa-chart-line"></i> <span class="nav-text">Reports</span></a></li>
                </ul>
                
                <div class="sidebar-footer">
                    <a href="../logout.php" class="nav-link logout-btn">
                        <i class="fas fa-sign-out-alt"></i> <span class="nav-text">Logout</span>
                    </a>
                </div>
            </div>
        </nav>

        <div class="main-content" id="mainContent">
            <div class="top-header">
                <div class="header-left">
                    <div class="header-titles">
                        <h1 id="page-title">Doctor Dashboard</h1>
                        <p id="page-subtitle">Medical Portal Overview</p>
                    </div>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <h3><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
                        <span class="role-badge">Doctor</span>
                        <div style="margin-top: 5px;">
                            <small><?php echo htmlspecialchars($doctor['specialization']); ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-container">
                <div class="page-section active" id="dashboard-content">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="fas fa-users"></i>
                            <div class="stat-info">
                                <h3><?php echo $stats['total_patients']; ?></h3>
                                <p>Total Patients</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <i class="fas fa-calendar-day"></i>
                            <div class="stat-info">
                                <h3><?php echo $stats['today_appointments']; ?></h3>
                                <p>Today's Appointments</p>
                            </div>
                        </div>
                        
                        <div class="stat-card <?php echo $stats['needs_attention'] > 0 ? 'warning' : 'success'; ?>">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div class="stat-info">
                                <h3><?php echo $stats['needs_attention']; ?></h3>
                                <p>Needs Attention</p>
                            </div>
                        </div>
                        
                        <div class="stat-card <?php echo $stats['pending_meal_plans'] > 0 ? 'warning' : 'success'; ?>">
                            <i class="fas fa-clipboard-list"></i>
                            <div class="stat-info">
                                <h3><?php echo $stats['pending_meal_plans']; ?></h3>
                                <p>Meal Plans to Review</p>
                            </div>
                        </div>
                    </div>

                    <div class="content-grid">
                        <div class="content-main">
                            <div class="widget">
                                <div class="widget-header">
                                    <h3><i class="fas fa-calendar-check"></i> Today's Appointments</h3>
                                    <a href="#" onclick="showPage('health_records')" class="btn btn-primary btn-sm">New Checkup</a>
                                </div>
                                <div class="widget-content">
                                    <?php if (!empty($today_appointments)): ?>
                                        <div class="appointments-list">
                                            <?php foreach ($today_appointments as $appointment): ?>
                                                <div class="appointment-item">
                                                    <div class="appointment-info">
                                                        <strong><?php echo htmlspecialchars($appointment['resident_name']); ?></strong>
                                                        <span class="room-badge">Room <?php echo htmlspecialchars($appointment['room_number']); ?></span>
                                                    </div>
                                                    <div class="appointment-time">
                                                        <i class="fas fa-clock"></i> Follow-up Checkup
                                                    </div>
                                                    <div class="appointment-actions">
                                                        <a href="health_record.php?id=<?php echo $appointment['id']; ?>" class="btn btn-secondary btn-sm">View Record</a>
                                                        <a href="add_health_record.php?resident_id=<?php echo $appointment['resident_id']; ?>" class="btn btn-primary btn-sm">New Checkup</a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state">
                                            <i class="fas fa-calendar"></i>
                                            <p>No appointments scheduled for today</p>
                                            <a href="#" onclick="showPage('appointments')" class="btn btn-primary">Schedule Appointments</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="content-sidebar">
                            <div class="widget">
                                <div class="widget-header">
                                    <h3><i class="fas fa-file-medical"></i> Recent Records</h3>
                                </div>
                                <div class="widget-content">
                                    <?php if (!empty($recent_records)): ?>
                                        <div class="records-list">
                                            <?php foreach (array_slice($recent_records, 0, 5) as $record): ?>
                                                <div class="record-item">
                                                    <div class="record-header">
                                                        <strong><?php echo htmlspecialchars($record['resident_name']); ?></strong>
                                                        <small><?php echo date('M j, Y', strtotime($record['checkup_date'])); ?></small>
                                                    </div>
                                                    <div class="record-details">
                                                        <span>BP: <?php echo htmlspecialchars($record['blood_pressure'] ?? 'N/A'); ?></span>
                                                        <span>HR: <?php echo $record['heart_rate'] ? $record['heart_rate'] . ' bpm' : 'N/A'; ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p>No recent health records.</p>
                                    <?php endif; ?>
                                    <a href="#" onclick="showPage('health_records')" class="btn btn-secondary btn-sm">View All Records</a>
                                </div>
                            </div>

                            <div class="widget">
                                <div class="widget-header">
                                    <h3><i class="fas fa-bell"></i> Medical Reminders</h3>
                                </div>
                                <div class="widget-content">
                                    <div class="reminders-list">
                                        <div class="reminder-item">
                                            <i class="fas fa-calendar-check"></i>
                                            <span>Weekly health assessments due</span>
                                        </div>
                                        <div class="reminder-item">
                                            <i class="fas fa-pills"></i>
                                            <span>Review medication dosages</span>
                                        </div>
                                        <div class="reminder-item">
                                            <i class="fas fa-utensils"></i>
                                            <span>Update meal plans for diabetic patients</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Page Content Frame -->
                <div class="page-section" id="page-content">
                    <div class="page-header">
                        <h1 id="page-title">Dashboard</h1>
                        <p id="page-subtitle">Medical Portal Overview</p>
                    </div>
                    <iframe id="content-frame" src="" style="width: 100%; height: calc(100vh - 140px); border: none; display: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script>

        // Update URL without page reload
        function updateURL(page) {
            const newURL = page === 'dashboard' ? 
                window.location.pathname + '?page=dashboard' : 
                window.location.pathname + '?page=' + page.replace('.php', '');
            window.history.pushState({page: page}, '', newURL);
        }

        function showDashboard(updateHistory = true) {
            document.getElementById('page-content').classList.remove('active');
            document.getElementById('dashboard-content').classList.add('active');
            document.getElementById('content-frame').style.display = 'none';
            
            // Update active state in sidebar
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelector('a[onclick="showDashboard()"]').classList.add('active');
            
            // Update URL
            if (updateHistory) {
                updateURL('dashboard');
            }
        }
        
        function loadPage(pageUrl, updateHistory = true) {
            document.getElementById('dashboard-content').classList.remove('active');
            document.getElementById('page-content').classList.add('active');
            
            const contentFrame = document.getElementById('content-frame');
            contentFrame.src = pageUrl;
            contentFrame.style.display = 'block';
            
            // Update header based on page
            let title, subtitle;
            switch(pageUrl) {
                case 'patients.php':
                    title = 'Patients';
                    subtitle = 'View and manage all assigned patients';
                    break;
                case 'health_records.php':
                    title = 'Health Records';
                    subtitle = 'View and manage patient health records';
                    break;
                case 'meals.php':
                    title = 'Meals';
                    subtitle = 'View weekly meal schedules';
                    break;
                case 'appointments.php':
                    title = 'Appointments';
                    subtitle = 'Manage daily and future appointments';
                    break;
                case 'reports.php':
                    title = 'Reports';
                    subtitle = 'View medical and health reports';
                    break;
                default:
                    title = pageUrl.replace('.php', '').split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
                    subtitle = 'Details for ' + title;
                    break;
            }
            
            document.getElementById('page-title').innerText = title;
            document.getElementById('page-subtitle').innerText = subtitle;
            
            // Update active state in sidebar
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });

            const currentLink = document.querySelector(`a[onclick="loadPage('${pageUrl}')"]`);
            if (currentLink) {
                currentLink.classList.add('active');
            }
            
            // Update URL
            if (updateHistory) {
                updateURL(pageUrl);
            }
        }

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(event) {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page');
            
            if (page === 'dashboard' || !page) {
                showDashboard(false);
            } else {
                loadPage(page + '.php', false);
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const sidebarHidden = localStorage.getItem('sidebarHidden') === 'true';
            if (sidebarHidden) {
                document.body.classList.add('sidebar-hidden');
            } else {
                document.body.classList.remove('sidebar-hidden');
            }
            
            // Handle initial page load with URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page');
            
            if (currentPage && currentPage !== 'dashboard') {
                const validPages = [
                    'patients', 'health_records', 'meals', 'appointments', 'reports'
                ];
                
                if (validPages.includes(currentPage)) {
                    loadPage(currentPage + '.php', false);
                } else {
                    // If invalid page, redirect to dashboard
                    showDashboard(true);
                }
            } else {
                // Default to dashboard
                showDashboard(false);
            }
        });
    </script>
</body>
</html>