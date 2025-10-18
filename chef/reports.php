<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a chef
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Chef') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get kitchen analytics data
$analytics = [];

// Monthly meal statistics
try {
    $monthly_meals_query = "SELECT 
        MONTH(meal_date) as month,
        YEAR(meal_date) as year,
        COUNT(*) as total_meals,
        AVG(cost_per_serving) as avg_cost_per_serving
        FROM daily_meals 
        WHERE YEAR(meal_date) = YEAR(CURDATE())
        GROUP BY YEAR(meal_date), MONTH(meal_date)
        ORDER BY year DESC, month DESC
        LIMIT 6";
    $monthly_meals_stmt = $db->prepare($monthly_meals_query);
    $monthly_meals_stmt->execute();
    $analytics['monthly_meals'] = $monthly_meals_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $analytics['monthly_meals'] = [];
}

// Popular meal types
try {
    $popular_meals_query = "SELECT 
        meal_type,
        COUNT(*) as frequency
        FROM daily_meals 
        WHERE YEAR(meal_date) = YEAR(CURDATE())
        GROUP BY meal_type
        ORDER BY frequency DESC";
    $popular_meals_stmt = $db->prepare($popular_meals_query);
    $popular_meals_stmt->execute();
    $analytics['popular_meals'] = $popular_meals_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $analytics['popular_meals'] = [];
}

// Recent activity
try {
    $recent_activity_query = "SELECT 
        meal_name,
        meal_type,
        meal_date,
        cost_per_serving
        FROM daily_meals 
        ORDER BY meal_date DESC, id DESC
        LIMIT 10";
    $recent_activity_stmt = $db->prepare($recent_activity_query);
    $recent_activity_stmt->execute();
    $analytics['recent_activity'] = $recent_activity_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $analytics['recent_activity'] = [];
}

// Summary statistics
try {
    $summary_query = "SELECT 
        COUNT(*) as total_meals_this_year,
        AVG(cost_per_serving) as avg_cost_per_serving,
        MIN(cost_per_serving) as min_cost,
        MAX(cost_per_serving) as max_cost
        FROM daily_meals 
        WHERE YEAR(meal_date) = YEAR(CURDATE())";
    $summary_stmt = $db->prepare($summary_query);
    $summary_stmt->execute();
    $analytics['summary'] = $summary_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $analytics['summary'] = [
        'total_meals_this_year' => 0,
        'avg_cost_per_serving' => 0,
        'min_cost' => 0,
        'max_cost' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Reports - Chef Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-bottom: 30px;
        }
        
        .header h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.8rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            text-align: center;
        }
        
        .stat-card i {
            font-size: 2rem;
            margin-bottom: 15px;
        }
        
        .stat-card:nth-child(1) i { color: #3498db; }
        .stat-card:nth-child(2) i { color: #27ae60; }
        .stat-card:nth-child(3) i { color: #e74c3c; }
        .stat-card:nth-child(4) i { color: #9b59b6; }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 1.6rem;
            color: #2c3e50;
        }
        
        .stat-card p {
            margin: 0;
            color: #7f8c8d;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }
        
        .report-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            overflow: hidden;
        }
        
        .report-header {
            background: #9b59b6;
            color: white;
            padding: 20px;
        }
        
        .report-header h2 {
            margin: 0;
            font-size: 1.3rem;
        }
        
        .report-content {
            padding: 20px;
        }
        
        .chart-placeholder {
            height: 200px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        
        .data-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .data-list li {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .data-list li:last-child {
            border-bottom: none;
        }
        
        .badge {
            background: #e9ecef;
            color: #495057;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge.breakfast { background: #fff3cd; color: #856404; }
        .badge.lunch { background: #d4edda; color: #155724; }
        .badge.dinner { background: #d1ecf1; color: #0c5460; }
        .badge.snack { background: #f8d7da; color: #721c24; }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .amount {
            font-weight: 600;
            color: #27ae60;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-chart-bar"></i> Kitchen Analytics & Reports</h1>
        <p>Comprehensive insights into kitchen operations and performance</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-utensils"></i>
            <h3><?php echo number_format($analytics['summary']['total_meals_this_year']); ?></h3>
            <p>Total Meals This Year</p>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-money-bill-wave"></i>
            <h3>৳<?php echo number_format($analytics['summary']['avg_cost_per_serving'], 2); ?></h3>
            <p>Average Cost per Serving</p>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-arrow-down"></i>
            <h3>৳<?php echo number_format($analytics['summary']['min_cost'], 2); ?></h3>
            <p>Lowest Cost Meal</p>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-arrow-up"></i>
            <h3>৳<?php echo number_format($analytics['summary']['max_cost'], 2); ?></h3>
            <p>Highest Cost Meal</p>
        </div>
    </div>

    <div class="reports-grid">
        <!-- Monthly Meal Trends -->
        <div class="report-card">
            <div class="report-header">
                <h2><i class="fas fa-chart-line"></i> Monthly Meal Trends</h2>
            </div>
            <div class="report-content">
                <?php if (count($analytics['monthly_meals']) > 0): ?>
                <ul class="data-list">
                    <?php foreach ($analytics['monthly_meals'] as $month): ?>
                    <li>
                        <span><?php echo date('F Y', mktime(0, 0, 0, $month['month'], 1, $month['year'])); ?></span>
                        <span>
                            <strong><?php echo $month['total_meals']; ?> meals</strong>
                            <small class="amount">(৳<?php echo number_format($month['avg_cost_per_serving'], 2); ?> avg)</small>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-chart-line" style="font-size: 2rem; margin-bottom: 10px;"></i>
                    <p>No monthly data available</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Popular Meal Types -->
        <div class="report-card">
            <div class="report-header">
                <h2><i class="fas fa-star"></i> Popular Meal Types</h2>
            </div>
            <div class="report-content">
                <?php if (count($analytics['popular_meals']) > 0): ?>
                <ul class="data-list">
                    <?php foreach ($analytics['popular_meals'] as $meal): ?>
                    <li>
                        <span>
                            <span class="badge <?php echo strtolower($meal['meal_type']); ?>">
                                <?php echo ucfirst($meal['meal_type']); ?>
                            </span>
                        </span>
                        <span><strong><?php echo $meal['frequency']; ?> times</strong></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-star" style="font-size: 2rem; margin-bottom: 10px;"></i>
                    <p>No meal type data available</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Kitchen Activity -->
        <div class="report-card" style="grid-column: 1 / -1;">
            <div class="report-header">
                <h2><i class="fas fa-clock"></i> Recent Kitchen Activity</h2>
            </div>
            <div class="report-content">
                <?php if (count($analytics['recent_activity']) > 0): ?>
                <ul class="data-list">
                    <?php foreach ($analytics['recent_activity'] as $activity): ?>
                    <li>
                        <span>
                            <strong><?php echo htmlspecialchars($activity['meal_name']); ?></strong>
                            <span class="badge <?php echo strtolower($activity['meal_type']); ?>">
                                <?php echo ucfirst($activity['meal_type']); ?>
                            </span>
                            <small style="color: #7f8c8d; margin-left: 10px;">
                                <?php echo date('M j, Y', strtotime($activity['meal_date'])); ?>
                            </small>
                        </span>
                        <span class="amount">৳<?php echo number_format($activity['cost_per_serving'], 2); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-clock" style="font-size: 2rem; margin-bottom: 10px;"></i>
                    <p>No recent activity data available</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
