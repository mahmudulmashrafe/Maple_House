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

// Get chef information
$chef_query = "SELECT c.*, u.first_name, u.last_name FROM chefs c 
               JOIN users u ON c.user_id = u.id 
               WHERE c.user_id = :user_id";
$chef_stmt = $db->prepare($chef_query);
$chef_stmt->bindParam(':user_id', $_SESSION['user_id']);
$chef_stmt->execute();
$chef = $chef_stmt->fetch(PDO::FETCH_ASSOC);

// Get salary records
$salaries = [];
$total_earnings = 0;

try {
    $salary_query = "SELECT * FROM staff_salaries 
                     WHERE user_id = :user_id 
                     ORDER BY salary_month DESC";
    $salary_stmt = $db->prepare($salary_query);
    $salary_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $salary_stmt->execute();
    $salaries = $salary_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total earnings
    foreach ($salaries as $salary) {
        $total_earnings += $salary['total_salary'];
    }
} catch (PDOException $e) {
    // Table doesn't exist yet, use empty array
    $salaries = [];
    $total_earnings = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary History</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background: #f8f9fa;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
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
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 5px solid #43e97b;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .salary-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px 25px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .section-header h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        .salary-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .salary-table thead {
            background: #f8f9fa;
        }
        
        .salary-table th {
            padding: 15px;
            text-align: center;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
        }
        
        .salary-table td {
            padding: 18px 15px;
            border-bottom: 1px solid #f1f3f5;
            color: #2c3e50;
            text-align: center;
        }
        
        .salary-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .salary-table tbody tr:hover {
            background: linear-gradient(135deg, rgba(67, 233, 123, 0.03) 0%, rgba(56, 249, 215, 0.03) 100%);
        }
        
        .amount {
            font-weight: 600;
            color: #43e97b;
            font-size: 1.1rem;
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        
        .status-paid {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .empty-state p {
            font-size: 1.1rem;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Earnings</div>
                <div class="stat-value">৳<?php echo number_format($total_earnings); ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #667eea;">
                <div class="stat-label">Total Payments</div>
                <div class="stat-value"><?php echo count($salaries); ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #f093fb;">
                <div class="stat-label">Average Salary</div>
                <div class="stat-value">৳<?php echo count($salaries) > 0 ? number_format($total_earnings / count($salaries)) : '0'; ?></div>
            </div>
        </div>
        
        <div class="salary-section">
            <div class="section-header">
                <h2><i class="fas fa-list"></i> Payment Records</h2>
            </div>
            
            <?php if (count($salaries) > 0): ?>
                <div class="table-wrapper">
                    <table class="salary-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Base Salary</th>
                                <th>Overtime</th>
                                <th>Bonus</th>
                                <th>Deductions</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salaries as $record): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('F Y', strtotime($record['salary_month'])); ?></strong>
                                    </td>
                                    <td class="amount">৳<?php echo number_format($record['base_salary'], 2); ?></td>
                                    <td>
                                        <?php if ($record['overtime_hours'] > 0): ?>
                                            <?php echo $record['overtime_hours']; ?> hrs × ৳<?php echo number_format($record['overtime_rate'], 2); ?>
                                            <br><small class="amount">= ৳<?php echo number_format($record['overtime_hours'] * $record['overtime_rate'], 2); ?></small>
                                        <?php else: ?>
                                            <span style="color: #999;">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="amount">
                                        <?php echo $record['bonus'] > 0 ? '৳' . number_format($record['bonus'], 2) : '--'; ?>
                                    </td>
                                    <td style="color: #dc3545;">
                                        <?php echo $record['deductions'] > 0 ? '-৳' . number_format($record['deductions'], 2) : '--'; ?>
                                    </td>
                                    <td>
                                        <strong class="amount" style="font-size: 1.1rem;">৳<?php echo number_format($record['total_salary'], 2); ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <p>No salary records found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
