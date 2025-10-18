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
            case 'add_item':
                $stmt = $db->prepare("INSERT INTO inventory_items (name, description, category_id, unit_id, current_stock, minimum_stock, maximum_stock, unit_cost, supplier_name, supplier_contact, location, expiry_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $expiry = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
                $stmt->execute([
                    $_POST['name'], $_POST['description'], $_POST['category_id'], $_POST['unit_id'],
                    $_POST['current_stock'], $_POST['minimum_stock'], $_POST['maximum_stock'],
                    $_POST['unit_cost'], $_POST['supplier_name'], $_POST['supplier_contact'],
                    $_POST['location'], $expiry, $_SESSION['user_id']
                ]);
                
                // Log initial stock transaction
                $item_id = $db->lastInsertId();
                if ($_POST['current_stock'] > 0) {
                    $stmt = $db->prepare("INSERT INTO inventory_transactions (item_id, transaction_type, quantity, previous_stock, new_stock, reference_type, notes, performed_by) VALUES (?, 'IN', ?, 0, ?, 'ADJUSTMENT', 'Initial stock', ?)");
                    $stmt->execute([$item_id, $_POST['current_stock'], $_POST['current_stock'], $_SESSION['user_id']]);
                }
                
                // Record expense for inventory purchase
                if ($_POST['current_stock'] > 0 && $_POST['unit_cost'] > 0) {
                    $total_cost = $_POST['current_stock'] * $_POST['unit_cost'];
                    $expense_description = "Inventory purchase: " . $_POST['name'] . " (" . $_POST['current_stock'] . " units)";
                    
                    try {
                        $expense_stmt = $db->prepare("INSERT INTO expenses (expense_type, category, description, amount, expense_date, vendor_name, vendor_contact, reference_id, reference_type, created_by, status) VALUES ('inventory', 'Inventory Purchase', ?, ?, CURDATE(), ?, ?, ?, 'inventory_item', ?, 'approved')");
                        $expense_stmt->execute([
                            $expense_description,
                            $total_cost,
                            $_POST['supplier_name'] ?: 'Unknown Supplier',
                            $_POST['supplier_contact'] ?: '',
                            $item_id,
                            $_SESSION['user_id']
                        ]);
                    } catch (Exception $e) {
                        // Continue even if expense recording fails
                        error_log("Failed to record inventory expense: " . $e->getMessage());
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Item added successfully and expense recorded']);
                break;
                
            case 'update_item':
                $stmt = $db->prepare("UPDATE inventory_items SET name=?, description=?, category_id=?, unit_id=?, minimum_stock=?, maximum_stock=?, unit_cost=?, supplier_name=?, supplier_contact=?, location=?, expiry_date=? WHERE id=?");
                $expiry = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
                $stmt->execute([
                    $_POST['name'], $_POST['description'], $_POST['category_id'], $_POST['unit_id'],
                    $_POST['minimum_stock'], $_POST['maximum_stock'], $_POST['unit_cost'],
                    $_POST['supplier_name'], $_POST['supplier_contact'], $_POST['location'],
                    $expiry, $_POST['item_id']
                ]);
                echo json_encode(['success' => true, 'message' => 'Item updated successfully']);
                break;
                
            case 'delete_item':
                $stmt = $db->prepare("UPDATE inventory_items SET is_active = FALSE WHERE id = ?");
                $stmt->execute([$_POST['item_id']]);
                echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
                break;
                
            case 'adjust_stock':
                // Get current stock and item details
                $stmt = $db->prepare("SELECT current_stock, name, unit_cost, supplier_name FROM inventory_items WHERE id = ?");
                $stmt->execute([$_POST['item_id']]);
                $item_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $current_stock = $item_data['current_stock'];
                
                $new_stock = $current_stock + $_POST['adjustment'];
                if ($new_stock < 0) {
                    echo json_encode(['success' => false, 'message' => 'Stock cannot be negative']);
                    break;
                }
                
                // Update stock
                $stmt = $db->prepare("UPDATE inventory_items SET current_stock = ? WHERE id = ?");
                $stmt->execute([$new_stock, $_POST['item_id']]);
                
                // Log transaction
                $transaction_type = $_POST['adjustment'] > 0 ? 'IN' : 'OUT';
                $stmt = $db->prepare("INSERT INTO inventory_transactions (item_id, transaction_type, quantity, previous_stock, new_stock, reference_type, notes, performed_by) VALUES (?, ?, ?, ?, ?, 'ADJUSTMENT', ?, ?)");
                $stmt->execute([$_POST['item_id'], $transaction_type, abs($_POST['adjustment']), $current_stock, $new_stock, $_POST['notes'], $_SESSION['user_id']]);
                
                // Record expense for stock additions (purchases)
                if ($_POST['adjustment'] > 0 && $item_data['unit_cost'] > 0) {
                    $total_cost = $_POST['adjustment'] * $item_data['unit_cost'];
                    $expense_description = "Stock replenishment: " . $item_data['name'] . " (+" . $_POST['adjustment'] . " units)";
                    
                    try {
                        $expense_stmt = $db->prepare("INSERT INTO expenses (expense_type, category, description, amount, expense_date, vendor_name, reference_id, reference_type, notes, created_by, status) VALUES ('inventory', 'Stock Replenishment', ?, ?, CURDATE(), ?, ?, 'inventory_item', ?, ?, 'approved')");
                        $expense_stmt->execute([
                            $expense_description,
                            $total_cost,
                            $item_data['supplier_name'] ?: 'Unknown Supplier',
                            $_POST['item_id'],
                            $_POST['notes'] ?: 'Stock adjustment',
                            $_SESSION['user_id']
                        ]);
                    } catch (Exception $e) {
                        // Continue even if expense recording fails
                        error_log("Failed to record stock adjustment expense: " . $e->getMessage());
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Stock adjusted successfully and expense recorded']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Handle GET requests for fetching item data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_item') {
    header('Content-Type: application/json');
    
    try {
        $stmt = $db->prepare("SELECT * FROM inventory_items WHERE id = ? AND is_active = 1");
        $stmt->execute([$_GET['item_id']]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            echo json_encode(['success' => true, 'item' => $item]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Get inventory items with category and unit info
$inventory_query = "SELECT i.*, c.name as category_name, u.name as unit_name, u.abbreviation as unit_abbr,
                    CASE WHEN i.current_stock <= i.minimum_stock THEN 'low' ELSE 'normal' END as stock_status
                    FROM inventory_items i
                    LEFT JOIN inventory_categories c ON i.category_id = c.id
                    LEFT JOIN inventory_units u ON i.unit_id = u.id
                    WHERE i.is_active = TRUE
                    ORDER BY i.name";
$inventory_stmt = $db->prepare($inventory_query);
$inventory_stmt->execute();
$inventory_items = $inventory_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories
$categories_stmt = $db->prepare("SELECT * FROM inventory_categories ORDER BY name");
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get units
$units_stmt = $db->prepare("SELECT * FROM inventory_units ORDER BY name");
$units_stmt->execute();
$units = $units_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent transactions
$transactions_query = "SELECT t.*, i.name as item_name, u.first_name, u.last_name
                       FROM inventory_transactions t
                       JOIN inventory_items i ON t.item_id = i.id
                       JOIN users u ON t.performed_by = u.id
                       ORDER BY t.transaction_date DESC LIMIT 10";
$transactions_stmt = $db->prepare($transactions_query);
$transactions_stmt->execute();
$recent_transactions = $transactions_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Maple House</title>
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
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn:hover { transform: translateY(-1px); opacity: 0.9; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        .low-stock { color: #e74c3c; }
        .normal-stock { color: #27ae60; }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        .inventory-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .table-header {
            background: #3498db;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table-content {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 600px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .table-tools {
            background: #ffffff;
            padding: 12px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .form-control.small {
            max-width: 220px;
            padding: 8px 10px;
            height: 36px;
        }
        .stock-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .stock-low {
            background: #ffeaa7;
            color: #e17055;
        }
        .stock-normal {
            background: #d4f6ed;
            color: #27ae60;
        }
        
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .widget {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .widget-header {
            background: #3498db;
            color: white;
            padding: 15px 20px;
            font-weight: 600;
        }
        .widget-content {
            padding: 20px;
        }
        
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
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .alert {
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1><i class="fas fa-boxes"></i> Inventory Management</h1>
                <p>Manage food items and supplies inventory</p>
            </div>
            <button class="btn btn-primary" onclick="showAddModal()">
                <i class="fas fa-plus"></i> Add New Item
            </button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($inventory_items); ?></div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number low-stock"><?php echo count(array_filter($inventory_items, function($item) { return $item['stock_status'] === 'low'; })); ?></div>
                <div class="stat-label">Low Stock Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">৳<?php echo number_format(array_sum(array_map(function($item) { return $item['current_stock'] * $item['unit_cost']; }, $inventory_items))); ?></div>
                <div class="stat-label">Total Stock Value</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($categories); ?></div>
                <div class="stat-label">Categories</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="inventory-table">
                <div class="table-header">
                    <h3><i class="fas fa-list"></i> Inventory Items</h3>
                    <div>
                        <button class="btn btn-secondary" onclick="exportInventory()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
                <div class="table-tools">
                    <input type="text" id="searchInput" class="form-control small" placeholder="Search items..." oninput="filterTable()">
                    <select id="categoryFilter" class="form-control small" onchange="filterTable()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['name']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="statusFilter" class="form-control small" onchange="filterTable()">
                        <option value="">All Status</option>
                        <option value="low">Low Stock</option>
                        <option value="normal">Normal</option>
                    </select>
                </div>
                <div class="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Min/Max</th>
                                <th>Unit Cost</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryBody">
                            <?php foreach ($inventory_items as $item): ?>
                            <tr data-name="<?php echo strtolower(htmlspecialchars($item['name'])); ?>" data-category="<?php echo htmlspecialchars($item['category_name']); ?>" data-status="<?php echo htmlspecialchars($item['stock_status']); ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                    <?php if ($item['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                <td>
                                    <strong><?php echo number_format($item['current_stock'], 2); ?></strong>
                                    <small><?php echo htmlspecialchars($item['unit_abbr']); ?></small>
                                </td>
                                <td>
                                    <?php echo number_format($item['minimum_stock'], 2); ?> / 
                                    <?php echo number_format($item['maximum_stock'], 2); ?>
                                    <small><?php echo htmlspecialchars($item['unit_abbr']); ?></small>
                                </td>
                                <td>৳<?php echo number_format($item['unit_cost'], 2); ?></td>
                                <td>
                                    <span class="stock-badge stock-<?php echo $item['stock_status']; ?>">
                                        <?php echo $item['stock_status'] === 'low' ? 'Low Stock' : 'Normal'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-warning btn-sm" onclick="showAdjustModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['name']); ?>', <?php echo $item['current_stock']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-primary btn-sm" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteItem(<?php echo $item['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="sidebar">
                <div class="widget">
                    <div class="widget-header">
                        <i class="fas fa-history"></i> Recent Transactions
                    </div>
                    <div class="widget-content">
                        <?php foreach ($recent_transactions as $transaction): ?>
                        <div style="padding: 10px 0; border-bottom: 1px solid #eee;">
                            <strong><?php echo htmlspecialchars($transaction['item_name']); ?></strong>
                            <br>
                            <small>
                                <?php echo $transaction['transaction_type']; ?> 
                                <?php echo number_format($transaction['quantity'], 2); ?>
                                by <?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?>
                            </small>
                            <br>
                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($transaction['transaction_date'])); ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="widget">
                    <div class="widget-header">
                        <i class="fas fa-exclamation-triangle"></i> Low Stock Alerts
                    </div>
                    <div class="widget-content">
                        <?php 
                        $low_stock_items = array_filter($inventory_items, function($item) { 
                            return $item['stock_status'] === 'low'; 
                        });
                        ?>
                        <?php if (empty($low_stock_items)): ?>
                            <p class="text-muted">No low stock items</p>
                        <?php else: ?>
                            <?php foreach ($low_stock_items as $item): ?>
                            <div style="padding: 10px 0; border-bottom: 1px solid #eee;">
                                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                <br>
                                <small>Current: <?php echo number_format($item['current_stock'], 2); ?> <?php echo htmlspecialchars($item['unit_abbr']); ?></small>
                                <br>
                                <small>Minimum: <?php echo number_format($item['minimum_stock'], 2); ?> <?php echo htmlspecialchars($item['unit_abbr']); ?></small>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Add New Inventory Item</h3>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="addItemForm">
                    <div class="form-group">
                        <label>Item Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Unit *</label>
                            <select name="unit_id" class="form-control" required>
                                <option value="">Select Unit</option>
                                <?php foreach ($units as $unit): ?>
                                <option value="<?php echo $unit['id']; ?>"><?php echo htmlspecialchars($unit['name'] . ' (' . $unit['abbreviation'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Current Stock</label>
                            <input type="number" name="current_stock" class="form-control" step="0.01" value="0">
                        </div>
                        <div class="form-group">
                            <label>Unit Cost (৳)</label>
                            <input type="number" name="unit_cost" class="form-control" step="0.01" value="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Minimum Stock</label>
                            <input type="number" name="minimum_stock" class="form-control" step="0.01" value="0">
                        </div>
                        <div class="form-group">
                            <label>Maximum Stock</label>
                            <input type="number" name="maximum_stock" class="form-control" step="0.01" value="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Supplier Name</label>
                            <input type="text" name="supplier_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Supplier Contact</label>
                            <input type="text" name="supplier_contact" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Storage Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g., Kitchen, Pantry, Refrigerator">
                        </div>
                        <div class="form-group">
                            <label>Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control">
                        </div>
                    </div>
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Inventory Item</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editItemForm">
                    <input type="hidden" name="item_id" id="edit_item_id">
                    <div class="form-group">
                        <label>Item Name *</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category_id" id="edit_category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Unit *</label>
                            <select name="unit_id" id="edit_unit_id" class="form-control" required>
                                <option value="">Select Unit</option>
                                <?php foreach ($units as $unit): ?>
                                <option value="<?php echo $unit['id']; ?>"><?php echo htmlspecialchars($unit['name'] . ' (' . $unit['abbreviation'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Unit Cost (৳)</label>
                            <input type="number" name="unit_cost" id="edit_unit_cost" class="form-control" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Current Stock (Read Only)</label>
                            <input type="number" id="edit_current_stock" class="form-control" readonly style="background: #f8f9fa;">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Minimum Stock</label>
                            <input type="number" name="minimum_stock" id="edit_minimum_stock" class="form-control" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Maximum Stock</label>
                            <input type="number" name="maximum_stock" id="edit_maximum_stock" class="form-control" step="0.01">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Supplier Name</label>
                            <input type="text" name="supplier_name" id="edit_supplier_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Supplier Contact</label>
                            <input type="text" name="supplier_contact" id="edit_supplier_contact" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Storage Location</label>
                            <input type="text" name="location" id="edit_location" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Expiry Date</label>
                            <input type="date" name="expiry_date" id="edit_expiry_date" class="form-control">
                        </div>
                    </div>
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Stock Adjustment Modal -->
    <div id="adjustModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Adjust Stock</h3>
                <span class="close" onclick="closeModal('adjustModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="adjustStockForm">
                    <input type="hidden" name="item_id" id="adjust_item_id">
                    <div class="form-group">
                        <label>Item Name</label>
                        <input type="text" id="adjust_item_name" class="form-control" readonly style="background: #f8f9fa;">
                    </div>
                    <div class="form-group">
                        <label>Current Stock</label>
                        <input type="number" id="adjust_current_stock" class="form-control" readonly style="background: #f8f9fa;">
                    </div>
                    <div class="form-group">
                        <label>Adjustment (+ to add, - to reduce)</label>
                        <input type="number" name="adjustment" class="form-control" step="0.01" required placeholder="e.g., +10 or -5">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Reason for adjustment..."></textarea>
                    </div>
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('adjustModal')">Cancel</button>
                        <button type="submit" class="btn btn-warning">Adjust Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function filterTable() {
            const search = (document.getElementById('searchInput')?.value || '').toLowerCase();
            const category = document.getElementById('categoryFilter')?.value || '';
            const status = document.getElementById('statusFilter')?.value || '';
            const rows = document.querySelectorAll('#inventoryBody tr');
            rows.forEach(row => {
                const name = row.getAttribute('data-name') || '';
                const rowCategory = row.getAttribute('data-category') || '';
                const rowStatus = row.getAttribute('data-status') || '';
                const matchesSearch = !search || name.includes(search);
                const matchesCategory = !category || rowCategory === category;
                const matchesStatus = !status || rowStatus === status;
                row.style.display = (matchesSearch && matchesCategory && matchesStatus) ? '' : 'none';
            });
        }

        // Modal functions
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function showEditModal(item) {
            document.getElementById('edit_item_id').value = item.id;
            document.getElementById('edit_name').value = item.name;
            document.getElementById('edit_description').value = item.description || '';
            document.getElementById('edit_category_id').value = item.category_id;
            document.getElementById('edit_unit_id').value = item.unit_id;
            document.getElementById('edit_unit_cost').value = item.unit_cost;
            document.getElementById('edit_current_stock').value = item.current_stock;
            document.getElementById('edit_minimum_stock').value = item.minimum_stock;
            document.getElementById('edit_maximum_stock').value = item.maximum_stock;
            document.getElementById('edit_supplier_name').value = item.supplier_name || '';
            document.getElementById('edit_supplier_contact').value = item.supplier_contact || '';
            document.getElementById('edit_location').value = item.location || '';
            document.getElementById('edit_expiry_date').value = item.expiry_date || '';
            document.getElementById('editModal').style.display = 'block';
        }

        function showAdjustModal(itemId, itemName, currentStock) {
            document.getElementById('adjust_item_id').value = itemId;
            document.getElementById('adjust_item_name').value = itemName;
            document.getElementById('adjust_current_stock').value = currentStock;
            document.getElementById('adjustModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Form submissions
        document.getElementById('addItemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, 'add_item', function() {
                closeModal('addModal');
                showSuccessPopup('Item added successfully!');
                setTimeout(() => location.reload(), 1000);
            });
        });

        document.getElementById('editItemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, 'update_item', function() {
                closeModal('editModal');
                showSuccessPopup('Item updated successfully!');
                setTimeout(() => location.reload(), 1000);
            });
        });

        // Show success popup function
        function showSuccessPopup(message) {
            const popup = document.createElement('div');
            popup.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #27ae60;
                color: white;
                padding: 15px 20px;
                border-radius: 5px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                font-weight: 500;
                animation: slideInRight 0.3s ease;
            `;
            popup.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
            
            if (!document.getElementById('successPopupStyles')) {
                const style = document.createElement('style');
                style.id = 'successPopupStyles';
                style.textContent = `
                    @keyframes slideInRight {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes slideOutRight {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(100%); opacity: 0; }
                    }
                `;
                document.head.appendChild(style);
            }
            
            document.body.appendChild(popup);
            
            setTimeout(() => {
                popup.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    if (popup.parentNode) {
                        popup.parentNode.removeChild(popup);
                    }
                }, 300);
            }, 3000);
        }

        document.getElementById('adjustStockForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, 'adjust_stock', function() {
                closeModal('adjustModal');
                location.reload();
            });
        });

        function submitForm(form, action, successCallback) {
            const formData = new FormData(form);
            formData.append('action', action);
            
            fetch('inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successCallback();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        function deleteItem(itemId) {
            if (confirm('Are you sure you want to delete this item?')) {
                const formData = new FormData();
                formData.append('action', 'delete_item');
                formData.append('item_id', itemId);
                
                fetch('inventory.php', {
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
            }
        }

        function exportInventory() {
            window.open('export_inventory.php', '_blank');
        }

        // Handle edit parameter from URL
        window.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const editId = urlParams.get('edit');
            if (editId) {
                // Find the item in the table and trigger edit
                const editButtons = document.querySelectorAll('button[onclick*="showEditModal"]');
                editButtons.forEach(button => {
                    const onclick = button.getAttribute('onclick');
                    if (onclick.includes(editId)) {
                        button.click();
                        // Clean URL
                        window.history.replaceState({}, document.title, window.location.pathname);
                    }
                });
            }
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>

