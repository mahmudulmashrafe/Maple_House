<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Manager'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch meal items from database
$meal_items_stmt = $db->prepare("SELECT * FROM meal_items WHERE is_active = 1 ORDER BY category, item_name");
$meal_items_stmt->execute();
$all_meal_items = $meal_items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch chefs from chefs table
$kitchen_staff_stmt = $db->prepare("
    SELECT c.id, CONCAT(u.first_name, ' ', u.last_name) as full_name, c.specialization, c.shift_hours, c.employee_id
    FROM chefs c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.is_available = 1 
    ORDER BY c.specialization, u.first_name
");
$kitchen_staff_stmt->execute();
$kitchen_staff = $kitchen_staff_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize items by category
$default_items = [
    'proteins' => [],
    'fish' => [],
    'rice' => [],
    'vegetables' => [],
    'breakfast_veg' => [],
    'breakfast_nonveg' => [],
    'breakfast_items' => []
];

foreach ($all_meal_items as $item) {
    if ($item['category'] === 'protein') {
        $default_items['proteins'][] = $item;
    } elseif ($item['category'] === 'fish') {
        $default_items['fish'][] = $item;
    } elseif ($item['category'] === 'rice') {
        $default_items['rice'][] = $item;
    } elseif ($item['category'] === 'vegetable') {
        $default_items['vegetables'][] = $item;
    } elseif ($item['category'] === 'breakfast_veg') {
        $default_items['breakfast_veg'][] = $item;
    } elseif ($item['category'] === 'breakfast_nonveg') {
        $default_items['breakfast_nonveg'][] = $item;
    } elseif ($item['category'] === 'breakfast_item') {
        $default_items['breakfast_items'][] = $item;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log all POST data
    error_log("POST Data: " . print_r($_POST, true));
    
    try {
        if ($_POST['action'] === 'save_meal') {
            $meal_date = $_POST['meal_date'];
            $meal_type = $_POST['meal_type'];
            
            // Get the chef ID for the specific meal type
            $chef_field = strtolower($meal_type) . '_chef';
            $chef_id = !empty($_POST[$chef_field]) ? $_POST[$chef_field] : null;
            
            // Prepare item data for the actual database structure
            $item_fields = [];
            $item_values = [];
            $has_items = false;
            
            $meal_prefix = strtolower($meal_type);
            $sections = ['veg', 'nonveg', 'drinks'];
            $section_slots = ['veg' => 3, 'nonveg' => 3, 'drinks' => 2];
            
            foreach ($sections as $section) {
                for ($i = 1; $i <= $section_slots[$section]; $i++) {
                    $field_name = "{$meal_prefix}_{$section}_item_{$i}";
                    $item_id = !empty($_POST[$field_name]) ? $_POST[$field_name] : null;
                    $item_fields[] = $field_name;
                    $item_values[] = $item_id;
                    
                    if ($item_id) {
                        $has_items = true;
                    }
                }
            }
            
            // Validate that at least one item is selected
            if (!$has_items) {
                throw new Exception("Please select at least one meal item.");
            }
            
            // Check if meal already exists
            $check_stmt = $db->prepare("SELECT id FROM daily_meals WHERE meal_date = ? AND meal_type = ?");
            $check_stmt->execute([$meal_date, $meal_type]);
            $existing_meal = $check_stmt->fetch();
            
            if ($existing_meal) {
                // Update existing meal with the actual database structure
                $chef_column = strtolower($meal_type) . '_chef_id';
                $update_fields = implode(' = ?, ', $item_fields) . ' = ?';
                $sql = "UPDATE daily_meals SET {$chef_column} = ?, {$update_fields} WHERE meal_date = ? AND meal_type = ?";
                $params = array_merge([$chef_id], $item_values, [$meal_date, $meal_type]);
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $success_message = "Meal updated successfully!";
            } else {
                // Insert new meal with the actual database structure
                $chef_column = strtolower($meal_type) . '_chef_id';
                $fields_str = implode(', ', $item_fields);
                $placeholders = str_repeat('?, ', count($item_fields) - 1) . '?';
                $sql = "INSERT INTO daily_meals (meal_date, meal_type, {$chef_column}, {$fields_str}) VALUES (?, ?, ?, {$placeholders})";
                $params = array_merge([$meal_date, $meal_type, $chef_id], $item_values);
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $success_message = "Meal saved successfully!";
            }
        } elseif ($_POST['action'] === 'delete_meal') {
            // Get meal type before deleting for success message
            $meal_type_stmt = $db->prepare("SELECT meal_type FROM daily_meals WHERE id = ?");
            $meal_type_stmt->execute([$_POST['meal_id']]);
            $meal_type = $meal_type_stmt->fetchColumn();
            
            $stmt = $db->prepare("DELETE FROM daily_meals WHERE id = ?");
            $stmt->execute([$_POST['meal_id']]);
            $success_message = "$meal_type Meal Deleted Successfully!";
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Handle week navigation
if (isset($_GET['week'])) {
    $week_start = $_GET['week'];
} else {
    // Get current week (Saturday to Friday)
    $current_date = new DateTime();
    $day_of_week = $current_date->format('w'); // 0 = Sunday, 6 = Saturday
    
    if ($day_of_week == 0) { // Sunday
        $week_start = $current_date->modify('-1 day')->format('Y-m-d'); // Go to Saturday
    } elseif ($day_of_week < 6) { // Monday to Friday
        $days_back = $day_of_week + 1; // Days to go back to Saturday
        $week_start = $current_date->modify("-{$days_back} days")->format('Y-m-d');
    } else { // Saturday
        $week_start = $current_date->format('Y-m-d');
    }
}

$week_end = (new DateTime($week_start))->modify('+6 days')->format('Y-m-d');

// Remove old template code - not needed with new structure

// Fetch meals for the week with chef information and meal items
$stmt = $db->prepare("
    SELECT dm.*, 
           -- Breakfast chef info
           CONCAT(bu.first_name, ' ', bu.last_name) as breakfast_chef_name,
           bc.specialization as breakfast_chef_specialization,
           bc.employee_id as breakfast_chef_employee_id,
           -- Lunch chef info
           CONCAT(lu.first_name, ' ', lu.last_name) as lunch_chef_name,
           lc.specialization as lunch_chef_specialization,
           lc.employee_id as lunch_chef_employee_id,
           -- Dinner chef info
           CONCAT(du.first_name, ' ', du.last_name) as dinner_chef_name,
           dc.specialization as dinner_chef_specialization,
           dc.employee_id as dinner_chef_employee_id
    FROM daily_meals dm 
    LEFT JOIN chefs bc ON dm.breakfast_chef_id = bc.id 
    LEFT JOIN users bu ON bc.user_id = bu.id 
    LEFT JOIN chefs lc ON dm.lunch_chef_id = lc.id 
    LEFT JOIN users lu ON lc.user_id = lu.id 
    LEFT JOIN chefs dc ON dm.dinner_chef_id = dc.id 
    LEFT JOIN users du ON dc.user_id = du.id 
    WHERE dm.meal_date BETWEEN ? AND ? 
    ORDER BY dm.meal_date, FIELD(dm.meal_type, 'Breakfast', 'Lunch', 'Dinner')
");
$stmt->execute([$week_start, $week_end]);
$meals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process meals to get item names and ingredients
foreach ($meals as &$meal) {
    $meal['items'] = [];
    $meal['menu_items'] = '';
    $meal['ingredients'] = [];
    
    // Get item IDs from both old and new structure
    $item_ids = [];
    $meal_prefix = strtolower($meal['meal_type']);
    
    // Check new structure first (sectioned)
    $sections = ['veg', 'nonveg', 'drinks'];
    $section_slots = ['veg' => 3, 'nonveg' => 3, 'drinks' => 2];
    $has_new_data = false;
    
    foreach ($sections as $section) {
        for ($i = 1; $i <= $section_slots[$section]; $i++) {
            $field_name = "{$meal_prefix}_{$section}_item_{$i}";
            if (!empty($meal[$field_name])) {
                $item_ids[] = $meal[$field_name];
                $has_new_data = true;
            }
        }
    }
    
    // If no new structure data, fall back to old structure
    if (!$has_new_data) {
        if ($meal['meal_type'] === 'Breakfast') {
            for ($i = 1; $i <= 4; $i++) {
                if (!empty($meal["breakfast_item_$i"])) {
                    $item_ids[] = $meal["breakfast_item_$i"];
                }
            }
        } elseif ($meal['meal_type'] === 'Lunch') {
            for ($i = 1; $i <= 6; $i++) {
                if (!empty($meal["lunch_item_$i"])) {
                    $item_ids[] = $meal["lunch_item_$i"];
                }
            }
        } elseif ($meal['meal_type'] === 'Dinner') {
            for ($i = 1; $i <= 6; $i++) {
                if (!empty($meal["dinner_item_$i"])) {
                    $item_ids[] = $meal["dinner_item_$i"];
                }
            }
        }
    }
    
    // Fetch item details
    if (!empty($item_ids)) {
        $placeholders = str_repeat('?,', count($item_ids) - 1) . '?';
        $items_stmt = $db->prepare("SELECT item_name, ingredients FROM meal_items WHERE id IN ($placeholders)");
        $items_stmt->execute($item_ids);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($items) {
            $meal['items'] = array_column($items, 'item_name');
            $meal['menu_items'] = implode(', ', $meal['items']);
            
            $all_ingredients = array_filter(array_column($items, 'ingredients'));
            if ($all_ingredients) {
                $ingredient_array = [];
                foreach ($all_ingredients as $ingredient_list) {
                    if ($ingredient_list) {
                        $ingredient_array = array_merge($ingredient_array, explode(', ', $ingredient_list));
                    }
                }
                $meal['ingredients'] = array_unique($ingredient_array);
            }
        }
    }
}

// Organize meals by date and type
$weekly_meals = [];
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime($week_start . ' +' . $i . ' days'));
    $weekly_meals[$date] = ['Breakfast' => null, 'Lunch' => null, 'Dinner' => null];
}

foreach ($meals as $meal) {
    $weekly_meals[$meal['meal_date']][$meal['meal_type']] = $meal;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Management - Maple House</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f7fa;
            height: 100vh;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }
        
        .container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 0;
            margin: 0;
        }
        
        .header {
            background: white;
            color: #2c3e50;
            padding: 12px 20px;
            margin: 5px 20px 0 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 2rem;
            text-align: center;
            margin: 0 0 25px 0;
        }
        
        .week-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .nav-btn {
            padding: 8px 16px;
            background: #3498db;
            color: white;
            text-decoration: none !important;
            border-radius: 5px;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
        }
        
        .nav-btn:hover {
            background: #2980b9;
        }
        
        .current-week {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .meal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .day-column {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            min-height: 600px;
        }
        
        .day-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 600;
        }
        
        .day-date {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 4px;
        }
        
        .meal-section {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .meal-section:last-child {
            border-bottom: none;
        }
        
        .meal-type {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .add-meal-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .add-meal-btn:hover {
            background: #218838;
        }
        
        .meal-content {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 10px;
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        .meal-items {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .meal-cost {
            color: #667eea;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .meal-chef {
            color: #666;
            font-size: 0.8rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .meal-actions {
            margin-top: 10px;
            display: flex;
            gap: 5px;
            justify-content: center;
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
        }
        
        .btn-small {
            padding: 4px 8px;
            font-size: 0.75rem;
            border: none;
            border-radius: 3px;
            cursor: pointer;
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
        
        .btn-add {
            background: #27ae60;
            color: white;
            width: 100%;
            padding: 8px;
            font-size: 0.8rem;
        }
        
        .btn-add:hover {
            background: #229954;
        }
        
        .btn-small:hover {
            opacity: 0.8;
        }
        
        .empty-meal {
            color: #95a5a6;
            font-style: italic;
            text-align: center;
            padding: 20px 10px;
            font-size: 0.85rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(3px);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            background: white;
            color: #2c3e50;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
            border-bottom: 1px solid #e9ecef;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .close {
            color: #999;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            padding: 5px;
        }
        
        .close:hover {
            color: #666;
        }
        
        .modal-body {
            padding: 25px;
            flex: 1;
            overflow-y: auto;
            max-height: calc(90vh - 140px);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #34495e;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #6c757d;
            border: 1px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            max-height: 180px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            background: #f8f9fa;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 10px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
            background: white;
            border: 1px solid #e9ecef;
        }
        
        .checkbox-item:hover {
            background: #f0f4ff;
            border-color: #667eea;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .checkbox-item input[type="checkbox"] {
            margin: 0;
            width: 16px;
            height: 16px;
            accent-color: #667eea;
        }
        
        .checkbox-item span {
            font-size: 0.9rem;
            color: #2d3748;
            font-weight: 500;
        }
        
        .meal-options {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 25px;
            margin: 20px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #f1f3f4 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        #editMenuContainer {
            border: 1px solid #667eea;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
        }
        
        #editMenuContainer label {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .week-info h2 {
            margin: 0;
            color: #2c3e50;
        }
        
        .week-info small {
            color: #666;
            font-size: 0.8rem;
        }
        

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 8px;
        }

        .popup-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 500px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideInRight 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-close {
            background: none;
            border: none;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            margin-left: auto;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.7;
        }

        .alert-close:hover {
            opacity: 1;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="week-navigation">
                <?php 
                $prev_week = date('Y-m-d', strtotime($week_start . ' -7 days'));
                $next_week = date('Y-m-d', strtotime($week_start . ' +7 days'));
                ?>
                <a href="?week=<?= date('Y-m-d', strtotime($week_start . ' -7 days')) ?>" class="btn btn-secondary">
                    <i class="fas fa-chevron-left"></i> Previous Week
                </a>
                <div class="week-info">
                    <h2>Week of <?= date('M j', strtotime($week_start)) ?> - <?= date('M j, Y', strtotime($week_end)) ?></h2>
                    <small>(Saturday to Friday)</small>
                </div>
                <a href="?week=<?= date('Y-m-d', strtotime($week_start . ' +7 days')) ?>" class="btn btn-secondary">
                    Next Week <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            

        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success popup-alert" id="successAlert">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                <button class="alert-close" onclick="closeAlert('successAlert')">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error popup-alert" id="errorAlert">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                <button class="alert-close" onclick="closeAlert('errorAlert')">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Fixed Day Names Header -->
        <div class="day-names-header">
            <?php foreach ($weekly_meals as $date => $day_meals): ?>
                <div class="day-name-column">
                    <div class="day-header">
                        <?= date('l', strtotime($date)) ?>
                        <div class="day-date"><?= date('M j', strtotime($date)) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Scrollable Content Area -->
        <div class="content-wrapper">
            <!-- Meal Content -->
            <div class="meal-grid">
            <?php foreach ($weekly_meals as $date => $day_meals): ?>
                <div class="day-column">
                    
                    <?php foreach (['Breakfast', 'Lunch', 'Dinner'] as $meal_type): ?>
                        <div class="meal-section">
                            <div class="meal-type"><?= $meal_type ?></div>
                            
                            <?php if ($day_meals[$meal_type]): ?>
                                <div class="meal-info">
                                    <?php
                                    $meal = $day_meals[$meal_type];
                                    $meal_prefix = strtolower($meal_type);
                                    
                                    // Get items by section
                                    $veg_items = [];
                                    $nonveg_items = [];
                                    $drink_items = [];
                                    
                                    // Check new structure first
                                    $sections = ['veg', 'nonveg', 'drinks'];
                                    $section_slots = ['veg' => 3, 'nonveg' => 3, 'drinks' => 2];
                                    $has_new_data = false;
                                    
                                    foreach ($sections as $section) {
                                        for ($i = 1; $i <= $section_slots[$section]; $i++) {
                                            $field_name = "{$meal_prefix}_{$section}_item_{$i}";
                                            if (!empty($meal[$field_name])) {
                                                $has_new_data = true;
                                                $item_stmt = $db->prepare("SELECT item_name, item_type FROM meal_items WHERE id = ?");
                                                $item_stmt->execute([$meal[$field_name]]);
                                                $item_data = $item_stmt->fetch(PDO::FETCH_ASSOC);
                                                
                                                if ($item_data) {
                                                    if ($section === 'veg') {
                                                        $veg_items[] = $item_data['item_name'];
                                                    } elseif ($section === 'nonveg') {
                                                        $nonveg_items[] = $item_data['item_name'];
                                                    } elseif ($section === 'drinks') {
                                                        $drink_items[] = $item_data['item_name'];
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    
                                    // If no new structure data, fall back to old structure
                                    if (!$has_new_data && !empty($meal['menu_items'])) {
                                        // Fallback to old display
                                        echo '<div class="meal-items">' . htmlspecialchars($meal['menu_items']) . '</div>';
                                    } else {
                                        // Display categorized items
                                        if (!empty($veg_items)): ?>
                                            <div class="meal-category veg-category">
                                                <div class="category-header">🥬 Veg Meals</div>
                                                <div class="category-items"><?= implode(', ', $veg_items) ?></div>
                                            </div>
                                        <?php endif;
                                        
                                        if (!empty($nonveg_items)): ?>
                                            <div class="meal-category nonveg-category">
                                                <div class="category-header">🍖 Non-Veg Meals</div>
                                                <div class="category-items"><?= implode(', ', $nonveg_items) ?></div>
                                            </div>
                                        <?php endif;
                                        
                                        if (!empty($drink_items)): ?>
                                            <div class="meal-category drink-category">
                                                <div class="category-header">🥤 Drinks</div>
                                                <div class="category-items"><?= implode(', ', $drink_items) ?></div>
                                            </div>
                                        <?php endif;
                                    }
                                    ?>
                                    
                                    <?php 
                                    // Get the correct chef info for this meal type
                                    $chef_name = null;
                                    $chef_specialization = null;
                                    $chef_employee_id = null;
                                    
                                    if ($meal_type === 'Breakfast') {
                                        $chef_name = $meal['breakfast_chef_name'];
                                        $chef_specialization = $meal['breakfast_chef_specialization'];
                                        $chef_employee_id = $meal['breakfast_chef_employee_id'];
                                    } elseif ($meal_type === 'Lunch') {
                                        $chef_name = $meal['lunch_chef_name'];
                                        $chef_specialization = $meal['lunch_chef_specialization'];
                                        $chef_employee_id = $meal['lunch_chef_employee_id'];
                                    } elseif ($meal_type === 'Dinner') {
                                        $chef_name = $meal['dinner_chef_name'];
                                        $chef_specialization = $meal['dinner_chef_specialization'];
                                        $chef_employee_id = $meal['dinner_chef_employee_id'];
                                    }
                                    
                                    if ($chef_name): ?>
                                        <div class="meal-chef">
                                            <i class="fas fa-user-chef"></i> <?= htmlspecialchars($chef_name) ?>
                                            <?php if ($chef_specialization): ?>
                                                - <?= htmlspecialchars($chef_specialization) ?>
                                            <?php endif; ?>
                                            <?php if ($chef_employee_id): ?>
                                                (<?= htmlspecialchars($chef_employee_id) ?>)
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="meal-actions">
                                    <button class="btn-small btn-edit" onclick="openMealModal('<?= $date ?>', '<?= $meal_type ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn-small btn-delete" onclick="deleteMeal(<?= $day_meals[$meal_type]['id'] ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="empty-meal">No meal planned</div>
                                <div class="meal-actions">
                                    <button class="add-meal-btn" onclick="openMealModal('<?= $date ?>', '<?= $meal_type ?>')">
                                        <i class="fas fa-plus"></i> Add <?= $meal_type ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Meal Modal -->
    <div id="mealModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Meal</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="mealForm" method="POST">
                    <input type="hidden" name="action" value="save_meal">
                    <input type="hidden" name="meal_date" id="mealDate">
                    <input type="hidden" name="meal_type" id="mealType">
                    
                    
                    <div class="form-group">
                        <label id="chefLabel">Chef</label>
                        <select name="chef" id="chefSelect">
                            <option value="">Select Chef</option>
                            <?php foreach ($kitchen_staff as $staff): ?>
                                <option value="<?= $staff['id'] ?>">
                                    <?= htmlspecialchars($staff['full_name']) ?> - <?= htmlspecialchars($staff['specialization']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label id="itemsLabel">Meal Items</label>
                        <div class="meal-items-grid" id="itemsGrid">
                            <!-- Items will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Meal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Item Selection Modal -->
    <div id="itemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="itemModalTitle">Select Meal Item</h3>
                <span class="close" onclick="closeItemModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="meal-items-grid" id="itemsSelectionGrid">
                    <!-- Items will be populated by JavaScript based on meal type -->
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeItemModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="clearCurrentSlot()">Clear Slot</button>
                </div>
            </div>
        </div>
    </div>
    <style>
        .meal-items-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 20px;
        }

        .meal-section-header {
            margin-bottom: 10px;
        }

        .meal-section-header h4 {
            margin: 0;
            padding: 8px 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .meal-section-slots {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .item-slot {
            border: 2px dashed #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .item-slot:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .item-slot.filled {
            border-style: solid;
            border-color: #28a745;
            background: linear-gradient(135deg, #f8fff9 0%, #e8f5e8 100%);
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
        }

        .item-slot.filled:hover {
            border-color: #218838;
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            transform: translateY(-1px);
        }

        .item-selected-label {
            font-size: 0.7rem;
            color: #28a745;
            font-weight: 600;
            margin-top: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
        }

        .item-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .item-price {
            font-size: 0.8rem;
            color: #28a745;
            font-weight: 500;
        }

        .empty-slot {
            color: #6c757d;
            font-style: italic;
        }

        .item-category {
            font-size: 0.7rem;
            color: #6c757d;
            text-transform: capitalize;
            margin-top: 3px;
        }

        .no-items {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 40px;
            grid-column: 1 / -1;
        }

        /* Item selection modal grid - 4 items per row */
        #itemsSelectionGrid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }

        /* Responsive for smaller screens */
        @media (max-width: 768px) {
            #itemsSelectionGrid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .item-type {
            font-size: 0.65rem;
            margin-top: 3px;
            padding: 2px 6px;
            border-radius: 8px;
            font-weight: 500;
        }

        .type-veg {
            background: #c6f6d5;
            color: #22543d;
        }

        .type-nonveg {
            background: #fed7d7;
            color: #c53030;
        }

        /* Meal category display styles */
        .meal-category {
            margin-bottom: 8px;
            padding: 6px 10px;
            border-radius: 6px;
            border-left: 4px solid;
        }

        .veg-category {
            background: #f0fff4;
            border-left-color: #22c55e;
        }

        .nonveg-category {
            background: #fffbeb;
            border-left-color: #f59e0b;
        }

        .drink-category {
            background: #eff6ff;
            border-left-color: #3b82f6;
        }

        .category-header {
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .veg-category .category-header {
            color: #16a34a;
        }

        .nonveg-category .category-header {
            color: #d97706;
        }

        .drink-category .category-header {
            color: #2563eb;
        }

        .category-items {
            font-size: 0.8rem;
            color: #374151;
            line-height: 1.3;
        }

        /* Chef information styling */
        .meal-chef {
            margin-top: 10px;
            padding: 6px 10px;
            background: #fff7ed;
            border-left: 4px solid #f97316;
            border-radius: 6px;
            font-size: 0.8rem;
            color: #ea580c;
            font-weight: 500;
        }

        .meal-chef i {
            color: #f97316;
            margin-right: 5px;
        }

        /* Fixed Day Names Header */
        .day-names-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 15px;
            background: white;
            padding: 15px;
            margin: 10px 20px 0 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 99;
        }

        .day-name-column {
            text-align: center;
        }

        .day-name-column .day-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 8px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .day-name-column .day-date {
            font-size: 0.75rem;
            margin-top: 4px;
            opacity: 0.9;
        }

        /* Scrollable Content Area */
        .content-wrapper {
            flex: 1;
            overflow-y: auto;
            padding: 10px 20px 20px 20px;
        }
        
        /* Meal Content Grid */
        .meal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
        }

        .day-column .day-header {
            display: none; /* Hide original day headers since we have fixed ones */
        }
    </style>

    <script>
        let currentSlot = null;
        let currentMealData = null;
        const allMealItems = <?= json_encode($all_meal_items) ?>;
        const weeklyMeals = <?= json_encode($weekly_meals) ?>;
        
        console.log('Weekly meals data:', weeklyMeals);
        console.log('All meal items:', allMealItems);

        function openMealModal(date, mealType) {
            console.log('openMealModal called with:', date, mealType);
            
            // Ensure DOM elements exist
            const mealDateElement = document.getElementById('mealDate');
            const mealTypeElement = document.getElementById('mealType');
            
            if (!mealDateElement || !mealTypeElement) {
                console.error('Form elements not found!', {mealDateElement, mealTypeElement});
                return;
            }
            
            mealDateElement.value = date;
            mealTypeElement.value = mealType;
            
            // Update chef field name and label based on meal type
            const chefSelect = document.getElementById('chefSelect');
            const chefLabel = document.getElementById('chefLabel');
            
            if (chefSelect && chefLabel) {
                const chefFieldName = mealType.toLowerCase() + '_chef';
                chefSelect.name = chefFieldName;
                chefLabel.textContent = `${mealType} Chef`;
                console.log('Updated chef field name to:', chefFieldName);
            }
            
            // Format date for display
            const dateObj = new Date(date);
            const formattedDate = dateObj.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            const modalTitleElement = document.getElementById('modalTitle');
            console.log('Modal title element:', modalTitleElement);
            
            if (modalTitleElement) {
                modalTitleElement.textContent = `${mealType} - ${formattedDate}`;
                console.log('Set modal title to:', `${mealType} - ${formattedDate}`);
            } else {
                console.error('Modal title element not found!');
            }
            
            console.log('Opening meal modal for:', date, mealType);
            
            // Generate item slots
            generateItemSlots(mealType);
            
            // Load existing meal data if available
            loadExistingMeal(date, mealType);
            
            document.getElementById('mealModal').style.display = 'block';
        }

        function generateItemSlots(mealType) {
            const grid = document.getElementById('itemsGrid');
            grid.innerHTML = '';
            
            // Clear any existing hidden inputs for this meal type
            const form = document.getElementById('mealForm');
            const existingInputs = form.querySelectorAll('input[type="hidden"][name*="_item_"]');
            existingInputs.forEach(input => input.remove());
            
            const prefix = mealType.toLowerCase();
            
            // Create sections for Veg, Non-Veg, and Drinks
            const sections = [
                { title: '🥬 Vegetarian Items (Veg Only)', type: 'veg', slots: 3 },
                { title: '🍖 Non-Vegetarian Items (Veg + Non-Veg)', type: 'nonveg', slots: 3 },
                { title: '🥤 Drinks (Universal)', type: 'drinks', slots: 2 }
            ];
            
            sections.forEach(section => {
                // Create section header
                const sectionHeader = document.createElement('div');
                sectionHeader.className = 'meal-section-header';
                sectionHeader.innerHTML = `<h4>${section.title}</h4>`;
                grid.appendChild(sectionHeader);
                
                // Create section container
                const sectionContainer = document.createElement('div');
                sectionContainer.className = 'meal-section-slots';
                
                for (let i = 1; i <= section.slots; i++) {
                    const slot = document.createElement('div');
                    slot.className = 'item-slot';
                    slot.onclick = () => openItemModal(i, mealType, section.type);
                    
                    const slotLabel = section.type === 'drinks' ? 'drinks' : `${section.type} item ${i}`;
                    
                    slot.innerHTML = `
                        <div class="empty-slot">Click to select ${slotLabel}</div>
                    `;
                    
                    // Create hidden input
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = `${prefix}_${section.type}_item_${i}`;
                    hiddenInput.id = `${prefix}_${section.type}_item_${i}`;
                    hiddenInput.value = '';
                    
                    document.getElementById('mealForm').appendChild(hiddenInput);
                    console.log('Created hidden input:', hiddenInput.name, 'with ID:', hiddenInput.id);
                    
                    sectionContainer.appendChild(slot);
                }
                
                grid.appendChild(sectionContainer);
            });
        }

        function loadExistingMeal(date, mealType) {
            const meal = weeklyMeals[date] && weeklyMeals[date][mealType];
            
            if (meal) {
                // Load the correct chef based on meal type
                const chefSelect = document.getElementById('chefSelect');
                let chefId = '';
                
                if (mealType === 'Breakfast') {
                    chefId = meal.breakfast_chef_id || '';
                } else if (mealType === 'Lunch') {
                    chefId = meal.lunch_chef_id || '';
                } else if (mealType === 'Dinner') {
                    chefId = meal.dinner_chef_id || '';
                }
                
                console.log('Loading chef for', mealType, ':', chefId);
                
                if (chefSelect) {
                    chefSelect.value = chefId;
                }
                
                currentMealData = meal;
                
                console.log('Loading meal data for', date, mealType, ':', meal);
                
                const prefix = mealType.toLowerCase();
                
                // First try to load from new sectioned structure
                const sections = ['veg', 'nonveg', 'drinks'];
                const section_slots = {'veg': 3, 'nonveg': 3, 'drinks': 2};
                let hasNewStructureData = false;
                
                sections.forEach(section => {
                    for (let i = 1; i <= section_slots[section]; i++) {
                        const fieldName = `${prefix}_${section}_item_${i}`;
                        const itemId = currentMealData[fieldName];
                        if (itemId) {
                            hasNewStructureData = true;
                            const item = allMealItems.find(it => it.id == itemId);
                            if (item) {
                                // Set currentSlot to match the section structure
                                currentSlot = { number: i, type: mealType, section: section };
                                setSlotItem(i, mealType, item.id, item.item_name, item.price);
                                console.log('Loaded existing item:', item.item_name, 'in section:', section, 'slot:', i);
                            }
                        }
                    }
                });
                
                // If no new structure data, load from old structure and distribute items
                if (!hasNewStructureData) {
                    console.log('Loading from old structure for', mealType);
                    const maxItems = mealType === 'Breakfast' ? 4 : 6;
                    let vegSlot = 1, nonvegSlot = 1, drinksSlot = 1;
                    
                    for (let i = 1; i <= maxItems; i++) {
                        const oldFieldName = `${prefix}_item_${i}`;
                        const itemId = currentMealData[oldFieldName];
                        if (itemId) {
                            const item = allMealItems.find(it => it.id == itemId);
                            if (item) {
                                let targetSection, targetSlot;
                                
                                // Determine which section this item should go to
                                if (item.meal_section === 'drinks') {
                                    targetSection = 'drinks';
                                    targetSlot = drinksSlot++;
                                } else if (item.item_type === 'veg') {
                                    targetSection = 'veg';
                                    targetSlot = vegSlot++;
                                } else {
                                    targetSection = 'nonveg';
                                    targetSlot = nonvegSlot++;
                                }
                                
                                // Only add if we have available slots
                                const maxSlots = targetSection === 'drinks' ? 2 : 3;
                                if (targetSlot <= maxSlots) {
                                    currentSlot = { number: targetSlot, type: mealType, section: targetSection };
                                    setSlotItem(targetSlot, mealType, item.id, item.item_name, item.price);
                                    console.log('Distributed old item:', item.item_name, 'to section:', targetSection, 'slot:', targetSlot);
                                }
                            }
                        }
                    }
                }
            } else {
                const chefSelect = document.getElementById('chefSelect');
                if (chefSelect) {
                    chefSelect.value = '';
                }
                currentMealData = null;
            }
        }

        function openItemModal(slotNumber, mealType, sectionType) {
            currentSlot = { number: slotNumber, type: mealType, section: sectionType };
            
            // Update modal title based on section type
            let modalTitle = '';
            if (sectionType === 'drinks') {
                modalTitle = 'Select Drinks';
            } else if (sectionType === 'veg') {
                modalTitle = `Select Vegetarian ${mealType} Item`;
            } else if (sectionType === 'nonveg') {
                modalTitle = `Select Non-Vegetarian ${mealType} Item`;
            }
            
            document.getElementById('itemModalTitle').textContent = modalTitle;
            
            // Filter and populate items
            populateItemSelection(mealType, sectionType);
            
            document.getElementById('itemModal').style.display = 'block';
        }

        function populateItemSelection(mealType, sectionType) {
            const grid = document.getElementById('itemsSelectionGrid');
            grid.innerHTML = '';
            
            // Filter items based on meal type and section type
            const filteredItems = allMealItems.filter(item => {
                // First filter by meal section
                const mealTypeLower = mealType.toLowerCase();
                const matchesMealSection = item.meal_section === mealTypeLower || 
                                         item.meal_section === 'all' ||
                                         (mealTypeLower === 'lunch' && item.meal_section === 'lunch_dinner') ||
                                         (mealTypeLower === 'dinner' && item.meal_section === 'lunch_dinner') ||
                                         (sectionType === 'drinks' && item.meal_section === 'drinks');
                
                if (!matchesMealSection) return false;
                
                // Then filter by dietary type
                if (sectionType === 'drinks') {
                    return item.meal_section === 'drinks';
                } else if (sectionType === 'veg') {
                    return item.item_type === 'veg';
                } else if (sectionType === 'nonveg') {
                    // Non-veg people can eat both veg and non-veg items
                    return item.item_type === 'veg' || item.item_type === 'non_veg';
                }
                
                return true;
            });
            
            // Create item selection cards
            filteredItems.forEach(item => {
                const itemCard = document.createElement('div');
                itemCard.className = 'item-slot filled';
                itemCard.onclick = () => selectItem(item.id, item.item_name, item.price);
                itemCard.innerHTML = `
                    <div class="item-name">${item.item_name}</div>
                    <div class="item-category">${item.category}</div>
                    <div class="item-type ${item.item_type === 'veg' ? 'type-veg' : 'type-nonveg'}">${item.item_type === 'veg' ? '🥬 Veg' : '🍖 Non-Veg'}</div>
                `;
                grid.appendChild(itemCard);
            });
            
            if (filteredItems.length === 0) {
                grid.innerHTML = '<div class="no-items">No items available for this section</div>';
            }
        }

        function selectItem(itemId, itemName, price) {
            if (currentSlot) {
                setSlotItem(currentSlot.number, currentSlot.type, itemId, itemName, price);
                closeItemModal();
            }
        }

        function setSlotItem(slotNumber, mealType, itemId, itemName, price) {
            if (!currentSlot) return;
            
            const prefix = mealType.toLowerCase();
            const sectionType = currentSlot.section;
            
            // Find the correct slot element based on section and slot number
            const sections = document.querySelectorAll('#itemsGrid .meal-section-slots');
            console.log('Available sections:', sections.length);
            
            let targetSection;
            if (sectionType === 'veg') targetSection = sections[0];
            else if (sectionType === 'nonveg') targetSection = sections[1];
            else if (sectionType === 'drinks') targetSection = sections[2];
            
            console.log('Target section for', sectionType, ':', targetSection);
            
            if (!targetSection) {
                console.error('No target section found for', sectionType);
                return;
            }
            
            const slotElement = targetSection.querySelector(`.item-slot:nth-child(${slotNumber})`);
            const hiddenInput = document.getElementById(`${prefix}_${sectionType}_item_${slotNumber}`);
            
            console.log('Setting slot item:', { slotNumber, mealType, sectionType, itemId, itemName });
            console.log('Slot element found:', slotElement);
            console.log('Hidden input found:', hiddenInput);
            
            if (slotElement && hiddenInput) {
                // Find the item details to show dietary type
                const item = allMealItems.find(it => it.id == itemId);
                const dietaryType = item ? (item.item_type === 'veg' ? '🥬' : '🍖') : '';
                
                slotElement.className = 'item-slot filled';
                slotElement.innerHTML = `
                    <div class="item-name">${itemName} ${dietaryType}</div>
                    <div class="item-selected-label">✓ Selected</div>
                `;
                hiddenInput.value = itemId;
                console.log('Hidden input value set to:', hiddenInput.value);
            } else {
                console.error('Could not find slot element or hidden input');
            }
        }

        function clearCurrentSlot() {
            if (currentSlot) {
                const prefix = currentSlot.type.toLowerCase();
                const sectionType = currentSlot.section;
                
                // Find the correct slot element based on section and slot number
                const sections = document.querySelectorAll('#itemsGrid .meal-section-slots');
                let targetSection;
                
                if (sectionType === 'veg') targetSection = sections[0];
                else if (sectionType === 'nonveg') targetSection = sections[1];
                else if (sectionType === 'drinks') targetSection = sections[2];
                
                if (targetSection) {
                    const slotElement = targetSection.querySelector(`.item-slot:nth-child(${currentSlot.number})`);
                    const hiddenInput = document.getElementById(`${prefix}_${sectionType}_item_${currentSlot.number}`);
                    
                    if (slotElement) {
                        slotElement.className = 'item-slot';
                        const slotLabel = sectionType === 'drinks' ? 'drinks' : `${sectionType} item ${currentSlot.number}`;
                        slotElement.innerHTML = `<div class="empty-slot">Click to select ${slotLabel}</div>`;
                    }
                    
                    if (hiddenInput) {
                        hiddenInput.value = '';
                    }
                }
                
                closeItemModal();
            }
        }

        function closeModal() {
            document.getElementById('mealModal').style.display = 'none';
        }

        // Ensure DOM is ready and log element availability
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, checking elements...');
            console.log('modalTitle element:', document.getElementById('modalTitle'));
            console.log('mealDate element:', document.getElementById('mealDate'));
            console.log('mealType element:', document.getElementById('mealType'));
            console.log('mealModal element:', document.getElementById('mealModal'));
            document.getElementById('mealForm').addEventListener('submit', function(e) {
                console.log('Form submitting...');
                const formData = new FormData(this);
                for (let [key, value] of formData.entries()) {
                    console.log(key + ': ' + value);
                }
            });

            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.popup-alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    if (alert) {
                        alert.style.animation = 'slideOutRight 0.3s ease-in forwards';
                        setTimeout(() => alert.remove(), 300);
                    }
                }, 5000);
            });
        });

        function closeAlert(alertId) {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.style.animation = 'slideOutRight 0.3s ease-in forwards';
                setTimeout(() => alert.remove(), 300);
            }
        }

        function closeItemModal() {
            document.getElementById('itemModal').style.display = 'none';
            currentSlot = null;
        }

        function deleteMeal(mealId) {
            if (confirm('Are you sure you want to delete this meal?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_meal';
                form.appendChild(actionInput);
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'meal_id';
                idInput.value = mealId;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const mealModal = document.getElementById('mealModal');
            const itemModal = document.getElementById('itemModal');
            
            if (event.target === mealModal) {
                closeModal();
            }
            if (event.target === itemModal) {
                closeItemModal();
            }
        }
    </script>
</body>
</html>
