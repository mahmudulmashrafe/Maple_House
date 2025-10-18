<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Manager'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle AJAX form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        switch ($_POST['action']) {
            case 'add_item':
                $stmt = $db->prepare("INSERT INTO meal_items (item_name, ingredients, category, meal_section, item_type) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['item_name'],
                    $_POST['ingredients'],
                    $_POST['category'],
                    $_POST['meal_section'],
                    $_POST['item_type']
                ]);
                echo json_encode(['success' => true, 'message' => 'Meal item added successfully!']);
                break;
                
            case 'update_item':
                $stmt = $db->prepare("UPDATE meal_items SET item_name = ?, ingredients = ?, category = ?, meal_section = ?, item_type = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['item_name'],
                    $_POST['ingredients'],
                    $_POST['category'],
                    $_POST['meal_section'],
                    $_POST['item_type'],
                    $_POST['item_id']
                ]);
                echo json_encode(['success' => true, 'message' => 'Meal item updated successfully!']);
                break;
                
            case 'delete_item':
                $stmt = $db->prepare("DELETE FROM meal_items WHERE id = ?");
                $stmt->execute([$_POST['item_id']]);
                echo json_encode(['success' => true, 'message' => 'Meal item deleted successfully!']);
                break;
                
            case 'toggle_status':
                $stmt = $db->prepare("UPDATE meal_items SET is_active = !is_active WHERE id = ?");
                $stmt->execute([$_POST['item_id']]);
                echo json_encode(['success' => true, 'message' => 'Item status updated successfully!']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Fetch all meal items
$filter_category = $_GET['category'] ?? 'all';
$filter_type = $_GET['type'] ?? 'all';
$filter_section = $_GET['section'] ?? '';

$query = "SELECT * FROM meal_items WHERE 1=1";
$params = [];

if ($filter_category !== 'all') {
    $query .= " AND category = ?";
    $params[] = $filter_category;
}

if ($filter_type !== 'all') {
    $query .= " AND item_type = ?";
    $params[] = $filter_type;
}

if ($filter_section !== '' && !empty($filter_section)) {
    $query .= " AND meal_section = ?";
    $params[] = $filter_section;
}

$query .= " ORDER BY meal_section, category, item_name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$meal_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_items,
        COUNT(CASE WHEN item_type = 'veg' THEN 1 END) as veg_items,
        COUNT(CASE WHEN item_type = 'non_veg' THEN 1 END) as nonveg_items,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_items,
        COUNT(CASE WHEN meal_section = 'breakfast' THEN 1 END) as breakfast_items,
        COUNT(CASE WHEN meal_section = 'lunch' THEN 1 END) as lunch_items,
        COUNT(CASE WHEN meal_section = 'dinner' THEN 1 END) as dinner_items,
        COUNT(CASE WHEN meal_section = 'lunch_dinner' THEN 1 END) as lunch_dinner_items,
        COUNT(CASE WHEN meal_section = 'drinks' THEN 1 END) as drinks_items
    FROM meal_items
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Items Management - Maple House</title>
    <link rel="icon" type="image/jpeg" href="../images/favicon.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="../images/favicon.jpg">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
            overflow: hidden;
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
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            gap: 20px;
            margin-bottom: 20px;
            flex-shrink: 0;
        }
        
        .stats-row-1 {
            grid-template-columns: repeat(4, 1fr);
        }
        
        .stats-row-2 {
            grid-template-columns: repeat(5, 1fr);
            margin-bottom: 10px;
        }
        
        .stat-card {
            background: white;
            padding: 8px 12px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s ease;
            border: 2px solid #e2e8f0;
        }
        
        /* First Row - General Stats */
        .stats-row-1 .stat-card:nth-child(1) {
            border-color: #3498db;
        }
        
        .stats-row-1 .stat-card:nth-child(2) {
            border-color: #27ae60;
        }
        
        .stats-row-1 .stat-card:nth-child(3) {
            border-color: #2ecc71;
        }
        
        .stats-row-1 .stat-card:nth-child(4) {
            border-color: #e74c3c;
        }
        
        /* Second Row - Meal Types */
        .stats-row-2 .stat-card:nth-child(1) {
            border-color: #f39c12;
        }
        
        .stats-row-2 .stat-card:nth-child(2) {
            border-color: #9b59b6;
        }
        
        .stats-row-2 .stat-card:nth-child(3) {
            border-color: #34495e;
        }
        
        .stats-row-2 .stat-card:nth-child(4) {
            border-color: #16a085;
        }
        
        .stats-row-2 .stat-card:nth-child(5) {
            border-color: #e67e22;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 3px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            flex-shrink: 0;
            padding: 10px 0;
        }
        
        .filter-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            font-size: 0.9rem;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-success {
            background: #48bb78;
            color: white;
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        .btn-warning {
            background: #ed8936;
            color: white;
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        .btn-danger {
            background: #f56565;
            color: white;
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        .items-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
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
        
        .table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        
        .table th,
        .table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            word-wrap: break-word;
            overflow: hidden;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2d3748;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 2px solid #dee2e6;
        }
        
        /* Column widths */
        th:nth-child(1), td:nth-child(1) { width: 200px; } /* Item Name */
        th:nth-child(2), td:nth-child(2) { width: 220px; } /* Ingredients */
        th:nth-child(3), td:nth-child(3) { width: 150px; } /* Category */
        th:nth-child(4), td:nth-child(4) { width: 120px; } /* Meal Section */
        th:nth-child(5), td:nth-child(5) { width: 100px; } /* Type */
        th:nth-child(6), td:nth-child(6) { width: 80px; } /* Status */
        th:nth-child(7), td:nth-child(7) { width: 150px; } /* Actions */
        
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
        
        .table tr:hover {
            background: #f7fafc;
        }
        
        .category-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .category-protein { background: #fed7d7; color: #c53030; }
        .category-fish { background: #bee3f8; color: #2b6cb0; }
        .category-rice { background: #faf089; color: #744210; }
        .category-vegetable { background: #c6f6d5; color: #22543d; }
        .category-breakfast_veg { background: #d69e2e; color: white; }
        .category-breakfast_nonveg { background: #e53e3e; color: white; }
        .category-breakfast_item { background: #805ad5; color: white; }
        .category-drinks { background: #38b2ac; color: white; }

        .section-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .section-breakfast { background: #fbb6ce; color: #97266d; }
        .section-lunch { background: #fed7aa; color: #c05621; }
        .section-dinner { background: #c6f6d5; color: #22543d; }
        .section-lunch_dinner { background: #fad5a5; color: #9c4221; }
        .section-drinks { background: #bee3f8; color: #2c5282; }
        .section-all { background: #e2e8f0; color: #4a5568; }
        
        .type-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .type-veg {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .type-non_veg {
            background: #fed7d7;
            color: #c53030;
        }
        
        .status-active {
            color: #22543d;
            font-weight: 500;
        }
        
        .status-inactive {
            color: #a0aec0;
            font-style: italic;
        }
        
        .actions {
            display: flex;
            gap: 5px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            background: white;
            color: #2c3e50;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            font-size: 1.2rem;
            font-weight: 600;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2d3748;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn-secondary {
            background: #a0aec0;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #718096;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }

        /* Popup Alert Styles */
        .popup-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            min-width: 300px;
            max-width: 500px;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideInRight 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .popup-alert.success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }

        .popup-alert.error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }

        .alert-close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: inherit;
            margin-left: auto;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .alert-close:hover {
            opacity: 0.7;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .stats-row-1 {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stats-row-2 {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .controls {
                flex-direction: column;
                gap: 10px;
            }
            
            .filters {
                flex-direction: column;
                gap: 10px;
            }
            
            .table {
                font-size: 0.8rem;
            }
            
            .table th,
            .table td {
                padding: 10px 8px;
            }

            .popup-alert {
                top: 10px;
                right: 10px;
                left: 10px;
                min-width: auto;
                max-width: none;
            }
        }
        
        @media (max-width: 480px) {
            .stats-row-1,
            .stats-row-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">

        <?php if ($success_message): ?>
            <div class="popup-alert success" id="successAlert">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button class="alert-close" onclick="closeAlert('successAlert')">&times;</button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="popup-alert error" id="errorAlert">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button class="alert-close" onclick="closeAlert('errorAlert')">&times;</button>
            </div>
        <?php endif; ?>

        <!-- First Row: Total, Active, Vegetarian, Non-Vegetarian -->
        <div class="stats-grid stats-row-1">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_items']; ?></div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_items']; ?></div>
                <div class="stat-label">Active Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['veg_items']; ?></div>
                <div class="stat-label">Vegetarian</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['nonveg_items']; ?></div>
                <div class="stat-label">Non-Vegetarian</div>
            </div>
        </div>

        <!-- Second Row: Breakfast, Lunch, Dinner, Lunch & Dinner, Drinks -->
        <div class="stats-grid stats-row-2">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['breakfast_items']; ?></div>
                <div class="stat-label">Breakfast Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['lunch_items']; ?></div>
                <div class="stat-label">Lunch Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['dinner_items']; ?></div>
                <div class="stat-label">Dinner Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['lunch_dinner_items']; ?></div>
                <div class="stat-label">Lunch & Dinner</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['drinks_items']; ?></div>
                <div class="stat-label">Drinks Items</div>
            </div>
        </div>

        <div class="controls">
            <?php if (isset($_GET['section']) || isset($_GET['category']) || isset($_GET['type'])): ?>
                <div style="margin-bottom: 10px; padding: 10px; background: #f0f4ff; border-radius: 6px; font-size: 0.9rem;">
                    <strong>Active Filters:</strong>
                    <?php if (!empty($filter_section)): ?>
                        Meal Section: <span style="background: #667eea; color: white; padding: 2px 6px; border-radius: 3px;"><?= ucfirst($filter_section) ?></span>
                    <?php endif; ?>
                    <?php if ($filter_category !== 'all'): ?>
                        Category: <span style="background: #48bb78; color: white; padding: 2px 6px; border-radius: 3px;"><?= ucfirst(str_replace('_', ' ', $filter_category)) ?></span>
                    <?php endif; ?>
                    <?php if ($filter_type !== 'all'): ?>
                        Type: <span style="background: #ed8936; color: white; padding: 2px 6px; border-radius: 3px;"><?= $filter_type === 'non_veg' ? 'Non-Veg' : 'Vegetarian' ?></span>
                    <?php endif; ?>
                    <a href="?" style="margin-left: 10px; color: #667eea; text-decoration: none;">Clear All</a>
                </div>
            <?php endif; ?>
            <div class="filters">
                <select class="filter-select" onchange="filterItems()" id="sectionFilter">
                    <option value="" <?php echo ($filter_section === '' || !isset($_GET['section'])) ? 'selected' : ''; ?>>All Meal Sections</option>
                    <option value="breakfast" <?php echo $filter_section === 'breakfast' ? 'selected' : ''; ?>>Breakfast</option>
                    <option value="lunch" <?php echo $filter_section === 'lunch' ? 'selected' : ''; ?>>Lunch</option>
                    <option value="dinner" <?php echo $filter_section === 'dinner' ? 'selected' : ''; ?>>Dinner</option>
                    <option value="lunch_dinner" <?php echo $filter_section === 'lunch_dinner' ? 'selected' : ''; ?>>Lunch and Dinner</option>
                    <option value="drinks" <?php echo $filter_section === 'drinks' ? 'selected' : ''; ?>>Drinks</option>
                    <option value="all" <?php echo $filter_section === 'all' ? 'selected' : ''; ?>>Universal (All Meals)</option>
                </select>

                <select class="filter-select" onchange="filterItems()" id="categoryFilter">
                    <option value="all" <?php echo $filter_category === 'all' ? 'selected' : ''; ?>>All Categories</option>
                    <option value="protein" <?php echo $filter_category === 'protein' ? 'selected' : ''; ?>>Protein</option>
                    <option value="fish" <?php echo $filter_category === 'fish' ? 'selected' : ''; ?>>Fish</option>
                    <option value="rice" <?php echo $filter_category === 'rice' ? 'selected' : ''; ?>>Rice</option>
                    <option value="vegetable" <?php echo $filter_category === 'vegetable' ? 'selected' : ''; ?>>Vegetable</option>
                    <option value="breakfast_veg" <?php echo $filter_category === 'breakfast_veg' ? 'selected' : ''; ?>>Breakfast Veg</option>
                    <option value="breakfast_nonveg" <?php echo $filter_category === 'breakfast_nonveg' ? 'selected' : ''; ?>>Breakfast Non-Veg</option>
                    <option value="breakfast_item" <?php echo $filter_category === 'breakfast_item' ? 'selected' : ''; ?>>Breakfast Items</option>
                    <option value="drinks" <?php echo $filter_category === 'drinks' ? 'selected' : ''; ?>>Drinks</option>
                </select>
                
                <select class="filter-select" onchange="filterItems()" id="typeFilter">
                    <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="veg" <?php echo $filter_type === 'veg' ? 'selected' : ''; ?>>Vegetarian</option>
                    <option value="non_veg" <?php echo $filter_type === 'non_veg' ? 'selected' : ''; ?>>Non-Vegetarian</option>
                </select>
            </div>
            
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add New Item
            </button>
        </div>

        <div class="items-table">
            <div class="table-header">
                <h3><i class="fas fa-utensils"></i> Meal Items</h3>
            </div>
            <div class="table-content">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Ingredients</th>
                            <th>Category</th>
                            <th>Meal Section</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                <tbody>
                    <?php foreach ($meal_items as $item): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($item['ingredients'] ?: 'N/A'); ?>
                            </td>
                            <td>
                                <span class="category-badge category-<?php echo $item['category']; ?>">
                                    <?php echo str_replace('_', ' ', ucfirst($item['category'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="section-badge section-<?php echo $item['meal_section']; ?>">
                                    <?php 
                                    if ($item['meal_section'] === 'lunch_dinner') {
                                        echo 'Lunch & Dinner';
                                    } else {
                                        echo ucfirst($item['meal_section']); 
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <span class="type-badge type-<?php echo $item['item_type']; ?>">
                                    <?php echo $item['item_type'] === 'non_veg' ? 'Non-Veg' : 'Vegetarian'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="<?php echo $item['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $item['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <button class="btn btn-success" onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn <?php echo $item['is_active'] ? 'btn-warning' : 'btn-info'; ?>" onclick="toggleStatus(<?php echo $item['id']; ?>)" title="<?php echo $item['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas <?php echo $item['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                    </button>
                                    <button class="btn btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="itemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" id="modalTitle">Add New Item</div>
            <div class="modal-body">
                <form id="itemForm" method="POST">
                    <input type="hidden" name="action" id="formAction" value="add_item">
                    <input type="hidden" name="item_id" id="itemId">
                    
                    <div class="form-group">
                        <label>Item Name</label>
                        <input type="text" name="item_name" id="itemName" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Ingredients</label>
                        <textarea name="ingredients" id="itemIngredients" placeholder="Enter ingredients separated by commas (e.g., Chicken, Onion, Garlic, Ginger, Spices)" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Meal Section</label>
                        <select name="meal_section" id="itemMealSection" required>
                            <option value="">Select Meal Section</option>
                            <option value="breakfast">Breakfast Only</option>
                            <option value="lunch">Lunch Only</option>
                            <option value="dinner">Dinner Only</option>
                            <option value="lunch_dinner">Lunch and Dinner Only</option>
                            <option value="drinks">Drinks (Universal)</option>
                            <option value="all">All Meals (Universal)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" id="itemCategory" required>
                            <option value="">Select Category</option>
                            <option value="protein">Protein</option>
                            <option value="fish">Fish</option>
                            <option value="rice">Rice</option>
                            <option value="vegetable">Vegetable</option>
                            <option value="breakfast_veg">Breakfast Vegetarian</option>
                            <option value="breakfast_nonveg">Breakfast Non-Vegetarian</option>
                            <option value="breakfast_item">Breakfast Items</option>
                            <option value="drinks">Drinks</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Type</label>
                        <select name="item_type" id="itemType" required>
                            <option value="">Select Type</option>
                            <option value="veg">Vegetarian</option>
                            <option value="non_veg">Non-Vegetarian</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
        
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Item';
            document.getElementById('formAction').value = 'add_item';
            document.getElementById('itemId').value = '';
            document.getElementById('itemName').value = '';
            document.getElementById('itemIngredients').value = '';
            document.getElementById('itemMealSection').value = '';
            document.getElementById('itemCategory').value = '';
            document.getElementById('itemType').value = '';
            document.getElementById('itemModal').style.display = 'block';
        }

        function editItem(item) {
            document.getElementById('modalTitle').textContent = 'Edit Item';
            document.getElementById('formAction').value = 'update_item';
            document.getElementById('itemId').value = item.id;
            document.getElementById('itemName').value = item.item_name;
            document.getElementById('itemIngredients').value = item.ingredients || '';
            document.getElementById('itemMealSection').value = item.meal_section || 'all';
            document.getElementById('itemCategory').value = item.category;
            document.getElementById('itemType').value = item.item_type;
            document.getElementById('itemModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('itemModal').style.display = 'none';
        }

        function filterItems() {
            const section = document.getElementById('sectionFilter').value;
            const category = document.getElementById('categoryFilter').value;
            const type = document.getElementById('typeFilter').value;
            
            let url = '?';
            if (section !== '' && section !== 'all') url += 'section=' + section + '&';
            if (category !== 'all') url += 'category=' + category + '&';
            if (type !== 'all') url += 'type=' + type + '&';
            
            // Remove trailing & or ? if no parameters
            if (url === '?') {
                window.location.href = window.location.pathname;
            } else {
                window.location.href = url.slice(0, -1);
            }
        }

        // Handle form submission with AJAX
        document.getElementById('itemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('meal_items.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showPopup(data.message);
                    closeModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showPopup(data.message, true);
                }
            })
            .catch(error => {
                showPopup('An error occurred. Please try again.', true);
            });
        });

        // Delete item function
        function deleteItem(id) {
            if (confirm('Are you sure you want to delete this meal item?')) {
                const formData = new FormData();
                formData.append('action', 'delete_item');
                formData.append('item_id', id);
                
                fetch('meal_items.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showPopup(data.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showPopup(data.message, true);
                    }
                });
            }
        }

        // Toggle status function
        function toggleStatus(id) {
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('item_id', id);
            
            fetch('meal_items.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showPopup(data.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showPopup(data.message, true);
                }
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('itemModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Alert management functions
        function closeAlert(alertId) {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }
        }

        // Auto-hide popup alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.popup-alert');
            alerts.forEach(function(alert) {
                if (alert) {
                    alert.style.animation = 'slideOutRight 0.3s ease-in';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }
            });
        }, 5000);
    </script>
</body>
</html>
