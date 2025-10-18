<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Manager'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$filter_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'monthly';

try {
    if ($filter_type === 'all_time') {
        $revenue_query = "SELECT rrh.*, u.first_name, u.last_name, pp.plan_name, pp.monthly_fee
                         FROM resident_revenue_history rrh
                         JOIN users u ON rrh.user_id = u.id
                         LEFT JOIN payment_plans pp ON rrh.plan_id = pp.id
                         WHERE rrh.payment_type IN ('initial', 'renewal', 'upgrade')
                         ORDER BY rrh.payment_date DESC LIMIT 50";
        $revenue_stmt = $db->prepare($revenue_query);
        $revenue_stmt->execute();
    } else {
        $revenue_query = "SELECT rrh.*, u.first_name, u.last_name, pp.plan_name, pp.monthly_fee
                         FROM resident_revenue_history rrh
                         JOIN users u ON rrh.user_id = u.id
                         LEFT JOIN payment_plans pp ON rrh.plan_id = pp.id
                         WHERE rrh.payment_type IN ('initial', 'renewal', 'upgrade')
                         AND DATE_FORMAT(rrh.payment_date, '%Y-%m') = ?
                         ORDER BY rrh.payment_date DESC LIMIT 50";
        $revenue_stmt = $db->prepare($revenue_query);
        $revenue_stmt->execute([$filter_month]);
    }
    $revenue_history = $revenue_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $revenue_history = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fees Management - Maple House</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; border-radius: 20px; padding: 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #f1f3f4; }
        .page-title { font-size: 2.5rem; font-weight: 700; color: #2c3e50; margin: 0; }
        .back-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 24px; border: none; border-radius: 10px; text-decoration: none; font-weight: 600; }
        .filter-section { background: white; border-radius: 15px; padding: 20px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .filter-container { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; min-width: 150px; }
        .revenue-table { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .table-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; font-size: 1.2rem; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #f1f3f4; }
        th { background: #f8f9fa; font-weight: 600; color: #2c3e50; }
        tr:hover { background: #f8f9fa; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; background: #d4edda; color: #155724; }
        .payment-type { padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; }
        .type-initial { background: #e3f2fd; color: #1565c0; }
        .type-renewal { background: #e8f5e8; color: #2e7d32; }
        .type-upgrade { background: #fff3e0; color: #ef6c00; }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-money-bill-wave"></i> Fees Management</h1>
            <a href="finances_overview.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <div class="filter-section">
            <div class="filter-container">
                <div class="filter-group">
                    <label>Filter Type:</label>
                    <select id="filterType" onchange="applyFilters()">
                        <option value="monthly" <?php echo ($filter_type == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                        <option value="all_time" <?php echo ($filter_type == 'all_time') ? 'selected' : ''; ?>>All Time</option>
                    </select>
                </div>
                <div class="filter-group" id="monthGroup" style="<?php echo ($filter_type == 'all_time') ? 'display:none;' : ''; ?>">
                    <label>Month:</label>
                    <select id="monthFilter" onchange="applyFilters()">
                        <?php
                        for ($i = 1; $i <= 12; $i++) {
                            $month_value = date('Y') . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
                            $month_name = date('F', mktime(0, 0, 0, $i, 1));
                            $selected = ($month_value == $filter_month) ? 'selected' : '';
                            echo "<option value='$month_value' $selected>$month_name " . date('Y') . "</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="revenue-table">
            <div class="table-header">
                <i class="fas fa-history"></i> Revenue History - Resident Fees
            </div>
            
            <?php if (!empty($revenue_history)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Resident</th>
                        <th>Plan</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($revenue_history as $record): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($record['transaction_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($record['plan_name']); ?><br><small>৳<?php echo number_format($record['monthly_fee']); ?>/month</small></td>
                        <td><strong>৳<?php echo number_format($record['amount']); ?></strong></td>
                        <td><span class="payment-type type-<?php echo $record['payment_type']; ?>"><?php echo ucfirst($record['payment_type']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($record['payment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($record['payment_method']); ?></td>
                        <td><span class="status-badge">Paid</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 50px; color: #6c757d;">
                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 20px;"></i>
                <p>No fee records found.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function applyFilters() {
            const filterType = document.getElementById('filterType').value;
            const month = document.getElementById('monthFilter').value;
            const monthGroup = document.getElementById('monthGroup');
            
            if (filterType === 'all_time') {
                monthGroup.style.display = 'none';
            } else {
                monthGroup.style.display = 'flex';
            }
            
            const url = new URL(window.location);
            url.searchParams.set('filter_type', filterType);
            if (filterType !== 'all_time') {
                url.searchParams.set('month', month);
            }
            window.location.href = url.toString();
        }
    </script>
</body>
</html>
