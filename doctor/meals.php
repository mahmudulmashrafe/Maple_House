<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') {
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
            overflow-x: hidden;
        }
        
        .container {
            min-height: 100vh;
            padding: 20px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: white;
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
            text-decoration: none;
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
        
        .empty-meal {
            color: #95a5a6;
            font-style: italic;
            text-align: center;
            padding: 20px 10px;
            font-size: 0.85rem;
        }
        
        .week-info h2 {
            margin: 0;
            color: white;
        }
        
        .week-info small {
            color: rgba(255,255,255,0.8);
            font-size: 0.8rem;
        }
        
        .meal-actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 15px 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .week-stats {
            display: flex;
            gap: 20px;
        }
        
        .week-stats .stat {
            color: white;
            font-size: 0.9rem;
            font-weight: 500;
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
                <a href="?week=<?= date('Y-m-d', strtotime($week_start . ' -7 days')) ?>" class="nav-btn">
                    <i class="fas fa-chevron-left"></i> Previous Week
                </a>
                <div class="week-info">
                    <h2>Week of <?= date('M j', strtotime($week_start)) ?> - <?= date('M j, Y', strtotime($week_end)) ?></h2>
                    <small>(Saturday to Friday)</small>
                </div>
                <a href="?week=<?= date('Y-m-d', strtotime($week_start . ' +7 days')) ?>" class="nav-btn">
                    Next Week <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            
            <div class="meal-actions-bar">
                <div style="color: white; font-weight: 500;">
                    <i class="fas fa-info-circle"></i> Read-only view - Contact admin to modify meal plans
                </div>
                <div class="week-stats">
                    <?php
                    $total_meals = count($meals);
                    ?>
                    <span class="stat">📊 <?= $total_meals ?> meals planned</span>
                    <span class="stat">🍽️ Weekly meal planning</span>
                </div>
            </div>

        </div>

        <!-- Meal Content -->
        <div class="meal-grid">
            <?php foreach ($weekly_meals as $date => $day_meals): ?>
                <div class="day-column">
                    <div class="day-header">
                        <?= date('l', strtotime($date)) ?>
                        <div class="day-date"><?= date('M j', strtotime($date)) ?></div>
                    </div>
                    
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
                            <?php else: ?>
                                <div class="empty-meal">No meal planned</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
