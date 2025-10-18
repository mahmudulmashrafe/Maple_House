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
$staff_query = "SELECT s.*, u.first_name, u.last_name
                FROM staff s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.user_id = :user_id";
$staff_stmt = $db->prepare($staff_query);
$staff_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_stmt->execute();
$staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);

// Get salary history
$salary_history_query = "SELECT * FROM staff_salaries 
                         WHERE user_id = :user_id 
                         ORDER BY salary_month DESC";
$salary_history_stmt = $db->prepare($salary_history_query);
$salary_history_stmt->bindParam(':user_id', $_SESSION['user_id']);
$salary_history_stmt->execute();
$salary_history = $salary_history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total earnings
$total_earnings = 0;
foreach ($salary_history as $record) {
    $total_earnings += $record['total_salary'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Salary - Maple House</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            padding: 20px;
        }
        
        .salary-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            position: sticky;
            top: 0;
            background: #f8f9fa;
            padding: 20px 0;
            z-index: 10;
        }
        
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 600px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #28a745;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-value.currency {
            color: #28a745;
        }
        
        .section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .section-header {
            background: #f8f9fa;
            padding: 20px 25px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .section-header h2 {
            color: #2c3e50;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-content {
            padding: 0;
            max-height: calc(100vh - 400px);
            overflow-y: auto;
            position: relative;
        }
        
        .section-content::-webkit-scrollbar {
            width: 8px;
        }
        
        .section-content::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .section-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        .section-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .salary-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .salary-table thead {
            background: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .salary-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e9ecef;
            font-size: 0.9rem;
            background: #f8f9fa;
        }
        
        .salary-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #e9ecef;
            color: #495057;
        }
        
        .salary-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-partial {
            background: #cce5ff;
            color: #004085;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #6c757d;
            margin: 20px;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #495057;
        }
        
        .amount {
            font-weight: 600;
            color: #28a745;
        }
        
        .deduction {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="salary-container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Monthly Salary</div>
                <div class="stat-value currency">৳<?php echo number_format($staff['salary'], 2); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Total Earnings</div>
                <div class="stat-value currency">৳<?php echo number_format($total_earnings, 2); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Total Payments</div>
                <div class="stat-value"><?php echo count($salary_history); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Department</div>
                <div class="stat-value" style="font-size: 1.3rem;"><?php echo htmlspecialchars($staff['department']); ?></div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> Salary History</h2>
            </div>
            <div class="section-content">
                <?php if (!empty($salary_history)): ?>
                    <table class="salary-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Base Salary</th>
                                <th>Overtime</th>
                                <th>Bonus</th>
                                <th>Deductions</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Payment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salary_history as $record): ?>
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
                                    <td class="deduction">
                                        <?php echo $record['deductions'] > 0 ? '-৳' . number_format($record['deductions'], 2) : '--'; ?>
                                    </td>
                                    <td>
                                        <strong class="amount" style="font-size: 1.1rem;">৳<?php echo number_format($record['total_salary'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($record['payment_status']); ?>">
                                            <?php echo ucfirst($record['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($record['payment_date']): ?>
                                            <?php echo date('M j, Y', strtotime($record['payment_date'])); ?>
                                            <br><small style="color: #666;"><?php echo ucfirst($record['payment_method']); ?></small>
                                        <?php else: ?>
                                            <span style="color: #999;">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <h3>No Salary History</h3>
                        <p>Your salary payment history will appear here once payments are processed.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
