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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add_expense':
                $stmt = $db->prepare("INSERT INTO expenses (expense_type, category, description, amount, expense_date, payment_method, vendor_name, vendor_contact, receipt_number, notes, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([
                    $_POST['expense_type'], $_POST['category'], $_POST['description'], 
                    $_POST['amount'], $_POST['expense_date'], $_POST['payment_method'],
                    $_POST['vendor_name'], $_POST['vendor_contact'], $_POST['receipt_number'],
                    $_POST['notes'], $_SESSION['user_id']
                ]);
                echo json_encode(['success' => true, 'message' => 'Expense added successfully']);
                break;
                
            case 'approve_expense':
                $stmt = $db->prepare("UPDATE expenses SET status = 'approved', approved_by = ? WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $_POST['expense_id']]);
                echo json_encode(['success' => true, 'message' => 'Expense approved successfully']);
                break;
                
            case 'pay_expense':
                $stmt = $db->prepare("UPDATE expenses SET status = 'paid' WHERE id = ?");
                $stmt->execute([$_POST['expense_id']]);
                echo json_encode(['success' => true, 'message' => 'Expense marked as paid']);
                break;
                
            case 'delete_expense':
                $stmt = $db->prepare("DELETE FROM expenses WHERE id = ?");
                $stmt->execute([$_POST['expense_id']]);
                echo json_encode(['success' => true, 'message' => 'Expense deleted successfully']);
                break;
                
            case 'process_single_salary':
                $salary_month = date('Y-m-01'); // Current month
                
                // Check if salary already exists for this month
                $check_stmt = $db->prepare("SELECT id FROM staff_salaries WHERE user_id = ? AND salary_month = ?");
                $check_stmt->execute([$_POST['user_id'], $salary_month]);
                
                if ($check_stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Salary for this month already processed']);
                    break;
                }
                
                $base_salary = $_POST['base_salary'];
                
                // Insert salary record
                $stmt = $db->prepare("INSERT INTO staff_salaries (user_id, salary_month, base_salary, overtime_hours, overtime_rate, bonus, deductions, total_salary, payment_status, notes, created_by) VALUES (?, ?, ?, 0, 0, 0, 0, ?, 'pending', 'Auto-processed monthly salary', ?)");
                $stmt->execute([$_POST['user_id'], $salary_month, $base_salary, $base_salary, $_SESSION['user_id']]);
                
                // Record expense
                $user_stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                $user_stmt->execute([$_POST['user_id']]);
                $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
                $staff_name = $user_data['first_name'] . ' ' . $user_data['last_name'];
                
                $expense_description = "Monthly salary: " . $staff_name . " (" . date('F Y') . ")";
                
                $expense_stmt = $db->prepare("INSERT INTO expenses (expense_type, category, description, amount, expense_date, reference_id, reference_type, created_by, status) VALUES ('salary', 'Staff Salary', ?, ?, CURDATE(), ?, 'staff_salary', ?, 'approved')");
                $expense_stmt->execute([$expense_description, $base_salary, $_POST['user_id'], $_SESSION['user_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Salary processed successfully']);
                break;
                
            case 'process_monthly_renewals':
                $current_day = date('j'); // Day of month (1-31)
                $salary_month = date('Y-m-01'); // Current month
                
                // Only process if it's day 10 or later, or if forced
                if ($current_day < 10 && !isset($_POST['force'])) {
                    echo json_encode(['success' => false, 'message' => 'Monthly renewals are processed on the 10th of each month']);
                    break;
                }
                
                // Get all active staff who don't have salary for current month
                $staff_query = "SELECT u.id, u.first_name, u.last_name,
                                CASE 
                                    WHEN u.role_id = 3 THEN 45000
                                    WHEN u.role_id = 4 THEN 35000
                                    WHEN u.role_id = 5 THEN 25000
                                    ELSE 20000
                                END as monthly_salary
                                FROM users u 
                                WHERE u.role_id IN (3, 4, 5) AND u.is_active = 1
                                AND u.id NOT IN (SELECT user_id FROM staff_salaries WHERE salary_month = ?)";
                $staff_stmt = $db->prepare($staff_query);
                $staff_stmt->execute([$salary_month]);
                $staff_to_process = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $processed_count = 0;
                foreach ($staff_to_process as $staff) {
                    // Insert salary record
                    $salary_stmt = $db->prepare("INSERT INTO staff_salaries (user_id, salary_month, base_salary, overtime_hours, overtime_rate, bonus, deductions, total_salary, payment_status, notes, created_by) VALUES (?, ?, ?, 0, 0, 0, 0, ?, 'pending', 'Auto-renewal on 10th', ?)");
                    $salary_stmt->execute([$staff['id'], $salary_month, $staff['monthly_salary'], $staff['monthly_salary'], $_SESSION['user_id']]);
                    
                    // Record expense
                    $staff_name = $staff['first_name'] . ' ' . $staff['last_name'];
                    $expense_description = "Monthly salary: " . $staff_name . " (" . date('F Y') . ")";
                    
                    $expense_stmt = $db->prepare("INSERT INTO expenses (expense_type, category, description, amount, expense_date, reference_id, reference_type, created_by, status) VALUES ('salary', 'Staff Salary', ?, ?, CURDATE(), ?, 'staff_salary', ?, 'approved')");
                    $expense_stmt->execute([$expense_description, $staff['monthly_salary'], $staff['id'], $_SESSION['user_id']]);
                    
                    $processed_count++;
                }
                
                echo json_encode(['success' => true, 'message' => "Processed {$processed_count} staff salaries for " . date('F Y')]);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Get filter parameters
$filter_month = $_GET['month'] ?? '';
$filter_type = $_GET['type'] ?? '';

// Get expense statistics
$current_month = date('Y-m');
$stats = [];

try {
    // Monthly expenses by type
    $monthly_query = "SELECT 
        expense_type,
        SUM(amount) as total_amount,
        COUNT(*) as count
        FROM expenses 
        WHERE DATE_FORMAT(expense_date, '%Y-%m') = ? AND status IN ('approved', 'paid')
        GROUP BY expense_type";
    $monthly_stmt = $db->prepare($monthly_query);
    $monthly_stmt->execute([$current_month]);
    $monthly_expenses = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total monthly expense
    $total_monthly = array_sum(array_column($monthly_expenses, 'total_amount'));
    
    // All time expenses
    $total_query = "SELECT SUM(amount) as total FROM expenses WHERE status IN ('approved', 'paid')";
    $total_stmt = $db->prepare($total_query);
    $total_stmt->execute();
    $total_all_time = $total_stmt->fetchColumn() ?: 0;
    
    // Pending expenses
    $pending_query = "SELECT COUNT(*) as count, SUM(amount) as amount FROM expenses WHERE status = 'pending'";
    $pending_stmt = $db->prepare($pending_query);
    $pending_stmt->execute();
    $pending_data = $pending_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $monthly_expenses = [];
    $total_monthly = 0;
    $total_all_time = 0;
    $pending_data = ['count' => 0, 'amount' => 0];
}

// Get current staff salaries information
try {
    // Check if salary columns exist
    $doctor_salary_exists = false;
    $chef_salary_exists = false;
    
    try {
        $doctor_check = $db->query("SHOW COLUMNS FROM doctors LIKE 'monthly_salary'");
        $doctor_salary_exists = $doctor_check->rowCount() > 0;
    } catch (Exception $e) {
        // doctors table might not exist
    }
    
    try {
        $chef_check = $db->query("SHOW COLUMNS FROM chefs LIKE 'monthly_salary'");
        $chef_salary_exists = $chef_check->rowCount() > 0;
    } catch (Exception $e) {
        // chefs table might not exist
    }
    
    if ($doctor_salary_exists && $chef_salary_exists) {
        $current_staff_query = "SELECT u.id, u.first_name, u.last_name, u.email, r.name as role_name,
                                CASE 
                                    WHEN r.id = 3 THEN 'Doctor'
                                    WHEN r.id = 4 THEN 'Chef' 
                                    WHEN r.id = 5 THEN 'Staff'
                                    ELSE 'Other'
                                END as position,
                                CASE 
                                    WHEN r.id = 3 THEN COALESCE(d.monthly_salary, 45000)
                                    WHEN r.id = 4 THEN COALESCE(c.monthly_salary, 35000)
                                    WHEN r.id = 5 THEN 25000
                                    ELSE 20000
                                END as monthly_salary,
                                u.is_active,
                                (SELECT MAX(salary_month) FROM staff_salaries WHERE user_id = u.id) as last_salary_month,
                                (SELECT payment_status FROM staff_salaries WHERE user_id = u.id AND salary_month = (SELECT MAX(salary_month) FROM staff_salaries WHERE user_id = u.id)) as last_payment_status
                                FROM users u 
                                JOIN roles r ON u.role_id = r.id 
                                LEFT JOIN doctors d ON u.id = d.user_id AND r.id = 3
                                LEFT JOIN chefs c ON u.id = c.user_id AND r.id = 4
                                WHERE u.role_id IN (3, 4, 5)
                                ORDER BY r.id, u.first_name";
    } else {
        // Fallback query without salary columns
        $current_staff_query = "SELECT u.id, u.first_name, u.last_name, u.email, r.name as role_name,
                                CASE 
                                    WHEN r.id = 3 THEN 'Doctor'
                                    WHEN r.id = 4 THEN 'Chef' 
                                    WHEN r.id = 5 THEN 'Staff'
                                    ELSE 'Other'
                                END as position,
                                CASE 
                                    WHEN r.id = 3 THEN 45000
                                    WHEN r.id = 4 THEN 35000
                                    WHEN r.id = 5 THEN 25000
                                    ELSE 20000
                                END as monthly_salary,
                                u.is_active,
                                (SELECT MAX(salary_month) FROM staff_salaries WHERE user_id = u.id) as last_salary_month,
                                (SELECT payment_status FROM staff_salaries WHERE user_id = u.id AND salary_month = (SELECT MAX(salary_month) FROM staff_salaries WHERE user_id = u.id)) as last_payment_status
                                FROM users u 
                                JOIN roles r ON u.role_id = r.id 
                                WHERE u.role_id IN (3, 4, 5)
                                ORDER BY r.id, u.first_name";
    }
    $staff_stmt = $db->prepare($current_staff_query);
    $staff_stmt->execute();
    $current_staff = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total monthly salary cost
    $total_monthly_salaries = 0;
    $active_staff_count = 0;
    foreach ($current_staff as $staff) {
        if ($staff['is_active']) {
            $total_monthly_salaries += $staff['monthly_salary'];
            $active_staff_count++;
        }
    }
} catch (Exception $e) {
    $current_staff = [];
    $total_monthly_salaries = 0;
    $active_staff_count = 0;
}

// Get expenses with filters
$where_conditions = ["1=1"];
$params = [];

if ($filter_month) {
    $where_conditions[] = "DATE_FORMAT(expense_date, '%Y-%m') = ?";
    $params[] = $filter_month;
}

if ($filter_type) {
    $where_conditions[] = "expense_type = ?";
    $params[] = $filter_type;
}

$expenses_query = "SELECT e.*, u.first_name, u.last_name, 
                   a.first_name as approved_by_name, a.last_name as approved_by_lastname
                   FROM expenses e
                   LEFT JOIN users u ON e.created_by = u.id
                   LEFT JOIN users a ON e.approved_by = a.id
                   WHERE " . implode(' AND ', $where_conditions) . "
                   ORDER BY e.expense_date DESC, e.created_at DESC";

$expenses_stmt = $db->prepare($expenses_query);
$expenses_stmt->execute($params);
$expenses = $expenses_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Management - Maple House</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary { 
            background: #3498db; 
            color: white; 
            border: none;
            transition: background-color 0.3s ease;
        }
        .btn-primary:hover { 
            background: #2980b9; 
            color: white; 
        }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn:hover { transform: translateY(-1px); opacity: 0.9; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
            flex-shrink: 0;
        }
        .stat-card {
            background: white;
            padding: 12px 15px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            border: 2px solid #e2e8f0;
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        /* Individual card border colors */
        .stat-card:nth-child(1) {
            border-color: #3498db;
        }
        
        .stat-card:nth-child(2) {
            border-color: #9b59b6;
        }
        
        .stat-card:nth-child(3) {
            border-color: #f39c12;
        }
        
        .stat-card:nth-child(4) {
            border-color: #e74c3c;
        }
        .stat-number {
            font-size: 1.6rem;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .stat-label {
            color: #666;
            font-size: 0.85rem;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            flex-shrink: 0;
            justify-content: space-between;
        }
        
        .filters-left {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filters-right {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-left: auto;
        }
        .form-control {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .expenses-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .table-header {
            background: #3498db;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        .table-content {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            max-height: 400px;
            border-top: 1px solid #e9ecef;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
            word-wrap: break-word;
            overflow: hidden;
        }
        th:nth-child(1), td:nth-child(1) { width: 100px; } /* Date */
        th:nth-child(2), td:nth-child(2) { width: 80px; } /* Type */
        th:nth-child(3), td:nth-child(3) { width: 100px; } /* Category */
        th:nth-child(4), td:nth-child(4) { width: 200px; } /* Description */
        th:nth-child(5), td:nth-child(5) { width: 100px; } /* Amount */
        th:nth-child(6), td:nth-child(6) { width: 120px; } /* Vendor */
        th:nth-child(7), td:nth-child(7) { width: 80px; } /* Status */
        th:nth-child(8), td:nth-child(8) { width: 120px; } /* Actions */
        th {
            background: #f8f9fa;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 2px solid #dee2e6;
        }
        
        /* Custom scrollbar styling */
        .table-content::-webkit-scrollbar {
            width: 8px;
        }
        .table-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .table-content::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        .table-content::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-pending { background: #ffeaa7; color: #e17055; }
        .status-approved { background: #d4f6ed; color: #27ae60; }
        .status-paid { background: #d1ecf1; color: #0c5460; }
        .status-rejected { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">৳<?php echo number_format($total_monthly); ?></div>
                <div class="stat-label">This Month</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">৳<?php echo number_format($total_all_time); ?></div>
                <div class="stat-label">All Time</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pending_data['count']; ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
        </div>

        <div class="filters">
            <div class="filters-left">
                <label><strong>Filters:</strong></label>
                <select id="monthFilter" class="form-control" onchange="applyFilters()">
                    <option value="" <?php echo ($filter_month === '') ? 'selected' : ''; ?>>All Months</option>
                    <?php for ($i = 0; $i < 12; $i++): 
                        $month = date('Y-m', strtotime("-$i months"));
                        $selected = ($month === $filter_month) ? 'selected' : '';
                    ?>
                    <option value="<?php echo $month; ?>" <?php echo $selected; ?>><?php echo date('F Y', strtotime($month)); ?></option>
                    <?php endfor; ?>
                </select>
                <select id="typeFilter" class="form-control" onchange="applyFilters()">
                    <option value="">All Types</option>
                    <option value="inventory" <?php echo ($filter_type === 'inventory') ? 'selected' : ''; ?>>Inventory</option>
                    <option value="salary" <?php echo ($filter_type === 'salary') ? 'selected' : ''; ?>>Salary</option>
                    <option value="utility" <?php echo ($filter_type === 'utility') ? 'selected' : ''; ?>>Utility</option>
                    <option value="infrastructure" <?php echo ($filter_type === 'infrastructure') ? 'selected' : ''; ?>>Infrastructure</option>
                    <option value="maintenance" <?php echo ($filter_type === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                    <option value="other" <?php echo ($filter_type === 'other') ? 'selected' : ''; ?>>Other</option>
                </select>
                <button class="btn btn-success" onclick="showRenewModal()">
                    <i class="fas fa-sync"></i> Renew
                </button>
            </div>
            <div class="filters-right">
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Add Expense
                </button>
            </div>
        </div>

        <div class="expenses-table">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Expense Records</h3>
            </div>
            <div class="table-content">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Vendor</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($expense['expense_date'])); ?></td>
                            <td><?php echo ucfirst($expense['expense_type']); ?></td>
                            <td><?php echo htmlspecialchars($expense['category']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($expense['description']); ?></strong>
                                <?php if ($expense['notes']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($expense['notes']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><strong>৳<?php echo number_format($expense['amount'], 2); ?></strong></td>
                            <td><?php echo htmlspecialchars($expense['vendor_name'] ?: 'N/A'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $expense['status']; ?>">
                                    <?php echo ucfirst($expense['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($expense['status'] === 'pending'): ?>
                                    <button class="btn btn-success btn-sm" onclick="approveExpense(<?php echo $expense['id']; ?>)" title="Approve">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php elseif ($expense['status'] === 'approved'): ?>
                                    <button class="btn btn-primary btn-sm" onclick="payExpense(<?php echo $expense['id']; ?>)" title="Mark as Paid">
                                        <i class="fas fa-money-bill"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-danger btn-sm" onclick="deleteExpense(<?php echo $expense['id']; ?>)" title="Delete" style="margin-left: 5px;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div id="addExpenseModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Add New Expense</h3>
                <span class="close" onclick="closeModal('addExpenseModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="addExpenseForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Expense Type *</label>
                            <select name="expense_type" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="inventory">Inventory</option>
                                <option value="salary">Salary</option>
                                <option value="utility">Utility</option>
                                <option value="infrastructure">Infrastructure</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Category *</label>
                            <input type="text" name="category" class="form-control" required placeholder="e.g., Office Supplies">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" class="form-control" rows="3" required placeholder="Detailed description of the expense"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Amount (৳) *</label>
                            <input type="number" name="amount" class="form-control" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Expense Date *</label>
                            <input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Payment Method</label>
                            <select name="payment_method" class="form-control">
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                                <option value="card">Card</option>
                                <option value="online">Online</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Receipt Number</label>
                            <input type="text" name="receipt_number" class="form-control" placeholder="Receipt/Invoice number">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Vendor Name</label>
                            <input type="text" name="vendor_name" class="form-control" placeholder="Supplier/Vendor name">
                        </div>
                        <div class="form-group">
                            <label>Vendor Contact</label>
                            <input type="text" name="vendor_contact" class="form-control" placeholder="Phone/Email">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes or comments"></textarea>
                    </div>
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addExpenseModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Expense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Renewal Confirmation Modal -->
    <div id="renewModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-sync"></i> Process Monthly Renewals</h3>
                <span class="close" onclick="closeModal('renewModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 3rem; color: #f39c12; margin-bottom: 15px;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4 style="color: #2c3e50; margin-bottom: 15px;">Confirm Monthly Renewal Process</h4>
                    <p style="color: #666; margin-bottom: 20px; line-height: 1.5;">
                        This will process monthly salary renewals for all active staff members. 
                        New expense records will be created for the current month.
                    </p>
                    <p style="color: #e74c3c; font-weight: 600; margin-bottom: 25px;">
                        Are you sure you want to continue?
                    </p>
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('renewModal')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-success" onclick="confirmRenewal()">
                            <i class="fas fa-check"></i> Yes, Process Renewals
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.3);
            backdrop-filter: blur(2px);
        }
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        .modal-header {
            background: white;
            color: #2c3e50;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 12px 12px 0 0;
            border-bottom: 1px solid #e9ecef;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .modal-body {
            padding: 20px;
            flex: 1;
            overflow-y: auto;
            max-height: calc(90vh - 140px);
        }
        .close {
            color: #999;
            font-size: 24px;
            cursor: pointer;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        /* Popup Notification */
        .popup-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #27ae60;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        .popup-notification.show {
            transform: translateX(0);
        }
        .popup-notification.error {
            background: #e74c3c;
        }
        
        body {
            overflow: hidden;
        }
    </style>

    <script>
        function showPopup(message, isError = false) {
            // Remove existing popup
            const existing = document.querySelector('.popup-notification');
            if (existing) existing.remove();
            
            // Create new popup
            const popup = document.createElement('div');
            popup.className = 'popup-notification' + (isError ? ' error' : '');
            popup.textContent = message;
            document.body.appendChild(popup);
            
            // Show popup
            setTimeout(() => popup.classList.add('show'), 100);
            
            // Hide popup after 3 seconds
            setTimeout(() => {
                popup.classList.remove('show');
                setTimeout(() => popup.remove(), 300);
            }, 3000);
        }
        
        function applyFilters() {
            const month = document.getElementById('monthFilter').value;
            const type = document.getElementById('typeFilter').value;
            
            const params = new URLSearchParams();
            params.append('page', 'expenses');
            if (month) params.append('month', month);
            if (type) params.append('type', type);
            
            // Check if we're in dashboard context
            if (window.location.href.includes('dashboard.php')) {
                window.location.href = 'dashboard.php?' + params.toString();
            } else {
                window.location.href = 'expenses.php?' + params.toString();
            }
        }

        function approveExpense(id) {
            if (confirm('Approve this expense?')) {
                fetch('expenses.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=approve_expense&expense_id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showPopup(data.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showPopup('Error: ' + data.message, true);
                    }
                });
            }
        }

        function payExpense(id) {
            if (confirm('Mark this expense as paid?')) {
                fetch('expenses.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=pay_expense&expense_id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showPopup(data.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showPopup('Error: ' + data.message, true);
                    }
                });
            }
        }

        function deleteExpense(id) {
            if (confirm('Are you sure you want to delete this expense? This action cannot be undone.')) {
                fetch('expenses.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=delete_expense&expense_id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showPopup(data.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showPopup('Error: ' + data.message, true);
                    }
                });
            }
        }

        function showAddModal() {
            document.getElementById('addExpenseModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showRenewModal() {
            document.getElementById('renewModal').style.display = 'block';
        }

        function confirmRenewal() {
            closeModal('renewModal');
            processMonthlyRenewals();
        }

        function processSingleSalary(userId, staffName, baseSalary) {
            if (confirm(`Process salary for ${staffName} (৳${baseSalary.toLocaleString()})?`)) {
                fetch('expenses.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=process_single_salary&user_id=${userId}&base_salary=${baseSalary}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }

        function processMonthlyRenewals() {
            const currentDay = new Date().getDate();
            let confirmMessage = 'Process monthly salary renewals for all active staff?';
            
            if (currentDay < 10) {
                confirmMessage = `Today is ${currentDay}th. Monthly renewals are normally processed on the 10th. Continue anyway?`;
            }
            
            if (confirm(confirmMessage)) {
                const forceParam = currentDay < 10 ? '&force=1' : '';
                
                fetch('expenses.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=process_monthly_renewals${forceParam}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showPopup(data.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showPopup('Error: ' + data.message, true);
                    }
                });
            }
        }

        document.getElementById('addExpenseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add_expense');
            
            fetch('expenses.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
