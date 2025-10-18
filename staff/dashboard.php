<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Staff') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get staff information
$staff_query = "SELECT s.*, u.first_name, u.last_name FROM staff s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.user_id = :user_id";
$staff_stmt = $db->prepare($staff_query);
$staff_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_stmt->execute();
$staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);

// Get assigned tasks for today
$today_tasks_query = "SELECT sr.*, s.service_name, CONCAT(u.first_name, ' ', u.last_name) as resident_name, 
                             r.room_number
                      FROM service_requests sr
                      JOIN services s ON sr.service_id = s.id
                      JOIN residents res ON sr.resident_id = res.id
                      JOIN users u ON res.user_id = u.id
                      LEFT JOIN residents r ON res.id = r.id
                      WHERE sr.assigned_staff_id = :staff_id 
                      AND (sr.scheduled_date = CURDATE() OR sr.status = 'In Progress')
                      ORDER BY sr.scheduled_date, sr.request_date";
$today_tasks_stmt = $db->prepare($today_tasks_query);
$today_tasks_stmt->bindParam(':staff_id', $staff['id']);
$today_tasks_stmt->execute();
$today_tasks = $today_tasks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending service requests (not yet scheduled)
$pending_requests_query = "SELECT sr.*, s.service_name, CONCAT(u.first_name, ' ', u.last_name) as resident_name, 
                                  r.room_number
                           FROM service_requests sr
                           JOIN services s ON sr.service_id = s.id
                           JOIN residents res ON sr.resident_id = res.id
                           JOIN users u ON res.user_id = u.id
                           LEFT JOIN residents r ON res.id = r.id
                           WHERE sr.assigned_staff_id = :staff_id 
                           AND sr.status = 'Requested'
                           ORDER BY sr.request_date";
$pending_requests_stmt = $db->prepare($pending_requests_query);
$pending_requests_stmt->bindParam(':staff_id', $staff['id']);
$pending_requests_stmt->execute();
$pending_requests = $pending_requests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get completed tasks this week
$completed_week_query = "SELECT COUNT(*) FROM service_requests sr
                         WHERE sr.assigned_staff_id = :staff_id 
                         AND sr.status = 'Completed'
                         AND WEEK(sr.completed_at) = WEEK(CURDATE())
                         AND YEAR(sr.completed_at) = YEAR(CURDATE())";
$completed_week_stmt = $db->prepare($completed_week_query);
$completed_week_stmt->bindParam(':staff_id', $staff['id']);
$completed_week_stmt->execute();
$completed_this_week = $completed_week_stmt->fetchColumn();

// Get recent completed tasks
$recent_completed_query = "SELECT sr.*, s.service_name, CONCAT(u.first_name, ' ', u.last_name) as resident_name, 
                                  r.room_number
                           FROM service_requests sr
                           JOIN services s ON sr.service_id = s.id
                           JOIN residents res ON sr.resident_id = res.id
                           JOIN users u ON res.user_id = u.id
                           LEFT JOIN residents r ON res.id = r.id
                           WHERE sr.assigned_staff_id = :staff_id 
                           AND sr.status = 'Completed'
                           ORDER BY sr.completed_at DESC LIMIT 5";
$recent_completed_stmt = $db->prepare($recent_completed_query);
$recent_completed_stmt->bindParam(':staff_id', $staff['id']);
$recent_completed_stmt->execute();
$recent_completed = $recent_completed_stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$stats = [];
$stats['today_tasks'] = count($today_tasks);
$stats['pending_requests'] = count($pending_requests);
$stats['completed_week'] = $completed_this_week;

// Calculate today's completion rate
$completed_today_query = "SELECT COUNT(*) FROM service_requests sr
                          WHERE sr.assigned_staff_id = :staff_id 
                          AND sr.status = 'Completed'
                          AND DATE(sr.completed_at) = CURDATE()";
$completed_today_stmt = $db->prepare($completed_today_query);
$completed_today_stmt->bindParam(':staff_id', $staff['id']);
$completed_today_stmt->execute();
$completed_today = $completed_today_stmt->fetchColumn();

// Avoid division by zero
$stats['completion_rate'] = $stats['today_tasks'] > 0 ? round(($completed_today / $stats['today_tasks']) * 100) : 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Maple House</title>
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
            border-left-color: #3498db;
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
            padding: 10px 30px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            min-height: 60px;
        }
        .header-left {
            display: flex;
            align-items: center;
        }
        .header-titles h1 {
            margin: 0;
            font-size: 1.5rem;
            color: #2c3e50;
        }
        .user-info {
            text-align: right;
        }
        .user-info h3 {
            margin: 0 0 3px 0;
            font-size: 1rem;
            color: #2c3e50;
        }
        .role-badge {
            background: #3498db;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .role-badge.staff {
            background: #28a745;
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
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border-left: 5px solid #667eea;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        .stat-card i {
            font-size: 2.5rem;
            padding: 15px;
            border-radius: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stat-card:nth-child(1) { border-left-color: #667eea; }
        .stat-card:nth-child(1) i { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        
        .stat-card:nth-child(2) { border-left-color: #f093fb; }
        .stat-card:nth-child(2) i { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        
        .stat-card:nth-child(3) { border-left-color: #4facfe; }
        .stat-card:nth-child(3) i { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        
        .stat-card:nth-child(4) { border-left-color: #43e97b; }
        .stat-card:nth-child(4) i { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        
        .stat-card.warning { border-left-color: #fa709a; }
        .stat-card.warning i { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        
        .stat-card.danger { border-left-color: #f5576c; }
        .stat-card.danger i { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        
        .stat-card.success { border-left-color: #43e97b; }
        .stat-card.success i { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 25px;
        }
        
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Widget Styles */
        .widget {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .widget:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .widget-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 2px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .widget-header h3 {
            margin: 0;
            font-size: 1.2rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .task-count {
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .widget-content {
            padding: 25px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        /* Other Custom Styles from original code */
        .tasks-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .task-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px;
            border-radius: 12px;
            border-left: 5px solid #6c757d;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
        }
        .task-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .task-item.scheduled { 
            border-left-color: #667eea; 
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }
        .task-item.in-progress { 
            border-left-color: #f5576c; 
            background: linear-gradient(135deg, rgba(240, 147, 251, 0.05) 0%, rgba(245, 87, 108, 0.05) 100%);
        }
        .task-item.completed { 
            border-left-color: #43e97b; 
            background: linear-gradient(135deg, rgba(67, 233, 123, 0.05) 0%, rgba(56, 249, 215, 0.05) 100%);
        }
        .task-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 12px; 
        }
        .task-status { 
            padding: 5px 12px; 
            border-radius: 20px; 
            font-size: 0.75rem; 
            font-weight: 600; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
        }
        .task-status.scheduled { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .task-status.in-progress { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .task-status.completed { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; }
        .task-actions { 
            display: flex; 
            flex-direction: column; 
            gap: 8px; 
            min-width: 120px; 
        }
        .task-info {
            flex: 1;
        }
        .task-details {
            display: flex;
            gap: 15px;
            margin-top: 8px;
            font-size: 0.9rem;
            color: #666;
        }
        .task-notes {
            margin-top: 10px;
            padding: 10px;
            background: rgba(0,0,0,0.02);
            border-radius: 6px;
            font-size: 0.85rem;
            color: #666;
        }
        .requests-list { 
            display: flex; 
            flex-direction: column; 
            gap: 15px; 
        }
        .request-item { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 15px; 
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(255, 152, 0, 0.1) 100%); 
            border-radius: 10px; 
            border-left: 4px solid #ffc107; 
            transition: all 0.3s ease;
        }
        .request-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.2);
        }
        .completed-list { 
            display: flex; 
            flex-direction: column; 
            gap: 12px; 
            margin-bottom: 15px; 
        }
        .completed-item { 
            padding: 12px; 
            background: linear-gradient(135deg, rgba(67, 233, 123, 0.1) 0%, rgba(56, 249, 215, 0.1) 100%); 
            border-radius: 10px; 
            border-left: 4px solid #43e97b; 
            transition: all 0.3s ease;
        }
        .completed-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(67, 233, 123, 0.2);
        }
        .quick-action-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f1f3f5;
            border-radius: 8px;
            text-decoration: none;
            color: #212529;
            transition: all 0.2s ease;
        }
        .quick-action-item:hover {
            background: #e9ecef;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-hands-helping"></i><span class="nav-text"> Maple House Staff</span></h2>
            </div>
            
            <div class="sidebar-menu">
                <ul class="nav-items">
                    <li><a href="#" onclick="showDashboard()" class="nav-link active"><i class="fas fa-tachometer-alt"></i> <span class="nav-text">Dashboard</span></a></li>
                    <li><a href="#" onclick="loadPage('my_tasks.php')" class="nav-link"><i class="fas fa-tasks"></i> <span class="nav-text">My Tasks</span></a></li>
                    <li><a href="#" onclick="loadPage('schedule.php')" class="nav-link"><i class="fas fa-calendar"></i> <span class="nav-text">Schedule</span></a></li>
                    <li><a href="#" onclick="loadPage('residents.php')" class="nav-link"><i class="fas fa-users"></i> <span class="nav-text">Residents</span></a></li>
                    <li><a href="#" onclick="loadPage('my_salary.php')" class="nav-link"><i class="fas fa-money-bill-wave"></i> <span class="nav-text">My Salary</span></a></li>
                    <li><a href="#" onclick="loadPage('profile.php')" class="nav-link"><i class="fas fa-user-circle"></i> <span class="nav-text">Profile</span></a></li>
                </ul>
            </div>
            
            <div class="sidebar-footer">
                <a href="../logout.php" class="nav-link logout-btn">
                    <i class="fas fa-sign-out-alt"></i> <span class="nav-text">Logout</span>
                </a>
            </div>
        </nav>

        <div class="main-content" id="mainContent">
            <div class="top-header">
                <div class="header-left">
                    <div class="header-titles">
                        <h1 id="page-title">Dashboard</h1>
                    </div>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <h3><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></h3>
                        <small style="color: #666;"><?php echo htmlspecialchars($staff['position'] . ' - ' . $staff['department']); ?></small>
                    </div>
                </div>
            </div>

            <div class="content-container">
                
                <div class="page-section active" id="dashboard-content">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="fas fa-tasks"></i>
                            <div>
                                <div class="stat-number"><?php echo $stats['today_tasks']; ?></div>
                                <div class="stat-label">Today's Tasks</div>
                            </div>
                        </div>
                        
                        <div class="stat-card <?php echo $stats['pending_requests'] > 0 ? 'warning' : 'success'; ?>">
                            <i class="fas fa-clock"></i>
                            <div>
                                <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                                <div class="stat-label">Pending Requests</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <div class="stat-number"><?php echo $stats['completed_week']; ?></div>
                                <div class="stat-label">Completed This Week</div>
                            </div>
                        </div>
                        
                        <div class="stat-card <?php echo $stats['completion_rate'] >= 80 ? 'success' : ($stats['completion_rate'] >= 60 ? 'warning' : 'danger'); ?>">
                            <i class="fas fa-percentage"></i>
                            <div>
                                <div class="stat-number"><?php echo $stats['completion_rate']; ?>%</div>
                                <div class="stat-label">Today's Completion</div>
                            </div>
                        </div>
                    </div>

                    <div class="content-grid">
                        <div class="content-main">
                            <div class="widget">
                                <div class="widget-header">
                                    <h3><i class="fas fa-list-check"></i> Today's Tasks</h3>
                                    <span class="task-count"><?php echo count($today_tasks); ?> tasks</span>
                                </div>
                                <div class="widget-content">
                                    <?php if (!empty($today_tasks)): ?>
                                        <div class="tasks-list">
                                            <?php foreach ($today_tasks as $task): ?>
                                                <div class="task-item <?php echo strtolower(str_replace(' ', '-', $task['status'])); ?>">
                                                    <div class="task-info">
                                                        <div class="task-header">
                                                            <strong><?php echo htmlspecialchars($task['service_name']); ?></strong>
                                                            <span class="task-status <?php echo strtolower(str_replace(' ', '-', $task['status'])); ?>">
                                                                <?php echo $task['status']; ?>
                                                            </span>
                                                        </div>
                                                        <div class="task-details">
                                                            <span class="resident-info">
                                                                <i class="fas fa-user"></i>
                                                                <?php echo htmlspecialchars($task['resident_name']); ?> 
                                                                (Room <?php echo htmlspecialchars($task['room_number']); ?>)
                                                            </span>
                                                            <?php if ($task['scheduled_date']): ?>
                                                                <span class="task-time">
                                                                    <i class="fas fa-clock"></i>
                                                                    <?php echo date('M j, Y', strtotime($task['scheduled_date'])); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($task['notes']): ?>
                                                            <div class="task-notes">
                                                                <i class="fas fa-sticky-note"></i>
                                                                <?php echo htmlspecialchars($task['notes']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="task-actions">
                                                        <?php if ($task['status'] === 'Scheduled'): ?>
                                                            <button onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'In Progress')" 
                                                                    class="btn btn-primary btn-sm">Start</button>
                                                        <?php elseif ($task['status'] === 'In Progress'): ?>
                                                            <button onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'Completed')" 
                                                                    class="btn btn-success btn-sm">Complete</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state">
                                            <i class="fas fa-check-circle"></i>
                                            <p>No tasks assigned for today</p>
                                            <a href="my_tasks.php" class="btn btn-primary">View All Tasks</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="content-sidebar">
                            <?php if (!empty($pending_requests)): ?>
                            <div class="widget">
                                <div class="widget-header">
                                    <h3><i class="fas fa-hourglass-half"></i> Pending Requests</h3>
                                </div>
                                <div class="widget-content">
                                    <div class="requests-list">
                                        <?php foreach ($pending_requests as $request): ?>
                                            <div class="request-item">
                                                <div class="request-info">
                                                    <strong><?php echo htmlspecialchars($request['service_name']); ?></strong>
                                                    <span class="resident-name">
                                                        <?php echo htmlspecialchars($request['resident_name']); ?> 
                                                        (Room <?php echo htmlspecialchars($request['room_number']); ?>)
                                                    </span>
                                                    <small class="request-date">
                                                        Requested: <?php echo date('M j, Y', strtotime($request['request_date'])); ?>
                                                    </small>
                                                </div>
                                                <div class="request-actions">
                                                    <button onclick="scheduleTask(<?php echo $request['id']; ?>)" 
                                                            class="btn btn-primary btn-sm">Schedule</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($recent_completed)): ?>
                            <div class="widget">
                                <div class="widget-header">
                                    <h3><i class="fas fa-check-double"></i> Recently Completed</h3>
                                </div>
                                <div class="widget-content">
                                    <div class="completed-list">
                                        <?php foreach ($recent_completed as $completed): ?>
                                            <div class="completed-item">
                                                <div class="completed-info">
                                                    <strong><?php echo htmlspecialchars($completed['service_name']); ?></strong>
                                                    <span class="resident-name">
                                                        <?php echo htmlspecialchars($completed['resident_name']); ?>
                                                    </span>
                                                    <small class="completion-time">
                                                        <?php echo date('M j, g:i A', strtotime($completed['completed_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Page Content Frame -->
                <div class="page-section" id="page-content">
                    <iframe id="content-frame" src="" style="width: 100%; height: 100%; border: none; display: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.body.classList.toggle('sidebar-hidden');
            const isHidden = document.body.classList.contains('sidebar-hidden');
            localStorage.setItem('sidebarHidden', isHidden);
        }

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
            
            // Reset title to Dashboard
            document.getElementById('page-title').innerText = 'Dashboard';
            
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
            
            // Update title based on page
            let title;
            switch(pageUrl) {
                case 'my_tasks.php':
                    title = 'My Tasks';
                    break;
                case 'schedule.php':
                    title = 'My Schedule';
                    break;
                case 'residents.php':
                    title = 'Residents';
                    break;
                case 'my_salary.php':
                    title = 'My Salary';
                    break;
                case 'profile.php':
                    title = 'Profile';
                    break;
                default:
                    title = pageUrl.replace('.php', '').split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
                    break;
            }
            
            document.getElementById('page-title').innerText = title;
            
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
                    'my_tasks', 'schedule', 'residents', 'my_salary', 'profile'
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
        
        // Salary Popup Function
        function showSalaryPopup() {
            fetch('../api/get_staff_salary.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const salary = data.salary;
                        const modal = document.createElement('div');
                        modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;';
                        modal.innerHTML = `
                            <div style="background:white;padding:30px;border-radius:15px;max-width:500px;width:90%;box-shadow:0 10px 40px rgba(0,0,0,0.3);">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:2px solid #e9ecef;padding-bottom:15px;">
                                    <h2 style="margin:0;color:#2c3e50;"><i class="fas fa-money-bill-wave"></i> My Salary Information</h2>
                                    <button onclick="this.closest('div[style*=fixed]').remove()" style="background:none;border:none;font-size:24px;cursor:pointer;color:#999;">&times;</button>
                                </div>
                                <div style="margin-bottom:20px;">
                                    <div style="background:#f8f9fa;padding:20px;border-radius:10px;margin-bottom:15px;">
                                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                                            <span style="color:#666;font-size:0.9rem;">Monthly Salary</span>
                                            <span style="font-size:1.8rem;font-weight:bold;color:#28a745;">৳${parseFloat(salary.monthly_salary || 0).toLocaleString()}</span>
                                        </div>
                                    </div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                                        <div style="background:#e7f3ff;padding:15px;border-radius:8px;text-align:center;">
                                            <div style="color:#666;font-size:0.85rem;margin-bottom:5px;">Department</div>
                                            <div style="font-weight:600;color:#2c3e50;">${salary.department || 'N/A'}</div>
                                        </div>
                                        <div style="background:#fff3cd;padding:15px;border-radius:8px;text-align:center;">
                                            <div style="color:#666;font-size:0.85rem;margin-bottom:5px;">Position</div>
                                            <div style="font-weight:600;color:#2c3e50;">${salary.position || 'N/A'}</div>
                                        </div>
                                        <div style="background:#d4edda;padding:15px;border-radius:8px;text-align:center;">
                                            <div style="color:#666;font-size:0.85rem;margin-bottom:5px;">Hire Date</div>
                                            <div style="font-weight:600;color:#2c3e50;">${salary.hire_date || 'N/A'}</div>
                                        </div>
                                        <div style="background:#f8d7da;padding:15px;border-radius:8px;text-align:center;">
                                            <div style="color:#666;font-size:0.85rem;margin-bottom:5px;">Shift Hours</div>
                                            <div style="font-weight:600;color:#2c3e50;">${salary.shift_hours || 'N/A'}</div>
                                        </div>
                                    </div>
                                </div>
                                <button onclick="this.closest('div[style*=fixed]').remove()" style="width:100%;padding:12px;background:#3498db;color:white;border:none;border-radius:8px;font-size:1rem;cursor:pointer;font-weight:600;">Close</button>
                            </div>
                        `;
                        document.body.appendChild(modal);
                        modal.onclick = (e) => { if(e.target === modal) modal.remove(); };
                    } else {
                        alert('Failed to load salary information');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading salary information');
                });
        }

        // The task update functions from your original code are kept below for functionality.
        function updateTaskStatus(taskId, newStatus) {
            if (confirm(`Are you sure you want to mark this task as ${newStatus.toLowerCase()}?`)) {
                fetch('../api/update_task_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        task_id: taskId,
                        status: newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Task ${newStatus.toLowerCase()} successfully!`);
                        setTimeout(() => location.reload(), 500);
                    } else {
                        alert(data.message || 'Failed to update task status');
                    }
                })
                .catch(error => {
                    console.error('Error updating task status:', error);
                    alert('Error updating task status');
                });
            }
        }

        function scheduleTask(requestId) {
            const scheduledDate = prompt('Enter scheduled date (YYYY-MM-DD):');
            if (scheduledDate) {
                fetch('../api/schedule_task.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        request_id: requestId,
                        scheduled_date: scheduledDate
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Task scheduled successfully!');
                        setTimeout(() => location.reload(), 500);
                    } else {
                        alert(data.message || 'Failed to schedule task');
                    }
                })
                .catch(error => {
                    console.error('Error scheduling task:', error);
                    alert('Error scheduling task');
                });
            }
        }
    </script>
</body>
</html>