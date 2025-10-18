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

// Get categories and units for form dropdowns
$categories_query = "SELECT * FROM inventory_categories ORDER BY name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

$units_query = "SELECT * FROM inventory_units ORDER BY name";
$units_stmt = $db->prepare($units_query);
$units_stmt->execute();
$units = $units_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get inventory statistics
$stats_query = "SELECT 
                    COUNT(*) as total_items,
                    COUNT(CASE WHEN current_stock <= minimum_stock THEN 1 END) as low_stock_items,
                    COUNT(CASE WHEN current_stock = 0 THEN 1 END) as out_of_stock_items,
                    COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as expiring_soon
                FROM inventory_items 
                WHERE is_active = 1";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent inventory items
$recent_items_query = "SELECT ii.*, ic.name as category_name, iu.abbreviation as unit_abbr
                       FROM inventory_items ii
                       LEFT JOIN inventory_categories ic ON ii.category_id = ic.id
                       LEFT JOIN inventory_units iu ON ii.unit_id = iu.id
                       WHERE ii.is_active = 1
                       ORDER BY ii.created_at DESC
                       LIMIT 8";
$recent_items_stmt = $db->prepare($recent_items_query);
$recent_items_stmt->execute();
$recent_items = $recent_items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get low stock items
$low_stock_query = "SELECT ii.*, ic.name as category_name, iu.abbreviation as unit_abbr
                    FROM inventory_items ii
                    LEFT JOIN inventory_categories ic ON ii.category_id = ic.id
                    LEFT JOIN inventory_units iu ON ii.unit_id = iu.id
                    WHERE ii.is_active = 1 AND ii.current_stock <= ii.minimum_stock
                    ORDER BY (ii.current_stock / ii.minimum_stock) ASC
                    LIMIT 6";
$low_stock_stmt = $db->prepare($low_stock_query);
$low_stock_stmt->execute();
$low_stock_items = $low_stock_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent transactions
$recent_transactions_query = "SELECT it.*, ii.name as item_name, CONCAT(u.first_name, ' ', u.last_name) as performed_by_name
                              FROM inventory_transactions it
                              JOIN inventory_items ii ON it.item_id = ii.id
                              JOIN users u ON it.performed_by = u.id
                              ORDER BY it.transaction_date DESC
                              LIMIT 6";
$recent_transactions_stmt = $db->prepare($recent_transactions_query);
$recent_transactions_stmt->execute();
$recent_transactions = $recent_transactions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all items for filtering
$all_items_query = "SELECT ii.*, ic.name as category_name, iu.abbreviation as unit_abbr
                    FROM inventory_items ii
                    LEFT JOIN inventory_categories ic ON ii.category_id = ic.id
                    LEFT JOIN inventory_units iu ON ii.unit_id = iu.id
                    WHERE ii.is_active = 1
                    ORDER BY ii.name ASC";
$all_items_stmt = $db->prepare($all_items_query);
$all_items_stmt->execute();
$all_items = $all_items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get out of stock items
$out_of_stock_query = "SELECT ii.*, ic.name as category_name, iu.abbreviation as unit_abbr
                       FROM inventory_items ii
                       LEFT JOIN inventory_categories ic ON ii.category_id = ic.id
                       LEFT JOIN inventory_units iu ON ii.unit_id = iu.id
                       WHERE ii.is_active = 1 AND ii.current_stock = 0
                       ORDER BY ii.name ASC";
$out_of_stock_stmt = $db->prepare($out_of_stock_query);
$out_of_stock_stmt->execute();
$out_of_stock_items = $out_of_stock_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get expiring soon items
$expiring_soon_query = "SELECT ii.*, ic.name as category_name, iu.abbreviation as unit_abbr
                        FROM inventory_items ii
                        LEFT JOIN inventory_categories ic ON ii.category_id = ic.id
                        LEFT JOIN inventory_units iu ON ii.unit_id = iu.id
                        WHERE ii.is_active = 1 AND ii.expiry_date IS NOT NULL 
                        AND ii.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                        ORDER BY ii.expiry_date ASC";
$expiring_soon_stmt = $db->prepare($expiring_soon_query);
$expiring_soon_stmt->execute();
$expiring_soon_items = $expiring_soon_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories and units for add form
$categories_query = "SELECT * FROM inventory_categories ORDER BY name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

$units_query = "SELECT * FROM inventory_units ORDER BY name";
$units_stmt = $db->prepare($units_query);
$units_stmt->execute();
$units = $units_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Overview - Maple House</title>
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
        
        .inventory-buttons {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 5px;
        }
        
        @media (max-width: 768px) {
            .inventory-buttons {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .inventory-buttons {
                grid-template-columns: 1fr;
            }
        }
        
        .inventory-card {
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
        
        .inventory-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .inventory-card.total:hover {
            border-color: #667eea;
        }
        
        .inventory-card.low-stock:hover {
            border-color: #f5576c;
        }
        
        .inventory-card.out-of-stock:hover {
            border-color: #ff9a9e;
        }
        
        .inventory-card.expiring:hover {
            border-color: #fcb69f;
        }
        
        .inventory-card.total {
            background: white;
            color: #667eea;
            border: 1px solid #667eea;
        }
        
        .inventory-card.low-stock {
            background: white;
            color: #f5576c;
            border: 1px solid #f5576c;
        }
        
        .inventory-card.out-of-stock {
            background: white;
            color: #ff9a9e;
            border: 1px solid #ff9a9e;
        }
        
        .inventory-card.expiring {
            background: white;
            color: #fcb69f;
            border: 1px solid #fcb69f;
        }
        
        .inventory-card i {
            font-size: 1.3rem;
            margin-bottom: 3px;
            opacity: 0.9;
        }
        
        .inventory-count {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 1px;
        }
        
        .inventory-label {
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .inventory-desc {
            font-size: 0.7rem;
            opacity: 0.8;
        }
        
        .page-wrapper {
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 2px 20px 20px 20px;
            overflow: hidden;
        }
        
        .fixed-header {
            flex-shrink: 0;
            margin-bottom: 2px;
        }
        
        .scrollable-content {
            flex: 1;
            overflow-y: auto;
            padding-right: 5px;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* Internet Explorer 10+ */
        }
        
        .scrollable-content::-webkit-scrollbar {
            display: none; /* WebKit */
        }
        
        .recent-sections {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }
        
        @media (max-width: 768px) {
            .recent-sections {
                grid-template-columns: 1fr;
            }
        }
        
        .recent-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-header {
            padding: 20px;
            background: white;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .section-header.items {
            background: white;
            color: #667eea;
        }
        
        .section-header.low-stock {
            background: white;
            color: #f5576c;
        }
        
        .section-header.transactions {
            background: white;
            color: #38f9d7;
        }
        
        .section-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }
        
        .section-content {
            padding: 0;
        }
        
        .item-row {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s ease;
        }
        
        .item-row:last-child {
            border-bottom: none;
        }
        
        .item-row:hover {
            background: #f8f9fa;
        }
        
        .item-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .item-details {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.4;
        }
        
        .item-meta {
            font-size: 0.8rem;
            color: #888;
            margin-top: 5px;
        }
        
        .stock-badge {
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .stock-normal {
            background: #d4edda;
            color: #155724;
        }
        
        .stock-low {
            background: #fff3cd;
            color: #856404;
        }
        
        .stock-out {
            background: #f8d7da;
            color: #721c24;
        }
        
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #666;
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
        }
        
        .view-all-btn:hover {
            background: #3498db;
            color: white;
        }
        
        .items-list {
            display: none;
            flex-direction: column;
            height: 100%;
            margin-top: 20px;
        }
        
        .items-list.active {
            display: flex;
        }
        
        .items-content-wrapper {
            flex: 1;
            overflow-y: auto;
            padding: 0 5px 20px 0;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* Internet Explorer 10+ */
        }
        
        .items-content-wrapper::-webkit-scrollbar {
            display: none; /* WebKit */
        }
        
        .items-header {
            background: white;
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin: 15px 0 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 10px;
            z-index: 98;
            flex-shrink: 0;
        }
        
        .items-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        
        .add-item-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .add-item-btn:hover {
            background: #2980b9;
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .item-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        
        .item-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .item-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1rem;
            margin: 0;
        }
        
        .item-category {
            background: #e3f2fd;
            color: #1976d2;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
        }
        
        .item-details {
            margin-bottom: 15px;
        }
        
        .item-detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .item-detail-label {
            color: #666;
        }
        
        .item-detail-value {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .item-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-edit {
            background: #f39c12;
            color: white;
        }
        
        .btn-edit:hover {
            background: #e67e22;
        }
        
        
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c0392b;
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
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 10;
            background: white;
        }
        
        .modal-title {
            margin: 0;
            color: #2c3e50;
        }
        
        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }
        
        .close:hover {
            color: #333;
        }
        
        .modal-body {
            padding: 20px;
            flex: 1;
            overflow-y: auto;
            max-height: calc(90vh - 140px);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        /* Form Styles */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Fixed Header with Inventory Buttons -->
        <div class="fixed-header">
            <div class="inventory-buttons">
        <div class="inventory-card total" onclick="showItems('all')">
            <i class="fas fa-boxes"></i>
            <div class="inventory-count"><?php echo $stats['total_items']; ?></div>
            <div class="inventory-label">Total Items</div>
            <div class="inventory-desc">All inventory items</div>
        </div>
        
        <div class="inventory-card low-stock" onclick="showItems('low_stock')">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="inventory-count"><?php echo $stats['low_stock_items']; ?></div>
            <div class="inventory-label">Low Stock</div>
            <div class="inventory-desc">Items running low</div>
        </div>
        
        <div class="inventory-card out-of-stock" onclick="showItems('out_of_stock')">
            <i class="fas fa-times-circle"></i>
            <div class="inventory-count"><?php echo $stats['out_of_stock_items']; ?></div>
            <div class="inventory-label">Unavailable</div>
            <div class="inventory-desc">Items unavailable</div>
        </div>
        
        <div class="inventory-card expiring" onclick="showItems('expiring')">
            <i class="fas fa-clock"></i>
            <div class="inventory-count"><?php echo $stats['expiring_soon']; ?></div>
            <div class="inventory-label">Expiring Soon</div>
            <div class="inventory-desc">Within 30 days</div>
        </div>
            </div>
        </div>
        
        <!-- Scrollable Content -->
        <div class="scrollable-content">
            <!-- Recent Sections -->
            <div class="recent-sections">
        <!-- Recent Items -->
        <div class="recent-section">
            <div class="section-header items">
                <i class="fas fa-boxes"></i>
                <h3>Recent Items</h3>
            </div>
            <div class="section-content">
                <?php if (!empty($recent_items)): ?>
                    <?php foreach (array_slice($recent_items, 0, 4) as $item): ?>
                        <div class="item-row">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-details">
                                <?php echo htmlspecialchars($item['category_name']); ?> • 
                                Stock: <?php echo $item['current_stock']; ?> <?php echo htmlspecialchars($item['unit_abbr']); ?>
                                <span class="stock-badge <?php 
                                    if ($item['current_stock'] == 0) echo 'stock-out';
                                    elseif ($item['current_stock'] <= $item['minimum_stock']) echo 'stock-low';
                                    else echo 'stock-normal';
                                ?>">
                                    <?php 
                                        if ($item['current_stock'] == 0) echo 'Out';
                                        elseif ($item['current_stock'] <= $item['minimum_stock']) echo 'Low';
                                        else echo 'OK';
                                    ?>
                                </span>
                            </div>
                            <div class="item-meta">
                                Location: <?php echo htmlspecialchars($item['location'] ?: 'Not specified'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="inventory.php" class="view-all-btn">
                        <i class="fas fa-arrow-right"></i> View All Items
                    </a>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-boxes"></i>
                        <p>No items found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Low Stock Items -->
        <div class="recent-section">
            <div class="section-header low-stock">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Low Stock Alert</h3>
            </div>
            <div class="section-content">
                <?php if (!empty($low_stock_items)): ?>
                    <?php foreach (array_slice($low_stock_items, 0, 4) as $item): ?>
                        <div class="item-row">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-details">
                                Current: <?php echo $item['current_stock']; ?> <?php echo htmlspecialchars($item['unit_abbr']); ?> • 
                                Min: <?php echo $item['minimum_stock']; ?> <?php echo htmlspecialchars($item['unit_abbr']); ?>
                            </div>
                            <div class="item-meta">
                                <?php echo htmlspecialchars($item['category_name']); ?> • 
                                <?php echo htmlspecialchars($item['location'] ?: 'Location not set'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="inventory.php?filter=low_stock" class="view-all-btn">
                        <i class="fas fa-arrow-right"></i> View All Low Stock
                    </a>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>All items well stocked!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="recent-section">
            <div class="section-header transactions">
                <i class="fas fa-exchange-alt"></i>
                <h3>Recent Transactions</h3>
            </div>
            <div class="section-content">
                <?php if (!empty($recent_transactions)): ?>
                    <?php foreach (array_slice($recent_transactions, 0, 4) as $transaction): ?>
                        <div class="item-row">
                            <div class="item-name"><?php echo htmlspecialchars($transaction['item_name']); ?></div>
                            <div class="item-details">
                                <?php echo $transaction['transaction_type']; ?>: <?php echo $transaction['quantity']; ?> units • 
                                <?php echo htmlspecialchars($transaction['reference_type']); ?>
                            </div>
                            <div class="item-meta">
                                By <?php echo htmlspecialchars($transaction['performed_by_name']); ?> • 
                                <?php echo date('M j, g:i A', strtotime($transaction['transaction_date'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="inventory.php?view=transactions" class="view-all-btn">
                        <i class="fas fa-arrow-right"></i> View All Transactions
                    </a>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-exchange-alt"></i>
                        <p>No recent transactions</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="recent-section">
            <div class="section-header">
                <i class="fas fa-tools"></i>
                <h3>Quick Actions</h3>
            </div>
            <div class="section-content">
                <div class="item-row" style="cursor: pointer;" onclick="window.location.href='inventory.php?action=add'">
                    <div class="item-name"><i class="fas fa-plus"></i> Add New Item</div>
                    <div class="item-details">Add a new inventory item to the system</div>
                </div>
                <div class="item-row" style="cursor: pointer;" onclick="window.location.href='inventory.php?action=stock_in'">
                    <div class="item-name"><i class="fas fa-arrow-down"></i> Stock In</div>
                    <div class="item-details">Record incoming inventory</div>
                </div>
                <div class="item-row" style="cursor: pointer;" onclick="window.location.href='inventory.php?action=stock_out'">
                    <div class="item-name"><i class="fas fa-arrow-up"></i> Stock Out</div>
                    <div class="item-details">Record outgoing inventory</div>
                </div>
                <div class="item-row" style="cursor: pointer;" onclick="window.location.href='export_inventory.php'">
                    <div class="item-name"><i class="fas fa-download"></i> Export Report</div>
                    <div class="item-details">Download inventory report</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Items List Section -->
    <div id="itemsList" class="items-list">
        <div class="items-header">
            <h2 id="itemsTitle" class="items-title">All Items</h2>
            <button class="add-item-btn" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add New Item
            </button>
        </div>
        <div class="items-content-wrapper">
            <div id="itemsGrid" class="items-grid">
                <!-- Items will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div id="addItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Inventory Item</h3>
                <button class="close" onclick="closeAddModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addItemForm">
                    <div class="form-group">
                        <label class="form-label">Item Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category *</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Unit *</label>
                            <select name="unit_id" class="form-control" required>
                                <option value="">Select Unit</option>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?php echo $unit['id']; ?>">
                                        <?php echo htmlspecialchars($unit['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Current Stock *</label>
                            <input type="number" name="current_stock" class="form-control" min="0" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Unit Cost</label>
                            <input type="number" name="unit_cost" class="form-control" min="0" step="0.01">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Minimum Stock *</label>
                            <input type="number" name="minimum_stock" class="form-control" min="0" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Maximum Stock</label>
                            <input type="number" name="maximum_stock" class="form-control" min="0" step="0.01">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Supplier Name</label>
                            <input type="text" name="supplier_name" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Supplier Contact</label>
                            <input type="text" name="supplier_contact" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Add Item
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Store all items data
        const allItems = <?php echo json_encode($all_items); ?>;
        const lowStockItems = <?php echo json_encode($low_stock_items); ?>;
        const outOfStockItems = <?php echo json_encode($out_of_stock_items); ?>;
        const expiringSoonItems = <?php echo json_encode($expiring_soon_items); ?>;

        function showItems(type) {
            // Hide recent sections
            document.querySelector('.recent-sections').style.display = 'none';
            
            // Show items list
            const itemsList = document.getElementById('itemsList');
            const itemsTitle = document.getElementById('itemsTitle');
            const itemsGrid = document.getElementById('itemsGrid');
            
            itemsList.classList.add('active');
            
            let items = [];
            let title = '';
            
            switch(type) {
                case 'all':
                    items = allItems;
                    title = 'All Items';
                    break;
                case 'low_stock':
                    items = lowStockItems;
                    title = 'Low Stock Items';
                    break;
                case 'out_of_stock':
                    items = outOfStockItems;
                    title = 'Unavailable Items';
                    break;
                case 'expiring':
                    items = expiringSoonItems;
                    title = 'Expiring Soon Items';
                    break;
            }
            
            itemsTitle.textContent = title;
            
            // Generate items HTML
            let itemsHTML = '';
            items.forEach(item => {
                const stockStatus = getStockStatus(item);
                const expiryInfo = getExpiryInfo(item);
                
                itemsHTML += `
                    <div class="item-card">
                        <div class="item-card-header">
                            <h3 class="item-name">${item.name}</h3>
                            <span class="item-category">${item.category_name || 'No Category'}</span>
                        </div>
                        <div class="item-details">
                            <div class="item-detail-row">
                                <span class="item-detail-label">Current Stock:</span>
                                <span class="item-detail-value">${item.current_stock} ${item.unit_abbr || ''}</span>
                            </div>
                            <div class="item-detail-row">
                                <span class="item-detail-label">Min Stock:</span>
                                <span class="item-detail-value">${item.minimum_stock} ${item.unit_abbr || ''}</span>
                            </div>
                            <div class="item-detail-row">
                                <span class="item-detail-label">Status:</span>
                                <span class="item-detail-value ${stockStatus.class}">${stockStatus.text}</span>
                            </div>
                            <div class="item-detail-row">
                                <span class="item-detail-label">Location:</span>
                                <span class="item-detail-value">${item.location || 'Not specified'}</span>
                            </div>
                            ${expiryInfo ? `
                            <div class="item-detail-row">
                                <span class="item-detail-label">Expiry:</span>
                                <span class="item-detail-value">${expiryInfo}</span>
                            </div>
                            ` : ''}
                        </div>
                        <div class="item-actions">
                            <button class="btn-sm btn-edit" onclick="editItem(${item.id})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-sm btn-delete" onclick="deleteItem(${item.id})">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                `;
            });
            
            if (items.length === 0) {
                itemsHTML = `
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.5;"></i>
                        <p>No items found in this category.</p>
                    </div>
                `;
            }
            
            itemsGrid.innerHTML = itemsHTML;
        }

        function getStockStatus(item) {
            if (item.current_stock == 0) {
                return { text: 'Out of Stock', class: 'text-danger' };
            } else if (item.current_stock <= item.minimum_stock) {
                return { text: 'Low Stock', class: 'text-warning' };
            } else {
                return { text: 'In Stock', class: 'text-success' };
            }
        }

        function getExpiryInfo(item) {
            if (!item.expiry_date) return null;
            
            const expiryDate = new Date(item.expiry_date);
            const today = new Date();
            const diffTime = expiryDate - today;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays < 0) {
                return `Expired ${Math.abs(diffDays)} days ago`;
            } else if (diffDays <= 30) {
                return `Expires in ${diffDays} days`;
            } else {
                return expiryDate.toLocaleDateString();
            }
        }

        function openAddModal() {
            document.getElementById('addItemModal').style.display = 'block';
        }

        function closeAddModal() {
            document.getElementById('addItemModal').style.display = 'none';
            document.getElementById('addItemForm').reset();
        }

        function editItem(itemId) {
            // Redirect to edit page or open edit modal
            alert('Edit functionality will be implemented');
        }

        function updateStock(itemId) {
            // Open stock update modal
            alert('Stock update functionality will be implemented');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addItemModal');
            if (event.target == modal) {
                closeAddModal();
            }
        }

        // Handle form submission
        document.getElementById('addItemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_item');
            
            fetch('inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessPopup('Item added successfully!');
                    closeAddModal();
                    location.reload(); // Refresh to show new item
                } else {
                    alert('Error: ' + (data.message || 'Failed to add item'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding the item');
            });
        });

        // Edit item function
        function editItem(itemId) {
            // Find the item data from the currently loaded items
            fetch(`inventory.php?action=get_item&item_id=${itemId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showEditModal(data.item);
                    } else {
                        alert('Error loading item data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading item data');
                });
        }

        // Show edit modal with item data
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

        // Close edit modal
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Handle edit form submission
        document.getElementById('editItemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_item');
            
            fetch('inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessPopup('Item updated successfully!');
                    closeEditModal();
                    location.reload(); // Refresh to show updated item
                } else {
                    alert('Error: ' + (data.message || 'Failed to update item'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the item');
            });
        });

        // Delete item function
        function deleteItem(itemId) {
            if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
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
                        showSuccessPopup('Item deleted successfully!');
                        location.reload(); // Refresh to remove deleted item
                    } else {
                        alert('Error: ' + (data.message || 'Failed to delete item'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the item');
                });
            }
        }

        // Show success popup
        function showSuccessPopup(message) {
            // Create popup element
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
            
            // Add animation keyframes
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
            
            // Remove popup after 3 seconds
            setTimeout(() => {
                popup.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    if (popup.parentNode) {
                        popup.parentNode.removeChild(popup);
                    }
                }, 300);
            }, 3000);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }
    </script>

    <!-- Edit Item Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Inventory Item</h3>
                <button class="close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editItemForm">
                    <input type="hidden" name="item_id" id="edit_item_id">
                    
                    <div class="form-group">
                        <label class="form-label">Item Name *</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category *</label>
                            <select name="category_id" id="edit_category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Unit *</label>
                            <select name="unit_id" id="edit_unit_id" class="form-control" required>
                                <option value="">Select Unit</option>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?php echo $unit['id']; ?>">
                                        <?php echo htmlspecialchars($unit['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Current Stock (Read Only)</label>
                            <input type="number" id="edit_current_stock" class="form-control" readonly style="background: #f8f9fa;">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Unit Cost</label>
                            <input type="number" name="unit_cost" id="edit_unit_cost" class="form-control" min="0" step="0.01">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Minimum Stock *</label>
                            <input type="number" name="minimum_stock" id="edit_minimum_stock" class="form-control" min="0" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Maximum Stock</label>
                            <input type="number" name="maximum_stock" id="edit_maximum_stock" class="form-control" min="0" step="0.01">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Supplier Name</label>
                            <input type="text" name="supplier_name" id="edit_supplier_name" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Supplier Contact</label>
                            <input type="text" name="supplier_contact" id="edit_supplier_contact" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" id="edit_location" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" id="edit_expiry_date" class="form-control">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Update Item
                    </button>
                </form>
            </div>
        </div>
    </div>
        </div>
    </div>
</body>
</html>
