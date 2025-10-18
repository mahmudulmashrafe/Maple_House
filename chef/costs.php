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

// Get monthly cost data
$monthly_costs_query = "SELECT 
    MONTH(meal_date) as month,
    YEAR(meal_date) as year,
    SUM(cost_per_serving * (SELECT COUNT(*) FROM residents WHERE DATE(created_at) <= meal_date)) as total_cost,
    COUNT(*) as total_meals
    FROM daily_meals 
    WHERE YEAR(meal_date) = YEAR(CURDATE())
    GROUP BY YEAR(meal_date), MONTH(meal_date)
    ORDER BY year DESC, month DESC";

try {
    $monthly_costs_stmt = $db->prepare($monthly_costs_query);
    $monthly_costs_stmt->execute();
    $monthly_costs = $monthly_costs_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $monthly_costs = [];
}

// Get current month cost
$current_month_query = "SELECT 
    COALESCE(SUM(cost_per_serving * (SELECT COUNT(*) FROM residents WHERE DATE(created_at) <= meal_date)), 0) as current_cost
    FROM daily_meals 
    WHERE MONTH(meal_date) = MONTH(CURDATE()) 
    AND YEAR(meal_date) = YEAR(CURDATE())";

try {
    $current_month_stmt = $db->prepare($current_month_query);
    $current_month_stmt->execute();
    $current_month_cost = $current_month_stmt->fetchColumn();
} catch (Exception $e) {
    $current_month_cost = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cost Tracking - Chef Dashboard</title>
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            font-size: 2.5rem;
            color: #e67e22;
            margin-bottom: 15px;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 1.8rem;
            color: #2c3e50;
        }
        
        .stat-card p {
            margin: 0;
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .costs-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            overflow: hidden;
        }
        
        .table-header {
            background: #e67e22;
            color: white;
            padding: 20px;
        }
        
        .table-header h2 {
            margin: 0;
            font-size: 1.4rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .amount {
            font-weight: 600;
            color: #e67e22;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-calculator"></i> Cost Tracking</h1>
        <p>Monitor monthly food expenses and budget management</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-money-bill-wave"></i>
            <h3>৳<?php echo number_format($current_month_cost); ?></h3>
            <p>Current Month Cost</p>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-chart-line"></i>
            <h3><?php echo count($monthly_costs); ?></h3>
            <p>Months Tracked</p>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-utensils"></i>
            <h3><?php 
                $avg_cost = count($monthly_costs) > 0 ? array_sum(array_column($monthly_costs, 'total_cost')) / count($monthly_costs) : 0;
                echo '৳' . number_format($avg_cost); 
            ?></h3>
            <p>Average Monthly Cost</p>
        </div>
    </div>

    <div class="costs-table">
        <div class="table-header">
            <h2><i class="fas fa-table"></i> Monthly Cost Breakdown</h2>
        </div>
        
        <?php if (count($monthly_costs) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Year</th>
                    <th>Total Meals</th>
                    <th>Total Cost</th>
                    <th>Average per Meal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthly_costs as $cost): ?>
                <tr>
                    <td><?php echo date('F', mktime(0, 0, 0, $cost['month'], 1)); ?></td>
                    <td><?php echo $cost['year']; ?></td>
                    <td><?php echo $cost['total_meals']; ?></td>
                    <td class="amount">৳<?php echo number_format($cost['total_cost']); ?></td>
                    <td>৳<?php echo $cost['total_meals'] > 0 ? number_format($cost['total_cost'] / $cost['total_meals'], 2) : '0.00'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-data">
            <i class="fas fa-chart-bar" style="font-size: 3rem; color: #bdc3c7; margin-bottom: 15px;"></i>
            <h3>No Cost Data Available</h3>
            <p>Start tracking meals to see cost analytics here.</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
