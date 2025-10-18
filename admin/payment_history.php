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

$resident_id = isset($_GET['resident_id']) ? intval($_GET['resident_id']) : 0;

if (!$resident_id) {
    header('Location: residents.php');
    exit();
}

// Get resident details
$resident_query = "SELECT r.*, u.first_name, u.last_name, u.email, pp.plan_name, pp.monthly_fee
                   FROM residents r
                   JOIN users u ON r.user_id = u.id
                   LEFT JOIN payment_plans pp ON r.plan_id = pp.id
                   WHERE r.id = :resident_id";
$resident_stmt = $db->prepare($resident_query);
$resident_stmt->bindParam(':resident_id', $resident_id);
$resident_stmt->execute();
$resident = $resident_stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    header('Location: residents.php');
    exit();
}

// Get payment history
$history_query = "SELECT rh.*, pp.plan_name, pp.monthly_fee as plan_fee,
                         u.first_name as processed_by_name, u.last_name as processed_by_lastname
                  FROM resident_revenue_history rh
                  LEFT JOIN payment_plans pp ON rh.plan_id = pp.id
                  LEFT JOIN users u ON rh.processed_by = u.id
                  WHERE rh.resident_id = :resident_id
                  ORDER BY rh.payment_date DESC, rh.created_at DESC";
$history_stmt = $db->prepare($history_query);
$history_stmt->bindParam(':resident_id', $resident_id);
$history_stmt->execute();
$payment_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_paid = 0;
$total_transactions = 0;
$current_subscription = null;

foreach ($payment_history as $payment) {
    if ($payment['payment_status'] === 'paid') {
        $total_paid += $payment['amount'];
        $total_transactions++;
    }
    if ($payment['is_active'] == 1) {
        $current_subscription = $payment;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - <?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            color: #2c5aa0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .resident-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .info-card i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #3498db;
        }
        
        .info-card.success i { color: #27ae60; }
        .info-card.warning i { color: #f39c12; }
        .info-card.info i { color: #3498db; }
        
        .info-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .info-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .payment-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            background: #3498db;
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
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
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .payment-type {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            background: #e9ecef;
            color: #495057;
        }
        
        .payment-type.initial {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .payment-type.renewal {
            background: #d4edda;
            color: #155724;
        }
        
        .payment-type.upgrade {
            background: #fff3cd;
            color: #856404;
        }
        
        .payment-type.downgrade {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #495057;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="residents.php" class="btn btn-secondary" style="margin-bottom: 15px;">
            <i class="fas fa-arrow-left"></i> Back to Residents
        </a>
        <h1>
            <i class="fas fa-history"></i> 
            Payment History - <?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?>
        </h1>
        <p>Complete payment and subscription history</p>
    </div>

    <!-- Statistics Cards -->
    <div class="resident-info">
        <div class="info-card success">
            <i class="fas fa-dollar-sign"></i>
            <div class="info-number">৳<?php echo number_format($total_paid); ?></div>
            <div class="info-label">Total Paid</div>
        </div>
        
        <div class="info-card info">
            <i class="fas fa-receipt"></i>
            <div class="info-number"><?php echo $total_transactions; ?></div>
            <div class="info-label">Total Transactions</div>
        </div>
        
        <div class="info-card warning">
            <i class="fas fa-star"></i>
            <div class="info-number"><?php echo htmlspecialchars($resident['plan_name']); ?></div>
            <div class="info-label">Current Plan</div>
        </div>
        
        <?php if ($current_subscription): ?>
        <div class="info-card info">
            <i class="fas fa-calendar-check"></i>
            <div class="info-number"><?php echo date('M j, Y', strtotime($current_subscription['subscription_end_date'])); ?></div>
            <div class="info-label">Subscription Ends</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Payment History Table -->
    <div class="payment-table">
        <div class="table-header">
            <i class="fas fa-list"></i>
            <h3>Payment History</h3>
        </div>
        
        <?php if (!empty($payment_history)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transaction ID</th>
                        <th>Type</th>
                        <th>Plan</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Method</th>
                        <th>Processed By</th>
                        <th>Subscription Period</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payment_history as $payment): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                            <td><code><?php echo htmlspecialchars($payment['transaction_id']); ?></code></td>
                            <td>
                                <span class="payment-type <?php echo $payment['payment_type']; ?>">
                                    <?php echo ucfirst($payment['payment_type']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($payment['plan_name']); ?></td>
                            <td>৳<?php echo number_format($payment['amount']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $payment['payment_status']; ?>">
                                    <?php echo ucfirst($payment['payment_status']); ?>
                                </span>
                            </td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                            <td>
                                <?php if ($payment['processed_by_name']): ?>
                                    <?php echo htmlspecialchars($payment['processed_by_name'] . ' ' . $payment['processed_by_lastname']); ?>
                                <?php else: ?>
                                    System
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($payment['subscription_start_date'] && $payment['subscription_end_date']): ?>
                                    <?php echo date('M j', strtotime($payment['subscription_start_date'])); ?> - 
                                    <?php echo date('M j, Y', strtotime($payment['subscription_end_date'])); ?>
                                    <?php if ($payment['is_active']): ?>
                                        <span class="status-badge status-paid">Active</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <h3>No Payment History</h3>
                <p>No payment transactions found for this resident.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
