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

// Get service request statistics with error handling
$stats = [
    'total_requests' => 0,
    'pending_requests' => 0,
    'in_progress_requests' => 0,
    'completed_requests' => 0
];

try {
    $stats_query = "SELECT 
                        COUNT(*) as total_requests,
                        COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_requests,
                        COUNT(CASE WHEN status = 'In Progress' THEN 1 END) as in_progress_requests,
                        COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_requests
                    FROM service_requests";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $result = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = $result;
    }
} catch (Exception $e) {
    // Table might not exist, use default values
}

// Initialize empty arrays for demo
$recent_requests = [];
$pending_requests = [];
$categories = [];
$staff_assignments = [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Requests Overview - Maple House</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
        }
        
        .services-buttons {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 5px;
        }
        
        @media (max-width: 768px) {
            .services-buttons {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .services-buttons {
                grid-template-columns: 1fr;
            }
        }
        
        .service-card {
            background: white;
            border-radius: 12px;
            padding: 8px 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            text-decoration: none;
            color: inherit;
        }
        
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #3498db;
        }
        
        .service-card.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .service-card.pending {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .service-card.in-progress {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            color: #333;
        }
        
        .service-card.completed {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        
        .service-card i {
            font-size: 1.3rem;
            margin-bottom: 3px;
            opacity: 0.9;
        }
        
        .service-count {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 1px;
        }
        
        .service-label {
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .service-desc {
            font-size: 0.7rem;
            opacity: 0.8;
        }
        
        .page-wrapper {
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 2px 20px 20px 20px;
        }
        
        .fixed-header {
            flex-shrink: 0;
            margin-bottom: 2px;
        }
        
        .scrollable-content {
            flex: 1;
            overflow-y: auto;
            padding-right: 5px;
        }
        
        .recent-sections {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            align-items: start;
        }
        
        @media (max-width: 768px) {
            .recent-sections {
                grid-template-columns: 1fr;
            }
        }
        
        .recent-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: fit-content;
        }
        
        .section-header {
            padding: 20px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header.recent {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .section-header.pending {
            background: linear-gradient(135deg, #f093fb, #f5576c);
        }
        
        .section-header.categories {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
        }
        
        .section-header.staff {
            background: linear-gradient(135deg, #ffecd2, #fcb69f);
            color: #333;
        }
        
        .section-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }
        
        .section-content {
            padding: 0;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .request-row {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s ease;
        }
        
        .request-row:last-child {
            border-bottom: none;
        }
        
        .request-row:hover {
            background: #f8f9fa;
        }
        
        .request-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .request-details {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.4;
        }
        
        .request-meta {
            font-size: 0.8rem;
            color: #888;
            margin-top: 5px;
        }
        
        .status-badge {
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-in-progress {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .priority-badge {
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
            margin-left: 5px;
        }
        
        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }
        
        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .priority-low {
            background: #d4edda;
            color: #155724;
        }
        
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #666;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        .view-all-btn {
            display: block;
            width: 100%;
            padding: 15px;
            background: #f8f9fa;
            color: #3498db;
            text-align: center;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            border-top: 1px solid #e9ecef;
            margin-top: auto;
            flex-shrink: 0;
        }
        
        .view-all-btn:hover {
            background: #3498db;
            color: white;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Fixed Header with Service Request Buttons -->
        <div class="fixed-header">
            <div class="services-buttons">
        <a href="services.php" class="service-card total">
            <i class="fas fa-tasks"></i>
            <div class="service-count"><?php echo $stats['total_requests']; ?></div>
            <div class="service-label">Total Requests</div>
            <div class="service-desc">All service requests</div>
        </a>
        
        <a href="services.php?filter=pending" class="service-card pending">
            <i class="fas fa-clock"></i>
            <div class="service-count"><?php echo $stats['pending_requests']; ?></div>
            <div class="service-label">Pending</div>
            <div class="service-desc">Awaiting assignment</div>
        </a>
        
        <a href="services.php?filter=in_progress" class="service-card in-progress">
            <i class="fas fa-spinner"></i>
            <div class="service-count"><?php echo $stats['in_progress_requests']; ?></div>
            <div class="service-label">In Progress</div>
            <div class="service-desc">Being worked on</div>
        </a>
        
        <a href="services.php?filter=completed" class="service-card completed">
            <i class="fas fa-check-circle"></i>
            <div class="service-count"><?php echo $stats['completed_requests']; ?></div>
            <div class="service-label">Completed</div>
            <div class="service-desc">Finished requests</div>
        </a>
            </div>
        </div>
        
        <!-- Scrollable Content -->
        <div class="scrollable-content">
            <!-- Recent Sections -->
            <div class="recent-sections">
        <!-- Recent Requests -->
        <div class="recent-section">
            <div class="section-header recent">
                <i class="fas fa-tasks"></i>
                <h3>Recent Requests</h3>
            </div>
            <div class="section-content">
                <?php if (!empty($recent_requests)): ?>
                    <?php foreach (array_slice($recent_requests, 0, 4) as $request): ?>
                        <div class="request-row">
                            <div class="request-title"><?php echo htmlspecialchars($request['service_type']); ?></div>
                            <div class="request-details">
                                <?php echo htmlspecialchars(substr($request['description'], 0, 60)) . '...'; ?>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $request['status'])); ?>">
                                    <?php echo $request['status']; ?>
                                </span>
                                <?php if ($request['priority']): ?>
                                    <span class="priority-badge priority-<?php echo strtolower($request['priority']); ?>">
                                        <?php echo $request['priority']; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="request-meta">
                                By <?php echo htmlspecialchars($request['resident_name'] ?: 'Unknown'); ?> • 
                                <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="services.php" class="view-all-btn">
                        <i class="fas fa-arrow-right"></i> View All Requests
                    </a>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <p>No service requests found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Requests -->
        <div class="recent-section">
            <div class="section-header pending">
                <i class="fas fa-clock"></i>
                <h3>Urgent Pending</h3>
            </div>
            <div class="section-content">
                <?php if (!empty($pending_requests)): ?>
                    <?php foreach (array_slice($pending_requests, 0, 4) as $request): ?>
                        <div class="request-row">
                            <div class="request-title"><?php echo htmlspecialchars($request['service_type']); ?></div>
                            <div class="request-details">
                                <?php echo htmlspecialchars(substr($request['description'], 0, 50)) . '...'; ?>
                                <?php if ($request['priority']): ?>
                                    <span class="priority-badge priority-<?php echo strtolower($request['priority']); ?>">
                                        <?php echo $request['priority']; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="request-meta">
                                <?php echo htmlspecialchars($request['resident_name'] ?: 'Unknown'); ?> • 
                                <?php echo date('M j, g:i A', strtotime($request['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="services.php?filter=pending" class="view-all-btn">
                        <i class="fas fa-arrow-right"></i> View All Pending
                    </a>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No pending requests!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Service Categories -->
        <div class="recent-section">
            <div class="section-header categories">
                <i class="fas fa-chart-pie"></i>
                <h3>Service Categories</h3>
            </div>
            <div class="section-content">
                <?php if (!empty($categories)): ?>
                    <?php foreach (array_slice($categories, 0, 4) as $category): ?>
                        <div class="request-row">
                            <div class="request-title"><?php echo htmlspecialchars($category['service_type']); ?></div>
                            <div class="request-details">
                                Total: <?php echo $category['count']; ?> requests • 
                                Completed: <?php echo $category['completed_count']; ?>
                            </div>
                            <div class="request-meta">
                                Completion Rate: <?php echo $category['count'] > 0 ? round(($category['completed_count'] / $category['count']) * 100) : 0; ?>%
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="services.php?view=categories" class="view-all-btn">
                        <i class="fas fa-arrow-right"></i> View All Categories
                    </a>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-pie"></i>
                        <p>No category data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Staff Performance -->
        <div class="recent-section">
            <div class="section-header staff">
                <i class="fas fa-users"></i>
                <h3>Staff Performance</h3>
            </div>
            <div class="section-content">
                <?php if (!empty($staff_assignments)): ?>
                    <?php foreach (array_slice($staff_assignments, 0, 4) as $staff): ?>
                        <div class="request-row">
                            <div class="request-title"><?php echo htmlspecialchars($staff['staff_name']); ?></div>
                            <div class="request-details">
                                Assigned: <?php echo $staff['assigned_count']; ?> • 
                                Completed: <?php echo $staff['completed_count']; ?>
                            </div>
                            <div class="request-meta">
                                Success Rate: <?php echo $staff['assigned_count'] > 0 ? round(($staff['completed_count'] / $staff['assigned_count']) * 100) : 0; ?>%
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="services.php?view=staff" class="view-all-btn">
                        <i class="fas fa-arrow-right"></i> View Staff Performance
                    </a>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No staff assignments yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
            </div>
        </div>
    </div>
</body>
</html>
