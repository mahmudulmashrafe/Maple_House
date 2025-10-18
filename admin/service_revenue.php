<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$filter_month = $_GET['month'] ?? date('Y-m');
$filter_service = $_GET['service'] ?? 'all';

// Build query based on filters
$where_clauses = [];
$params = [];

if ($filter_month !== 'all') {
    $where_clauses[] = "DATE_FORMAT(sp.purchase_date, '%Y-%m') = :month";
    $params[':month'] = $filter_month;
}

if ($filter_service !== 'all') {
    $where_clauses[] = "sp.service_name = :service";
    $params[':service'] = $filter_service;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get all purchases
$purchases_query = "SELECT sp.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as resident_name,
                    r.room_number
                    FROM service_purchases sp
                    JOIN residents r ON sp.resident_id = r.id
                    JOIN users u ON r.user_id = u.id
                    $where_sql
                    ORDER BY sp.purchase_date DESC";
$purchases_stmt = $db->prepare($purchases_query);
foreach ($params as $key => $value) {
    $purchases_stmt->bindValue($key, $value);
}
$purchases_stmt->execute();
$purchases = $purchases_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_revenue = 0;
$total_transactions = count($purchases);
$service_breakdown = [];
$payment_method_breakdown = [];

foreach ($purchases as $purchase) {
    $total_revenue += $purchase['total_price'];
    
    // Service breakdown
    if (!isset($service_breakdown[$purchase['service_name']])) {
        $service_breakdown[$purchase['service_name']] = [
            'count' => 0,
            'revenue' => 0
        ];
    }
    $service_breakdown[$purchase['service_name']]['count']++;
    $service_breakdown[$purchase['service_name']]['revenue'] += $purchase['total_price'];
    
    // Payment method breakdown
    if (!isset($payment_method_breakdown[$purchase['payment_method']])) {
        $payment_method_breakdown[$purchase['payment_method']] = [
            'count' => 0,
            'revenue' => 0
        ];
    }
    $payment_method_breakdown[$purchase['payment_method']]['count']++;
    $payment_method_breakdown[$purchase['payment_method']]['revenue'] += $purchase['total_price'];
}

// Get unique services for filter
$services_query = "SELECT DISTINCT service_name FROM service_purchases ORDER BY service_name";
$services_stmt = $db->prepare($services_query);
$services_stmt->execute();
$available_services = $services_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Revenue - Maple House Admin</title>
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
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 15px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .header h1 {
            margin: 0 0 10px 0;
            font-size: 2rem;
        }

        .header p {
            margin: 0;
            opacity: 0.9;
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 0.85rem;
            color: #666;
            font-weight: 600;
        }

        .filter-group select {
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95rem;
            min-width: 200px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .table-header h2 {
            margin: 0;
            color: #2c3e50;
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

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .breakdown-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .breakdown-card h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .breakdown-item:last-child {
            margin-bottom: 0;
        }

        .breakdown-label {
            font-weight: 600;
            color: #2c3e50;
        }

        .breakdown-value {
            color: #28a745;
            font-weight: 700;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 0.85rem;
            }
            
            th, td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Service Revenue</h1>
            <p>Track and analyze service purchase revenue</p>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">৳<?php echo number_format($total_revenue, 2); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-label">Total Transactions</div>
                <div class="stat-value"><?php echo $total_transactions; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-label">Average Transaction</div>
                <div class="stat-value">৳<?php echo $total_transactions > 0 ? number_format($total_revenue / $total_transactions, 2) : '0.00'; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; flex: 1;">
                <div class="filter-group">
                    <label>Month</label>
                    <select name="month">
                        <option value="all" <?php echo $filter_month === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <?php
                        for ($i = 0; $i < 12; $i++) {
                            $month = date('Y-m', strtotime("-$i months"));
                            $selected = $filter_month === $month ? 'selected' : '';
                            echo "<option value='$month' $selected>" . date('F Y', strtotime($month)) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Service</label>
                    <select name="service">
                        <option value="all" <?php echo $filter_service === 'all' ? 'selected' : ''; ?>>All Services</option>
                        <?php foreach ($available_services as $service): ?>
                            <option value="<?php echo htmlspecialchars($service); ?>" <?php echo $filter_service === $service ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($service); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </form>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Transactions Table -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Purchase History</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Resident</th>
                            <th>Room</th>
                            <th>Service</th>
                            <th>Qty</th>
                            <th>Payment</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($purchases)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 10px; display: block;"></i>
                                    No transactions found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($purchases as $purchase): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($purchase['purchase_date'])); ?></td>
                                <td><?php echo htmlspecialchars($purchase['resident_name']); ?></td>
                                <td>Room <?php echo htmlspecialchars($purchase['room_number']); ?></td>
                                <td><?php echo htmlspecialchars($purchase['service_name']); ?></td>
                                <td><?php echo $purchase['quantity']; ?></td>
                                <td style="text-transform: uppercase;"><?php echo htmlspecialchars($purchase['payment_method']); ?></td>
                                <td style="font-weight: 700; color: #28a745;">৳<?php echo number_format($purchase['total_price'], 2); ?></td>
                                <td><span class="badge badge-success"><?php echo ucfirst($purchase['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Breakdown Cards -->
            <div>
                <!-- Service Breakdown -->
                <div class="breakdown-card">
                    <h3><i class="fas fa-concierge-bell"></i> Service Breakdown</h3>
                    <?php if (empty($service_breakdown)): ?>
                        <p style="text-align: center; color: #999;">No data available</p>
                    <?php else: ?>
                        <?php foreach ($service_breakdown as $service => $data): ?>
                        <div class="breakdown-item">
                            <div>
                                <div class="breakdown-label"><?php echo htmlspecialchars($service); ?></div>
                                <div style="font-size: 0.85rem; color: #666;"><?php echo $data['count']; ?> transactions</div>
                            </div>
                            <div class="breakdown-value">৳<?php echo number_format($data['revenue'], 2); ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Payment Method Breakdown -->
                <div class="breakdown-card">
                    <h3><i class="fas fa-credit-card"></i> Payment Methods</h3>
                    <?php if (empty($payment_method_breakdown)): ?>
                        <p style="text-align: center; color: #999;">No data available</p>
                    <?php else: ?>
                        <?php foreach ($payment_method_breakdown as $method => $data): ?>
                        <div class="breakdown-item">
                            <div>
                                <div class="breakdown-label" style="text-transform: uppercase;"><?php echo htmlspecialchars($method); ?></div>
                                <div style="font-size: 0.85rem; color: #666;"><?php echo $data['count']; ?> transactions</div>
                            </div>
                            <div class="breakdown-value">৳<?php echo number_format($data['revenue'], 2); ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
