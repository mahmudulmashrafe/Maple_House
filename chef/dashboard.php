<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

// Check if user is logged in and is a chef
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Chef') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get chef information
$chef_query = "SELECT c.*, u.first_name, u.last_name FROM chefs c 
               JOIN users u ON c.user_id = u.id 
               WHERE c.user_id = :user_id";
$chef_stmt = $db->prepare($chef_query);
$chef_stmt->bindParam(':user_id', $_SESSION['user_id']);
$chef_stmt->execute();
$chef = $chef_stmt->fetch(PDO::FETCH_ASSOC);

// If no chef record found, create a basic one or use user info
if (!$chef) {
    // Get user info as fallback
    $user_query = "SELECT first_name, last_name FROM users WHERE id = :user_id";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $user_stmt->execute();
    $user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Create a fallback chef array
    $chef = [
        'first_name' => $user_info['first_name'] ?? 'Chef',
        'last_name' => $user_info['last_name'] ?? 'User',
        'shift_hours' => '6:00 AM - 2:00 PM, 4:00 PM - 8:00 PM',
        'specialization' => 'General Cooking',
        'experience_years' => 0
    ];
}

// Get active meal plans count only
try {
    $active_meal_plans_query = "SELECT COUNT(*) as count FROM meal_plans mp
                               WHERE mp.is_active = 1 
                               AND mp.valid_from <= CURDATE() 
                               AND mp.valid_to >= CURDATE()";
    $active_meal_plans_stmt = $db->prepare($active_meal_plans_query);
    $active_meal_plans_stmt->execute();
    $active_meal_plans_count = $active_meal_plans_stmt->fetchColumn();
} catch (Exception $e) {
    $active_meal_plans_count = 0;
}

// Get monthly cost tracking
try {
    $monthly_cost_query = "SELECT COALESCE(SUM(cost_per_serving * (SELECT COUNT(*) FROM residents WHERE DATE(created_at) <= meal_date)), 0) as total_cost
                           FROM daily_meals 
                           WHERE MONTH(meal_date) = MONTH(CURDATE()) 
                           AND YEAR(meal_date) = YEAR(CURDATE())";
    $monthly_cost_stmt = $db->prepare($monthly_cost_query);
    $monthly_cost_stmt->execute();
    $monthly_cost = $monthly_cost_stmt->fetchColumn();
} catch (Exception $e) {
    $monthly_cost = 0;
}

// Get total residents count
try {
    $residents_count_query = "SELECT COUNT(*) as count FROM residents";
    $residents_count_stmt = $db->prepare($residents_count_query);
    $residents_count_stmt->execute();
    $residents_count = $residents_count_stmt->fetchColumn();
} catch (Exception $e) {
    $residents_count = 0;
}

// Simple statistics for dashboard
$stats = [];
$stats['active_meal_plans'] = $active_meal_plans_count;
$stats['monthly_cost'] = $monthly_cost;
$stats['total_residents'] = $residents_count;
$stats['meals_this_week'] = 0; // Can be calculated if needed
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chef Dashboard - Maple House</title>
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

        .nav-items {
            flex: 1;
            list-style: none;
            padding: 0;
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
            background: #3498db;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .role-badge.chef {
            background: #e67e22;
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
        .stat-card:nth-child(3) i { background: #e67e22; }
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
        
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-utensils"></i><span class="nav-text"> Maple House Kitchen</span></h2>
            </div>
            
            <div class="sidebar-menu">
                <ul class="nav-items">
                    <li><a href="?page=dashboard" onclick="event.preventDefault(); showPage('dashboard')" class="nav-link active" data-page="dashboard"><i class="fas fa-tachometer-alt"></i> <span class="nav-text">Dashboard</span></a></li>
                    <li><a href="?page=meal_planning" onclick="event.preventDefault(); showPage('meal_planning')" class="nav-link" data-page="meal_planning"><i class="fas fa-calendar-alt"></i> <span class="nav-text">Meal Planning</span></a></li>
                    <li><a href="?page=daily" onclick="event.preventDefault(); showPage('daily')" class="nav-link" data-page="daily"><i class="fas fa-list"></i> <span class="nav-text">Daily Menu</span></a></li>
                    <li><a href="?page=meal_items" onclick="event.preventDefault(); showPage('meal_items')" class="nav-link" data-page="meal_items"><i class="fas fa-utensils"></i> <span class="nav-text">Meal Items</span></a></li>
                    <li><a href="?page=inventory" onclick="event.preventDefault(); showPage('inventory')" class="nav-link" data-page="inventory"><i class="fas fa-boxes"></i> <span class="nav-text">Inventory</span></a></li>
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
                        <h1 id="page-title">Chef Dashboard</h1>
                        <p id="page-subtitle">Kitchen Operations • <?php echo htmlspecialchars($chef['shift_hours']); ?></p>
                    </div>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <h3><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
                        <span class="role-badge chef">Chef</span>
                    </div>
                </div>
            </div>

            <div class="content-container">
                
                <div class="page-section active" id="dashboard-page">
                    <div class="welcome-section">
                        <h1 class="welcome-title">Welcome, Chef <?php echo htmlspecialchars($chef['first_name'] . ' ' . $chef['last_name']); ?>!</h1>
                        <p class="welcome-subtitle">Kitchen Operations • <?php echo htmlspecialchars($chef['shift_hours']); ?></p>
                    </div>
    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="fas fa-clipboard-list"></i>
                            <div class="stat-info">
                                <h3><?php echo $stats['active_meal_plans']; ?></h3>
                                <p>Active Meal Plans</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <i class="fas fa-users"></i>
                            <div class="stat-info">
                                <h3><?php echo $stats['total_residents']; ?></h3>
                                <p>Total Residents</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <i class="fas fa-money-bill-wave"></i>
                            <div class="stat-info">
                                <h3>৳<?php echo number_format($stats['monthly_cost']); ?></h3>
                                <p>Monthly Food Cost</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <i class="fas fa-utensils"></i>
                            <div class="stat-info">
                                <h3><?php echo $stats['meals_this_week']; ?></h3>
                                <p>Meals This Week</p>
                            </div>
                        </div>
                    </div>
    
                    <div class="content-area">
                        <h2><i class="fas fa-home"></i> Dashboard Overview</h2>
                        <p>Welcome to your kitchen management dashboard. Use the sidebar navigation to access different sections:</p>
                        
                        <div style="margin-top: 30px;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                                <div style="padding: 20px; border: 1px solid #dee2e6; border-radius: 8px; cursor: pointer;" onclick="showPage('meal_planning')">
                                    <h4><i class="fas fa-calendar-alt"></i> Meal Planning</h4>
                                    <p>Plan and schedule meals for residents with dietary requirements.</p>
                                </div>
                                
                                <div style="padding: 20px; border: 1px solid #dee2e6; border-radius: 8px; cursor: pointer;" onclick="showPage('daily')">
                                    <h4><i class="fas fa-list"></i> Daily Menu</h4>
                                    <p>Manage today's menu and meal preparations.</p>
                                </div>
                                
                                <div style="padding: 20px; border: 1px solid #dee2e6; border-radius: 8px; cursor: pointer;" onclick="showPage('inventory')">
                                    <h4><i class="fas fa-boxes"></i> Inventory</h4>
                                    <p>Track kitchen inventory and ingredient supplies.</p>
                                </div>
                                
                                <div style="padding: 20px; border: 1px solid #dee2e6; border-radius: 8px; cursor: pointer;" onclick="showPage('costs')">
                                    <h4><i class="fas fa-calculator"></i> Cost Tracking</h4>
                                    <p>Monitor food costs and budget management.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="page-section" id="meal_planning-page">
                    <iframe id="meal_planning-frame" src="meal_planning.php" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
                <div class="page-section" id="daily-page">
                    <iframe id="daily-frame" src="daily.php" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
                <div class="page-section" id="meal_items-page">
                    <iframe id="meal_items-frame" src="meal_items.php" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
                <div class="page-section" id="inventory-page">
                    <iframe id="inventory-frame" src="inventory_usage.php" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
                <div class="page-section" id="costs-page">
                    <iframe id="costs-frame" src="costs.php" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
                <div class="page-section" id="reports-page">
                    <iframe id="reports-frame" src="reports.php" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>

            </div>
        </div>
    </div>

    <script>

        function showPage(page) {
            // Hide all page sections
            document.querySelectorAll('.page-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected page section
            const pageSection = document.getElementById(page + '-page');
            pageSection.classList.add('active');

            // If the section contains an iframe, make sure its source is set
            const pageFrame = document.getElementById(page + '-frame');
            if (pageFrame && !pageFrame.src) {
                pageFrame.src = page + '.php';
            }
            
            // Update active menu item
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelector(`[data-page="${page}"]`).classList.add('active');

            // Update header titles
            let title, subtitle;
            switch(page) {
                case 'dashboard':
                    title = 'Chef Dashboard';
                    subtitle = 'Kitchen Operations';
                    break;
                case 'meal_planning':
                    title = 'Meal Planning';
                    subtitle = 'Create and manage meal schedules';
                    break;
                case 'daily':
                    title = 'Daily Menu';
                    subtitle = 'View and edit today\'s menu';
                    break;
                case 'meal_items':
                    title = 'Meal Items';
                    subtitle = 'Manage meal items database';
                    break;
                case 'inventory':
                    title = 'Inventory';
                    subtitle = 'Track ingredient supplies';
                    break;
                case 'costs':
                    title = 'Cost Tracking';
                    subtitle = 'Monitor monthly food expenses';
                    break;
                case 'reports':
                    title = 'Reports';
                    subtitle = 'View kitchen analytics';
                    break;
                default:
                    title = 'Chef Dashboard';
                    subtitle = 'Kitchen Operations';
                    break;
            }
            // Update page title and subtitle with a small delay to ensure DOM is ready
            setTimeout(() => {
                const titleElement = document.getElementById('page-title');
                const subtitleElement = document.getElementById('page-subtitle');
                
                if (titleElement) {
                    titleElement.innerText = title;
                }
                if (subtitleElement) {
                    subtitleElement.innerText = subtitle;
                }
            }, 100);

            // Update URL without page reload
            const newURL = page === 'dashboard' ? 
                window.location.pathname + '?page=dashboard' : 
                window.location.pathname + '?page=' + page;
            window.history.pushState({page: page}, '', newURL);
            localStorage.setItem('chefCurrentPage', page);
            document.title = title + ' - Maple House';
        }

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(event) {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page') || 'dashboard';
            
            // Call showPage but don't update URL again (to avoid infinite loop)
            document.querySelectorAll('.page-section').forEach(section => {
                section.classList.remove('active');
            });
            
            const pageSection = document.getElementById(page + '-page');
            if (pageSection) {
                pageSection.classList.add('active');
            }

            const pageFrame = document.getElementById(page + '-frame');
            if (pageFrame && !pageFrame.src) {
                pageFrame.src = page + '.php';
            }
            
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            const activeLink = document.querySelector(`[data-page="${page}"]`);
            if (activeLink) {
                activeLink.classList.add('active');
            }

            // Update titles
            let title, subtitle;
            switch(page) {
                case 'dashboard':
                    title = 'Chef Dashboard';
                    subtitle = 'Kitchen Operations';
                    break;
                case 'meal_planning':
                    title = 'Meal Planning';
                    subtitle = 'Create and manage meal schedules';
                    break;
                case 'daily':
                    title = 'Daily Menu';
                    subtitle = 'View and edit today\'s menu';
                    break;
                case 'inventory':
                    title = 'Inventory';
                    subtitle = 'Track ingredient supplies';
                    break;
                case 'costs':
                    title = 'Cost Tracking';
                    subtitle = 'Monitor monthly food expenses';
                    break;
                case 'reports':
                    title = 'Reports';
                    subtitle = 'View kitchen analytics';
                    break;
                default:
                    title = 'Chef Dashboard';
                    subtitle = 'Kitchen Operations';
                    break;
            }
            
            setTimeout(() => {
                const titleElement = document.getElementById('page-title');
                const subtitleElement = document.getElementById('page-subtitle');
                
                if (titleElement) {
                    titleElement.innerText = title;
                }
                if (subtitleElement) {
                    subtitleElement.innerText = subtitle;
                }
            }, 100);
        });

        // Restore sidebar state and current page on page load
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarHidden = localStorage.getItem('sidebarHidden') === 'true';
            if (sidebarHidden) {
                document.body.classList.add('sidebar-hidden');
            } else {
                document.body.classList.remove('sidebar-hidden');
            }

            // Determine initial page from URL or saved state
            let initialPage = 'dashboard';
            try {
                const params = new URLSearchParams(window.location.search);
                initialPage = params.get('page') || localStorage.getItem('chefCurrentPage') || 'dashboard';
            } catch (e) {}
            showPage(initialPage);
        });
    </script>
</body>
</html>