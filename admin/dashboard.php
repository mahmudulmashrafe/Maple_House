<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and has admin/manager role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Manager'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get comprehensive dashboard statistics
$stats = [];

// === RESIDENT STATISTICS ===
// Total residents
$residents_query = "SELECT COUNT(*) FROM residents";
$residents_stmt = $db->prepare($residents_query);
$residents_stmt->execute();
$stats['total_residents'] = $residents_stmt->fetchColumn();

// Active residents
$active_residents_query = "SELECT COUNT(*) FROM residents r JOIN users u ON r.user_id = u.id WHERE u.is_active = 1";
$active_residents_stmt = $db->prepare($active_residents_query);
$active_residents_stmt->execute();
$stats['active_residents'] = $active_residents_stmt->fetchColumn();

// Room occupancy - Updated to use infrastructure table
try {
    $occupancy_query = "SELECT 
        COUNT(CASE WHEN i.is_occupied = 1 THEN 1 END) as occupied_rooms,
        COUNT(*) as total_rooms
        FROM infrastructure i 
        WHERE i.type = 'room' AND i.is_active = 1";
    $occupancy_stmt = $db->prepare($occupancy_query);
    $occupancy_stmt->execute();
    $occupancy_data = $occupancy_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['occupied_rooms'] = $occupancy_data['occupied_rooms'];
    $stats['total_rooms'] = $occupancy_data['total_rooms'];
    $stats['occupancy_rate'] = $stats['total_rooms'] > 0 ? round(($stats['occupied_rooms'] / $stats['total_rooms']) * 100, 1) : 0;
} catch (Exception $e) {
    // Fallback to old method if infrastructure table doesn't exist
    $occupancy_query = "SELECT 
        COUNT(DISTINCT r.room_number) as occupied_rooms,
        60 as total_rooms
        FROM residents r 
        JOIN users u ON r.user_id = u.id 
        WHERE u.is_active = 1";
    $occupancy_stmt = $db->prepare($occupancy_query);
    $occupancy_stmt->execute();
    $occupancy_data = $occupancy_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['occupied_rooms'] = $occupancy_data['occupied_rooms'];
    $stats['total_rooms'] = 60; // Default fallback
    $stats['occupancy_rate'] = $stats['total_rooms'] > 0 ? round(($stats['occupied_rooms'] / $stats['total_rooms']) * 100, 1) : 0;
}

// === STAFF STATISTICS ===
// Total staff by role
$staff_query = "SELECT 
    COUNT(CASE WHEN u.role_id = 5 THEN 1 END) as total_staff,
    COUNT(CASE WHEN u.role_id = 3 THEN 1 END) as total_doctors,
    COUNT(CASE WHEN u.role_id = 4 THEN 1 END) as total_chefs,
    COUNT(CASE WHEN u.role_id = 5 AND u.is_active = 1 THEN 1 END) as active_staff,
    COUNT(CASE WHEN u.role_id = 3 AND u.is_active = 1 THEN 1 END) as active_doctors,
    COUNT(CASE WHEN u.role_id = 4 AND u.is_active = 1 THEN 1 END) as active_chefs
    FROM users u WHERE u.role_id IN (3, 4, 5)";
$staff_stmt = $db->prepare($staff_query);
$staff_stmt->execute();
$staff_data = $staff_stmt->fetch(PDO::FETCH_ASSOC);
$stats = array_merge($stats, $staff_data);

// === FINANCIAL STATISTICS (Synced with finances_overview.php) ===
$current_month = date('Y-m');
$current_year = date('Y');

// Initialize financial stats
$stats['monthly_donations'] = 0;
$stats['monthly_resident_payments'] = 0;
$stats['monthly_revenue'] = 0;
$stats['monthly_expenses'] = 0;
$stats['net_income'] = 0;

try {
    // Get donation amounts (same logic as finances_overview.php)
    $donations_query = "SELECT 
                            COALESCE(SUM(CASE WHEN is_verified = 1 AND DATE_FORMAT(donation_date, '%Y-%m') = ? THEN amount ELSE 0 END), 0) as monthly_donations
                        FROM donations";
    $donations_stmt = $db->prepare($donations_query);
    $donations_stmt->execute([$current_month]);
    $donation_result = $donations_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($donation_result) {
        $stats['monthly_donations'] = $donation_result['monthly_donations'];
    }
    
    // Get resident revenue from subscription history (same logic as finances_overview.php)
    $resident_revenue_query = "SELECT 
                                    COALESCE(SUM(CASE WHEN payment_status = 'paid' AND DATE_FORMAT(payment_date, '%Y-%m') = ? THEN amount ELSE 0 END), 0) as monthly_resident_payments
                                FROM resident_revenue_history 
                                WHERE payment_type IN ('initial', 'renewal', 'upgrade')";
    $resident_revenue_stmt = $db->prepare($resident_revenue_query);
    $resident_revenue_stmt->execute([$current_month]);
    $resident_result = $resident_revenue_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($resident_result) {
        $stats['monthly_resident_payments'] = $resident_result['monthly_resident_payments'];
    }
    
    // Calculate total revenue (resident payments + donations)
    $stats['monthly_revenue'] = $stats['monthly_resident_payments'] + $stats['monthly_donations'];
    
    // Monthly expenses from expense system
    try {
        $expenses_query = "SELECT SUM(amount) as monthly_expenses FROM expenses WHERE status IN ('approved', 'paid') AND DATE_FORMAT(expense_date, '%Y-%m') = ?";
        $expenses_stmt = $db->prepare($expenses_query);
        $expenses_stmt->execute([$current_month]);
        $real_expenses = $expenses_stmt->fetchColumn() ?: 0;
        $stats['monthly_expenses'] = $real_expenses > 0 ? $real_expenses : 35000; // Fallback to default
    } catch (Exception $e) {
        $stats['monthly_expenses'] = 35000; // Monthly operational expenses fallback
    }
    
    // Calculate net income
    $stats['net_income'] = $stats['monthly_revenue'] - $stats['monthly_expenses'];
    
} catch (Exception $e) {
    // Handle database errors - use fallback data if tables don't exist
    error_log("Dashboard finance stats error: " . $e->getMessage());
    $stats['monthly_donations'] = 25000;
    $stats['monthly_resident_payments'] = 50000;
    $stats['monthly_revenue'] = $stats['monthly_donations'] + $stats['monthly_resident_payments'];
    $stats['monthly_expenses'] = 35000;
    $stats['net_income'] = $stats['monthly_revenue'] - $stats['monthly_expenses'];
}

// === CONTACT MESSAGE STATISTICS ===
try {
    $contact_query = "SELECT 
        COUNT(*) as total_messages,
        COUNT(CASE WHEN status = 'unread' THEN 1 END) as unread_messages,
        COUNT(CASE WHEN status = 'read' THEN 1 END) as read_messages,
        COUNT(CASE WHEN status = 'replied' THEN 1 END) as replied_messages,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_messages
        FROM contact_messages";
    $contact_stmt = $db->prepare($contact_query);
    $contact_stmt->execute();
    $contact_data = $contact_stmt->fetch(PDO::FETCH_ASSOC);
    $stats = array_merge($stats, $contact_data);
} catch (Exception $e) {
    // Fallback if contact_messages table doesn't exist
    $stats['total_messages'] = 0;
    $stats['unread_messages'] = 0;
    $stats['read_messages'] = 0;
    $stats['replied_messages'] = 0;
    $stats['today_messages'] = 0;
}

// === SUBSCRIPTION STATISTICS ===
try {
    $subscription_query = "SELECT 
        COUNT(CASE WHEN pp.id != 1 THEN 1 END) as paid_residents,
        COUNT(CASE WHEN pp.id = 1 THEN 1 END) as basic_residents,
        COUNT(CASE WHEN rh.renewal_due_date <= CURDATE() AND rh.renewal_due_date >= DATE_SUB(CURDATE(), INTERVAL 5 DAY) THEN 1 END) as renewal_due,
        COUNT(CASE WHEN rh.grace_period_end < CURDATE() THEN 1 END) as overdue_payments
        FROM residents r
        JOIN users u ON r.user_id = u.id
        JOIN payment_plans pp ON r.plan_id = pp.id
        LEFT JOIN resident_revenue_history rh ON r.id = rh.resident_id AND rh.is_active = 1
        WHERE u.is_active = 1";
    $subscription_stmt = $db->prepare($subscription_query);
    $subscription_stmt->execute();
    $subscription_data = $subscription_stmt->fetch(PDO::FETCH_ASSOC);
    $stats = array_merge($stats, $subscription_data);
} catch (Exception $e) {
    // Fallback if tables don't exist
    $stats['paid_residents'] = 0;
    $stats['basic_residents'] = $stats['active_residents'];
    $stats['renewal_due'] = 0;
    $stats['overdue_payments'] = 0;
}

// === SERVICE STATISTICS ===
try {
    $service_query = "SELECT 
        COUNT(*) as total_requests,
        COUNT(CASE WHEN status IN ('Requested', 'Scheduled') THEN 1 END) as pending_requests,
        COUNT(CASE WHEN status = 'In Progress' THEN 1 END) as in_progress_requests,
        COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_requests
        FROM service_requests";
    $service_stmt = $db->prepare($service_query);
    $service_stmt->execute();
    $service_data = $service_stmt->fetch(PDO::FETCH_ASSOC);
    $stats = array_merge($stats, $service_data);
} catch (Exception $e) {
    $stats['total_requests'] = 0;
    $stats['pending_requests'] = 0;
    $stats['in_progress_requests'] = 0;
    $stats['completed_requests'] = 0;
}

// === INVENTORY STATISTICS ===
try {
    $inventory_query = "SELECT 
        COUNT(*) as total_items,
        COUNT(CASE WHEN current_stock <= minimum_stock THEN 1 END) as low_stock_items,
        COUNT(CASE WHEN current_stock = 0 THEN 1 END) as out_of_stock_items,
        COUNT(CASE WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as expiring_items
        FROM inventory_items WHERE is_active = 1";
    $inventory_stmt = $db->prepare($inventory_query);
    $inventory_stmt->execute();
    $inventory_data = $inventory_stmt->fetch(PDO::FETCH_ASSOC);
    $stats = array_merge($stats, $inventory_data);
} catch (Exception $e) {
    $stats['total_items'] = 0;
    $stats['low_stock_items'] = 0;
    $stats['out_of_stock_items'] = 0;
    $stats['expiring_items'] = 0;
}

// === DONATION STATISTICS ===
try {
    $donation_query = "SELECT 
        COUNT(*) as total_donations,
        COUNT(CASE WHEN is_verified = 1 THEN 1 END) as verified_donations,
        COUNT(CASE WHEN is_verified = 0 THEN 1 END) as pending_donations,
        COALESCE(SUM(CASE WHEN is_verified = 1 THEN amount END), 0) as total_donation_amount
        FROM donations";
    $donation_stmt = $db->prepare($donation_query);
    $donation_stmt->execute();
    $donation_data = $donation_stmt->fetch(PDO::FETCH_ASSOC);
    $stats = array_merge($stats, $donation_data);
} catch (Exception $e) {
    $stats['total_donations'] = 0;
    $stats['verified_donations'] = 0;
    $stats['pending_donations'] = 0;
    $stats['total_donation_amount'] = 0;
}

// Removed recent residents, donations, and pending services queries
// Dashboard now focuses on key metrics only
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Maple House</title>
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
            height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            transition: all 0.3s ease;
        }
        .dashboard-layout {
            display: flex;
            height: 100vh;
            width: 100%;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: white;
            color: #2c3e50;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 15px rgba(0,0,0,0.1);
            z-index: 1000;
            transition: width 0.3s ease;
            overflow: hidden;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
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
        }

        .nav-items {
            list-style: none;
            padding: 20px 0;
            margin: 0;
            flex: 1;
        }

        .nav-items li {
            position: relative;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #2c3e50;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            white-space: nowrap;
        }

        .nav-link:hover {
            background: rgba(52, 152, 219, 0.1);
            color: #2c3e50;
            border-left-color: #3498db;
        }

        .nav-link.active {
            background: rgba(52, 152, 219, 0.15);
            color: #2c3e50;
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

        /* Dropdown Styles */
        .dropdown {
            position: relative;
        }
        .dropdown-toggle {
            justify-content: space-between;
            cursor: pointer;
        }
        .dropdown-icon {
            transition: transform 0.3s ease;
            font-size: 0.8rem;
        }
        .dropdown.open .dropdown-icon {
            transform: rotate(180deg);
        }
        .dropdown-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            background: rgba(0,0,0,0.05);
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        .dropdown.open .dropdown-menu {
            max-height: 300px;
        }
        .dropdown-menu li a {
            padding: 10px 20px 10px 35px;
            font-size: 0.9rem;
            border-left: none;
            color: #2c3e50;
            display: flex;
            align-items: center;
        }
        .dropdown-menu li a:hover {
            background: rgba(52, 152, 219, 0.1);
            border-left: 3px solid #3498db;
        }
        .dropdown-menu li a i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
            font-size: 0.9rem;
        }

        /* Sidebar Footer */
        .sidebar-footer {
            border-top: 1px solid rgba(0,0,0,0.1);
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
            transition: margin-left 0.3s ease;
            margin-left: 0;
        }
        .top-header {
            background: white;
            padding: 10px 30px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            min-height: 55px;
        }
        .header-left {
            display: flex;
            align-items: center;
        }
        .header-titles h1 {
            margin: 0 0 3px 0;
            font-size: 1.6rem;
            color: #2c3e50;
        }
        .header-titles p {
            margin: 0;
            color: #666;
            font-size: 0.85rem;
        }
        .user-info {
            text-align: right;
        }
        .user-info h3 {
            margin: 0 0 3px 0;
            font-size: 1.05rem;
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
        .role-badge.admin {
            background: #e74c3c;
        }

        /* Content Container */
        .content-container {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            position: relative;
        }
        .content-view {
            display: none;
            height: 100%;
        }
        .content-view.active {
            display: block;
        }

        /* Stats Grid */
        .admin-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .user-section {
            margin-bottom: 15px;
        }
        .finance-section {
            margin-bottom: 15px;
        }
        .inventory-section {
            margin-bottom: 15px;
        }
        .service-section {
            margin-bottom: 15px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 2px solid #e2e8f0;
        }
        
        /* Default stat cards (User Management) */
        .user-section .stat-card:nth-child(1) {
            border-color: #3498db;
        }
        
        .user-section .stat-card:nth-child(2) {
            border-color: #9b59b6;
        }
        
        .user-section .stat-card:nth-child(3) {
            border-color: #e67e22;
        }
        
        .user-section .stat-card:nth-child(4) {
            border-color: #2ecc71;
        }
        
        /* Financial cards */
        .stat-card.success {
            border-color: #27ae60;
        }
        
        .stat-card.warning {
            border-color: #f39c12;
        }
        
        .stat-card.danger {
            border-color: #e74c3c;
        }
        
        .stat-card.info {
            border-color: #3498db;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .stat-card i {
            font-size: 2.5rem;
            margin-right: 20px;
            color: #3498db;
        }
        .stat-card.success i {
            color: #27ae60;
        }
        .stat-card.warning i {
            color: #f39c12;
        }
        .stat-card.danger i {
            color: #e74c3c;
        }
        .stat-number {
            font-size: 1.6rem;
            font-weight: bold;
            color: #2c3e50;
            line-height: 1.1;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .stat-label {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        .stat-sublabel {
            font-size: 0.75rem;
            color: #888;
            margin-top: 3px;
            line-height: 1.2;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        .widget {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }
        .widget-header {
            background: white;
            color: #2c3e50;
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
        }
        .widget-header h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .widget-content {
            padding: 25px;
        }

        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .data-table th, .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        .data-table th {
            background: white;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e9ecef;
        }
        .data-table tr:hover {
            background: #f8f9fa;
        }

        /* Status Badge */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status-badge.requested {
            background: white;
            color: #856404;
            border: 1px solid #856404;
        }
        .status-badge.scheduled {
            background: white;
            color: #155724;
            border: 1px solid #155724;
        }
        .status-badge.in_progress {
            background: white;
            color: #004085;
            border: 1px solid #004085;
        }
        .status-badge.completed {
            background: white;
            color: #155724;
            border: 1px solid #155724;
        }

        /* Button Styles */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-1px);
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* Sidebar Collapse Styles */
        body.sidebar-hidden .sidebar {
            width: 70px;
        }
        body.sidebar-hidden .sidebar .nav-text,
        body.sidebar-hidden .sidebar-header h2,
        body.sidebar-hidden .sidebar-footer {
            opacity: 0;
            display: none;
        }
        body.sidebar-hidden .sidebar .nav-link {
            justify-content: center;
        }
        body.sidebar-hidden .sidebar .nav-link i {
            margin-right: 0;
        }
        body.sidebar-hidden .main-content {
            margin-left: 0;
        }
        body.sidebar-hidden .sidebar .dropdown-icon {
            display: none;
        }
        
        /* Dropdown hover behavior for collapsed sidebar */
        body.sidebar-hidden .sidebar .dropdown-menu {
            position: absolute;
            left: 100%;
            top: 0;
            width: 200px;
            background: #ffffffff;
            border-radius: 0 8px 8px 0;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.2);
            z-index: 1001;
            padding: 10px 0;
            transition: max-height 0.3s ease;
        }

        body.sidebar-hidden .sidebar .dropdown:hover .dropdown-menu {
            max-height: 300px; /* Adjust as needed */
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-home"></i><span class="nav-text"> Maple House</span></h2>
            </div>
            
            <div class="sidebar-menu">
                <ul class="nav-items">
                    <li><a href="#" onclick="showDashboard()" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i> <span class="nav-text">Dashboard</span>
                    </a></li>
                    
                    <li><a href="#" onclick="loadPage('management.php')" class="nav-link">
                        <i class="fas fa-users-cog"></i> <span class="nav-text">User Management</span>
                    </a></li>
                
                    <li><a href="#" onclick="loadPage('meal_plan.php')" class="nav-link">
                        <i class="fas fa-concierge-bell"></i> <span class="nav-text">Meal Plans</span>
                    </a></li>
                    <li><a href="#" onclick="loadPage('meal_items.php')" class="nav-link">
                        <i class="fas fa-utensils"></i> <span class="nav-text">Meal Items</span>
                    </a></li>
                    <li><a href="#" onclick="loadPage('inventory_overview.php')" class="nav-link">
                        <i class="fas fa-boxes-stacked"></i> <span class="nav-text">Inventory</span>
                    </a></li>
                    <li><a href="#" onclick="loadPage('services.php')" class="nav-link">
                        <i class="fas fa-tasks"></i> <span class="nav-text">Service Requests</span>
                    </a></li>
                    <li><a href="#" onclick="loadPage('occupancy_overview.php')" class="nav-link">
                        <i class="fas fa-bed"></i> <span class="nav-text">Occupancy Reports</span>
                    </a></li>
                    <li><a href="#" onclick="loadPage('finances_overview.php')" class="nav-link">
                        <i class="fas fa-chart-line"></i> <span class="nav-text">Finance</span>
                    </a></li>
                    <li><a href="#" onclick="loadPage('expenses.php')" class="nav-link">
                        <i class="fas fa-receipt"></i> <span class="nav-text">Expense Management</span>
                    </a></li>
                    <li><a href="#" onclick="loadPage('donations_management.php')" class="nav-link">
                        <i class="fas fa-heart"></i> <span class="nav-text">Donations</span>
                    </a></li>
                    <li><a href="#" onclick="loadPage('contact_messages.php')" class="nav-link">
                        <i class="fas fa-envelope"></i> <span class="nav-text">Contact Messages</span>
                    </a></li>
                </ul>
                
                <div class="sidebar-footer">
                    <a href="../logout.php" class="nav-link logout-btn">
                        <i class="fas fa-sign-out-alt"></i> <span class="nav-text">Logout</span>
                    </a>
                </div>
            </div>
        </nav>

        <div class="main-content">
            <div class="top-header">
                <div class="header-left">
                    <div class="header-titles">
                        <h1 id="page-title">Admin Dashboard</h1>
                        <p id="page-subtitle">System Overview & Management</p>
                    </div>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <h3><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
                        <span class="role-badge admin"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
                        <div style="margin-top: 5px;">
                            <small>Last login: <?php echo date('M j, Y g:i A'); ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-container">
                <div id="dashboard-content" class="content-view active">
                    <!-- 1. USER SECTION -->
                    <div class="user-section">
                        <h2 style="margin-bottom: 20px; color: #2c3e50;"><i class="fas fa-users"></i> User Management</h2>
                        <div class="admin-stats">
                            <div class="stat-card">
                                <i class="fas fa-users"></i>
                                <div>
                                    <div class="stat-number"><?php echo $stats['active_residents']; ?></div>
                                    <div class="stat-label">Active Residents</div>
                                    <div class="stat-sublabel"><?php echo $stats['occupancy_rate']; ?>% Occupancy Rate</div>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <i class="fas fa-user-tie"></i>
                                <div>
                                    <div class="stat-number"><?php echo $stats['active_staff']; ?></div>
                                    <div class="stat-label">Active Staff</div>
                                    <div class="stat-sublabel"><?php echo $stats['total_staff']; ?> Total Staff</div>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <i class="fas fa-user-md"></i>
                                <div>
                                    <div class="stat-number"><?php echo $stats['active_doctors']; ?></div>
                                    <div class="stat-label">Active Doctors</div>
                                    <div class="stat-sublabel"><?php echo $stats['total_doctors']; ?> Total Doctors</div>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <i class="fas fa-utensils"></i>
                                <div>
                                    <div class="stat-number"><?php echo $stats['active_chefs']; ?></div>
                                    <div class="stat-label">Active Chefs</div>
                                    <div class="stat-sublabel"><?php echo $stats['total_chefs']; ?> Total Chefs</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 2. FINANCE SECTION -->
                    <div class="finance-section">
                        <h2 style="margin: 30px 0 20px 0; color: #2c3e50;"><i class="fas fa-chart-line"></i> Financial Overview</h2>
                        <div class="admin-stats">
                            <div class="stat-card success">
                                <i class="fas fa-money-bill-wave"></i>
                                <div>
                                    <div class="stat-number">৳<?php echo number_format($stats['monthly_revenue'], 0); ?></div>
                                    <div class="stat-label">Monthly Revenue</div>
                                    <div class="stat-sublabel">Residents: ৳<?php echo number_format($stats['monthly_resident_payments'], 0); ?> | Donations: ৳<?php echo number_format($stats['monthly_donations'], 0); ?></div>
                                </div>
                            </div>
                            
                            <div class="stat-card warning">
                                <i class="fas fa-hand-holding-usd"></i>
                                <div>
                                    <div class="stat-number">৳<?php echo number_format($stats['monthly_expenses'], 0); ?></div>
                                    <div class="stat-label">Monthly Expenses</div>
                                    <div class="stat-sublabel">Operational Costs</div>
                                </div>
                            </div>
                            
                            <div class="stat-card <?php echo $stats['net_income'] >= 0 ? 'success' : 'danger'; ?>">
                                <i class="fas fa-chart-line"></i>
                                <div>
                                    <div class="stat-number">৳<?php echo number_format($stats['net_income'], 0); ?></div>
                                    <div class="stat-label">Net Income</div>
                                    <div class="stat-sublabel"><?php echo $stats['net_income'] >= 0 ? 'Profit' : 'Loss'; ?> This Month</div>
                                </div>
                            </div>
                            
                            <div class="stat-card info">
                                <i class="fas fa-heart"></i>
                                <div>
                                    <div class="stat-number">৳<?php echo number_format($stats['monthly_donations'], 0); ?></div>
                                    <div class="stat-label">Monthly Donations</div>
                                    <div class="stat-sublabel"><?php echo $stats['verified_donations']; ?> Verified | <?php echo $stats['pending_donations']; ?> Pending</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 3. INVENTORY SECTION -->
                    <div class="inventory-section">
                        <h2 style="margin: 30px 0 20px 0; color: #2c3e50;"><i class="fas fa-boxes"></i> Inventory Management</h2>
                        <div class="admin-stats">
                            <div class="stat-card">
                                <i class="fas fa-boxes"></i>
                                <div>
                                    <div class="stat-number"><?php echo $stats['total_items']; ?></div>
                                    <div class="stat-label">Total Items</div>
                                    <div class="stat-sublabel">In Inventory</div>
                                </div>
                            </div>
                            
                            <div class="stat-card <?php echo $stats['low_stock_items'] > 0 ? 'warning' : 'success'; ?>">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div>
                                    <div class="stat-number"><?php echo $stats['low_stock_items']; ?></div>
                                    <div class="stat-label">Low Stock Items</div>
                                    <div class="stat-sublabel">Need Restocking</div>
                                </div>
                            </div>
                            
                            <div class="stat-card <?php echo $stats['out_of_stock_items'] > 0 ? 'danger' : 'success'; ?>">
                                <i class="fas fa-times-circle"></i>
                                <div>
                                    <div class="stat-number"><?php echo $stats['out_of_stock_items']; ?></div>
                                    <div class="stat-label">Out of Stock</div>
                                    <div class="stat-sublabel">Unavailable Items</div>
                                </div>
                            </div>
                            
                            <div class="stat-card <?php echo $stats['expiring_items'] > 0 ? 'warning' : 'success'; ?>">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <div class="stat-number"><?php echo $stats['expiring_items']; ?></div>
                                    <div class="stat-label">Expiring Soon</div>
                                    <div class="stat-sublabel">Within 30 Days</div>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    
                    <!-- 4. SERVICE REQUESTS SECTION -->
                    <div class="service-section">
                        <h2 style="margin: 30px 0 20px 0; color: #2c3e50;"><i class="fas fa-tasks"></i> Service Management</h2>
                        <div class="admin-stats">
                            <div class="stat-card <?php echo $stats['pending_requests'] > 5 ? 'warning' : 'success'; ?>">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                                    <div class="stat-label">Pending Requests</div>
                                    <div class="stat-sublabel">Awaiting Assignment</div>
                                </div>
                            </div>
                            
                            <div class="stat-card info">
                                <i class="fas fa-spinner"></i>
                                <div>
                                    <div class="stat-number"><?php echo $stats['in_progress_requests']; ?></div>
                                    <div class="stat-label">In Progress</div>
                                    <div class="stat-sublabel">Being Worked On</div>
                                </div>
                            </div>
                            
                            <div class="stat-card success">
                                <i class="fas fa-check-circle"></i>
                                <div>
                                    <div class="stat-number"><?php echo $stats['completed_requests']; ?></div>
                                    <div class="stat-label">Completed</div>
                                    <div class="stat-sublabel">Finished Requests</div>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <i class="fas fa-tasks"></i>
                                <div>
                                    <div class="stat-number"><?php echo $stats['total_requests']; ?></div>
                                    <div class="stat-label">Total Requests</div>
                                    <div class="stat-sublabel">All Time</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dashboard now focuses on key metrics only -->
                </div>

                <div id="page-content" class="content-view">
                    <iframe id="content-frame" src="" style="width: 100%; height: 100%; border: none; background: white; border-radius: 8px;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script>
        // URL Router System
        function toggleDropdown(id) {
            const dropdown = document.getElementById(id);
            const parentLi = dropdown.parentElement;
            parentLi.classList.toggle('open');
        }

        function toggleSidebar() {
            document.body.classList.toggle('sidebar-hidden');
            
            // Save sidebar state to localStorage
            const isHidden = document.body.classList.contains('sidebar-hidden');
            localStorage.setItem('sidebarHidden', isHidden);
        }

        // Update URL without page reload
        function updateURL(page) {
            const newURL = page === 'dashboard' ? 
                window.location.pathname + '?page=dashboard' : 
                window.location.pathname + '?page=' + page.replace('.php', '');
            window.history.pushState({ page: page }, '', newURL);
        }

        // Get current page from URL
        function getCurrentPage() {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page');
            return page || 'dashboard';
        }

        // Load saved sidebar state from localStorage
        function loadSidebarState() {
            const sidebarHidden = localStorage.getItem('sidebarHidden');
            if (sidebarHidden === 'true') {
                document.body.classList.add('sidebar-hidden');
            } else {
                document.body.classList.remove('sidebar-hidden');
            }
        }

        function showDashboard(updateHistory = true) {
            document.getElementById('page-title').innerText = 'Admin Dashboard';
            document.getElementById('page-subtitle').innerText = 'System Overview & Management';
            document.getElementById('dashboard-content').classList.add('active');
            document.getElementById('page-content').classList.remove('active');
            
            // Remove active class from all nav links
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            // Add active class to the dashboard link
            document.querySelector('a[onclick="showDashboard()"]').classList.add('active');
            
            // Update URL
            if (updateHistory) {
                updateURL('dashboard');
            }
        }
        
        function loadPage(pageUrl, updateHistory = true) {
            console.log('Loading page:', pageUrl);
            document.getElementById('dashboard-content').classList.remove('active');
            document.getElementById('page-content').classList.add('active');
            
            const contentFrame = document.getElementById('content-frame');
            console.log('Setting iframe src to:', pageUrl);
            contentFrame.src = pageUrl;
            
            // Debug: Check if iframe is loading
            contentFrame.onload = function() {
                console.log('Iframe loaded successfully');
            };
            contentFrame.onerror = function() {
                console.log('Iframe failed to load');
            };
            
            // Update header based on page
            let title, subtitle;
            switch(pageUrl) {
                case 'add_resident.php':
                    title = 'Add Resident';
                    subtitle = 'Onboard a new resident to the system';
                    break;
                case 'add_staff.php':
                    title = 'Add Staff';
                    subtitle = 'Register a new staff member';
                    break;
                case 'add_doctor.php':
                    title = 'Add Doctor';
                    subtitle = 'Register a new doctor';
                    break;
                case 'add_chef.php':
                    title = 'Add Chef';
                    subtitle = 'Add a new chef';
                    break;
                case 'residents.php':
                    title = 'Residents';
                    subtitle = 'Manage resident information';
                    break;
                case 'staff.php':
                    title = 'Staff';
                    subtitle = 'Manage staff roles and access';
                    break;
                case 'medical_records.php':
                    title = 'Medical Records';
                    subtitle = 'Manage resident medical history';
                    break;
                case 'meal_plan.php':
                    title = 'Meal Plans';
                    subtitle = 'Manage weekly meal plans';
                    break;
                case 'payments.php':
                    title = 'Payments';
                    subtitle = 'Handle resident payments and invoices';
                    break;
                case 'donations.php':
                    title = 'Donations';
                    subtitle = 'Manage and track donations';
                    break;
                case 'services.php':
                    title = 'Service Requests';
                    subtitle = 'Manage all resident service requests';
                    break;
                case 'finances.php':
                    title = 'Finance';
                    subtitle = 'Financial management and reports';
                    break;
                case 'financial_reports.php':
                    title = 'Financial Reports';
                    subtitle = 'View financial summaries and analytics';
                    break;
                case 'occupancy_reports.php':
                    title = 'Occupancy Reports';
                    subtitle = 'Analyze room occupancy data';
                    break;
                case 'inventory.php':
                    title = 'Inventory';
                    subtitle = 'Manage inventory and supplies';
                    break;
                case 'contact_messages.php':
                    title = 'Contact Messages';
                    subtitle = 'Manage website contact form submissions';
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
                const parentDropdown = currentLink.closest('.dropdown');
                if (parentDropdown) {
                    parentDropdown.classList.add('open');
                }
            }
            
            // Update URL
            if (updateHistory) {
                updateURL(pageUrl);
            }
        }

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(event) {
            const page = getCurrentPage();
            if (page === 'dashboard') {
                showDashboard(false);
            } else {
                loadPage(page + '.php', false);
            }
        });

        // Load correct page on initial load based on URL
        document.addEventListener('DOMContentLoaded', function() {
            // Load saved sidebar state first
            loadSidebarState();
            
            const currentPage = getCurrentPage();
            if (currentPage === 'dashboard') {
                showDashboard(false);
            } else {
                // Validate that the page exists in our navigation before loading
                const validPages = [
                    'add_resident', 'add_staff', 'add_doctor', 'add_chef',
                    'meal_plan', 'meal_items', 'inventory_overview', 'inventory', 'services', 
                    'occupancy_overview', 'finances_overview', 'expenses', 
                    'staff_salaries', 'donations_management', 'residents', 'staff', 'doctors', 'chefs',
                    'management', 'medical_records', 'payments', 'financial_reports'
                ];
                
                if (validPages.includes(currentPage)) {
                    loadPage(currentPage + '.php', false);
                } else {
                    // If invalid page, redirect to dashboard
                    showDashboard(true);
                }
            }
        });
    </script>
</body>
</html>