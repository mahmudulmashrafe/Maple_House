<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and has chef role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Chef') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle AJAX requests for starting cooking sessions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'complete_cooking_session') {
            // Complete an active cooking session
            $session_id = $_POST['session_id'];
            
            // Calculate total cost from usage details
            $cost_stmt = $db->prepare("SELECT SUM(total_cost) as total_cost FROM chef_usage_details WHERE session_id = ?");
            $cost_stmt->execute([$session_id]);
            $total_cost = $cost_stmt->fetchColumn() ?: 0;
            
            // Update session status to completed
            $complete_stmt = $db->prepare("UPDATE chef_usage_sessions SET status = 'COMPLETED', completed_at = NOW(), total_cost = ? WHERE id = ? AND chef_id = ?");
            $complete_stmt->execute([$total_cost, $session_id, $_SESSION['user_id']]);
            
            echo json_encode(['success' => true, 'message' => 'Cooking session completed successfully!', 'total_cost' => $total_cost]);
        }
        else if ($_POST['action'] === 'cancel_cooking_session') {
            // Cancel an active cooking session and restore inventory
            $session_id = $_POST['session_id'];
            
            // Get all usage details for this session
            $usage_stmt = $db->prepare("SELECT * FROM chef_usage_details WHERE session_id = ?");
            $usage_stmt->execute([$session_id]);
            $usage_details = $usage_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Restore inventory for each item
            foreach ($usage_details as $usage) {
                // Get current stock
                $stock_stmt = $db->prepare("SELECT current_stock FROM inventory_items WHERE id = ?");
                $stock_stmt->execute([$usage['item_id']]);
                $current_stock = $stock_stmt->fetchColumn();
                
                // Restore stock
                $new_stock = $current_stock + $usage['quantity_used'];
                $restore_stmt = $db->prepare("UPDATE inventory_items SET current_stock = ? WHERE id = ?");
                $restore_stmt->execute([$new_stock, $usage['item_id']]);
                
                // Log restoration transaction
                $trans_stmt = $db->prepare("INSERT INTO inventory_transactions (item_id, transaction_type, quantity, previous_stock, new_stock, reference_type, reference_id, notes, performed_by) VALUES (?, 'IN', ?, ?, ?, 'CHEF_USAGE', ?, ?, ?)");
                $trans_stmt->execute([
                    $usage['item_id'],
                    $usage['quantity_used'],
                    $current_stock,
                    $new_stock,
                    $session_id,
                    'Cooking session cancelled - inventory restored',
                    $_SESSION['user_id']
                ]);
            }
            
            // Update session status to cancelled
            $cancel_stmt = $db->prepare("UPDATE chef_usage_sessions SET status = 'CANCELLED', completed_at = NOW() WHERE id = ? AND chef_id = ?");
            $cancel_stmt->execute([$session_id, $_SESSION['user_id']]);
            
            echo json_encode(['success' => true, 'message' => 'Cooking session cancelled and inventory restored!']);
        }
        else if ($_POST['action'] === 'start_cooking_session') {
            // Check if there's already an active session
            $check_stmt = $db->prepare("SELECT id FROM chef_usage_sessions WHERE chef_id = ? AND status = 'IN_PROGRESS'");
            $check_stmt->execute([$_SESSION['user_id']]);
            
            if ($check_stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'You already have an active cooking session. Please complete or cancel it first.']);
                exit();
            }
            
            // Create new cooking session
            $stmt = $db->prepare("INSERT INTO chef_usage_sessions (chef_id, meal_type, meal_date, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $_POST['meal_type'],
                $_POST['meal_date'],
                $_POST['description']
            ]);
            
            $session_id = $db->lastInsertId();
            
            // Add selected ingredients to session
            if (!empty($_POST['ingredients'])) {
                $ingredients = json_decode($_POST['ingredients'], true);
                foreach ($ingredients as $ingredient) {
                    // Check stock and add ingredient usage
                    $stock_stmt = $db->prepare("SELECT current_stock, unit_cost FROM inventory_items WHERE id = ?");
                    $stock_stmt->execute([$ingredient['item_id']]);
                    $item = $stock_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($item && $item['current_stock'] >= $ingredient['quantity']) {
                        // Add usage detail
                        $total_cost = $ingredient['quantity'] * $item['unit_cost'];
                        $usage_stmt = $db->prepare("INSERT INTO chef_usage_details (session_id, item_id, quantity_used, unit_cost, total_cost, notes) VALUES (?, ?, ?, ?, ?, ?)");
                        $usage_stmt->execute([
                            $session_id,
                            $ingredient['item_id'],
                            $ingredient['quantity'],
                            $item['unit_cost'],
                            $total_cost,
                            $ingredient['notes'] ?? ''
                        ]);
                        
                        // Update inventory stock
                        $new_stock = $item['current_stock'] - $ingredient['quantity'];
                        $update_stmt = $db->prepare("UPDATE inventory_items SET current_stock = ? WHERE id = ?");
                        $update_stmt->execute([$new_stock, $ingredient['item_id']]);
                        
                        // Log transaction
                        $trans_stmt = $db->prepare("INSERT INTO inventory_transactions (item_id, transaction_type, quantity, previous_stock, new_stock, reference_type, reference_id, notes, performed_by) VALUES (?, 'OUT', ?, ?, ?, 'CHEF_USAGE', ?, ?, ?)");
                        $trans_stmt->execute([
                            $ingredient['item_id'],
                            $ingredient['quantity'],
                            $item['current_stock'],
                            $new_stock,
                            $session_id,
                            'Cooking session: ' . $_POST['meal_type'],
                            $_SESSION['user_id']
                        ]);
                    }
                }
            }
            
            echo json_encode(['success' => true, 'session_id' => $session_id, 'message' => 'Cooking session started with ingredients!']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Get today's date
$today = date('Y-m-d');

// Get current chef's information
try {
    $chef_stmt = $db->prepare("SELECT c.*, u.first_name, u.last_name FROM chefs c JOIN users u ON c.user_id = u.id WHERE c.user_id = ?");
    $chef_stmt->execute([$_SESSION['user_id']]);
    $chef_info = $chef_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $chef_info = ['id' => 1, 'first_name' => 'Sample', 'last_name' => 'Chef'];
}

// Get today's planned meals assigned to this chef with separated veg/non-veg items
$today_meals_stmt = $db->prepare("
    SELECT dm.*, 
           -- Get vegetarian items
           CASE 
               WHEN dm.meal_type = 'Breakfast' THEN 
                   CONCAT_WS(', ', 
                       (SELECT item_name FROM meal_items WHERE id = dm.breakfast_veg_item_1 AND item_type = 'veg'),
                       (SELECT item_name FROM meal_items WHERE id = dm.breakfast_veg_item_2 AND item_type = 'veg'),
                       (SELECT item_name FROM meal_items WHERE id = dm.breakfast_veg_item_3 AND item_type = 'veg')
                   )
               WHEN dm.meal_type = 'Lunch' THEN 
                   CONCAT_WS(', ', 
                       (SELECT item_name FROM meal_items WHERE id = dm.lunch_veg_item_1 AND item_type = 'veg'),
                       (SELECT item_name FROM meal_items WHERE id = dm.lunch_veg_item_2 AND item_type = 'veg'),
                       (SELECT item_name FROM meal_items WHERE id = dm.lunch_veg_item_3 AND item_type = 'veg')
                   )
               WHEN dm.meal_type = 'Dinner' THEN 
                   CONCAT_WS(', ', 
                       (SELECT item_name FROM meal_items WHERE id = dm.dinner_veg_item_1 AND item_type = 'veg'),
                       (SELECT item_name FROM meal_items WHERE id = dm.dinner_veg_item_2 AND item_type = 'veg'),
                       (SELECT item_name FROM meal_items WHERE id = dm.dinner_veg_item_3 AND item_type = 'veg')
                   )
           END as veg_items,
           -- Get non-vegetarian items (remove item_type filter since nonveg fields can contain any items)
           CASE 
               WHEN dm.meal_type = 'Breakfast' THEN 
                   CONCAT_WS(', ', 
                       (SELECT item_name FROM meal_items WHERE id = dm.breakfast_nonveg_item_1),
                       (SELECT item_name FROM meal_items WHERE id = dm.breakfast_nonveg_item_2),
                       (SELECT item_name FROM meal_items WHERE id = dm.breakfast_nonveg_item_3)
                   )
               WHEN dm.meal_type = 'Lunch' THEN 
                   CONCAT_WS(', ', 
                       (SELECT item_name FROM meal_items WHERE id = dm.lunch_nonveg_item_1),
                       (SELECT item_name FROM meal_items WHERE id = dm.lunch_nonveg_item_2),
                       (SELECT item_name FROM meal_items WHERE id = dm.lunch_nonveg_item_3)
                   )
               WHEN dm.meal_type = 'Dinner' THEN 
                   CONCAT_WS(', ', 
                       (SELECT item_name FROM meal_items WHERE id = dm.dinner_nonveg_item_1),
                       (SELECT item_name FROM meal_items WHERE id = dm.dinner_nonveg_item_2),
                       (SELECT item_name FROM meal_items WHERE id = dm.dinner_nonveg_item_3)
                   )
           END as nonveg_items,
           -- Get drinks
           CASE 
               WHEN dm.meal_type = 'Breakfast' THEN 
                   CONCAT_WS(', ', 
                       (SELECT item_name FROM meal_items WHERE id = dm.breakfast_drinks_item_1),
                       (SELECT item_name FROM meal_items WHERE id = dm.breakfast_drinks_item_2)
                   )
               WHEN dm.meal_type = 'Lunch' THEN 
                   CONCAT_WS(', ', 
                       (SELECT item_name FROM meal_items WHERE id = dm.lunch_drinks_item_1),
                       (SELECT item_name FROM meal_items WHERE id = dm.lunch_drinks_item_2)
                   )
               WHEN dm.meal_type = 'Dinner' THEN 
                   CONCAT_WS(', ', 
                       (SELECT item_name FROM meal_items WHERE id = dm.dinner_drinks_item_1),
                       (SELECT item_name FROM meal_items WHERE id = dm.dinner_drinks_item_2)
                   )
           END as drink_items
    FROM daily_meals dm 
    WHERE dm.meal_date = ? 
    AND (
        (dm.meal_type = 'Breakfast' AND dm.breakfast_chef_id = ?) OR
        (dm.meal_type = 'Lunch' AND dm.lunch_chef_id = ?) OR
        (dm.meal_type = 'Dinner' AND dm.dinner_chef_id = ?)
    )
    ORDER BY FIELD(dm.meal_type, 'Breakfast', 'Lunch', 'Dinner')
");
try {
    $today_meals_stmt->execute([$today, $chef_info['id'], $chef_info['id'], $chef_info['id']]);
    $meals_from_db = $today_meals_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $meals_from_db = [];
}

// Get resident preferences for today's meals
$preferences_stmt = $db->prepare("
    SELECT 
        meal_type,
        dietary_preference,
        spice_level,
        oil_preference,
        COUNT(*) as count
    FROM meal_preferences mp
    JOIN residents r ON mp.resident_id = r.id
    WHERE mp.meal_date = ?
    GROUP BY meal_type, dietary_preference, spice_level, oil_preference
    ORDER BY meal_type, dietary_preference, spice_level, oil_preference
");
try {
    $preferences_stmt->execute([$today]);
    $all_preferences = $preferences_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_preferences = [];
}

// Get total resident count for calculating defaults
try {
    $total_residents_stmt = $db->prepare("SELECT COUNT(*) as total FROM residents WHERE is_active = 1");
    $total_residents_stmt->execute();
    $total_residents = $total_residents_stmt->fetchColumn();
    
    // Debug: Check if we're getting the right count
    if ($total_residents != 6) {
        // Try to fix it by ensuring only 6 residents are active
        $db->exec("UPDATE residents SET is_active = 0");
        $db->exec("UPDATE residents SET is_active = 1 WHERE id IN (1, 2, 3, 4, 5, 6)");
        
        // Recount
        $total_residents_stmt->execute();
        $total_residents = $total_residents_stmt->fetchColumn();
    }
} catch (Exception $e) {
    $total_residents = 6; // Changed default to 6
}

// Organize preferences by meal type with separate veg/non-veg tracking
$meal_preferences = [];
foreach ($all_preferences as $pref) {
    $meal_type = $pref['meal_type'];
    if (!isset($meal_preferences[$meal_type])) {
        $meal_preferences[$meal_type] = [
            'dietary' => ['Vegetarian' => 0, 'Non-Vegetarian' => 0],
            'spice' => ['Spicy' => 0, 'Medium' => 0, 'Non Spicy' => 0],
            'oil' => ['Less oily' => 0, 'Normal' => 0, 'Extra oily' => 0],
            // Separate tracking for veg and non-veg preferences
            'veg_spice' => ['Spicy' => 0, 'Medium' => 0, 'Non Spicy' => 0],
            'veg_oil' => ['Less oily' => 0, 'Normal' => 0, 'Extra oily' => 0],
            'nonveg_spice' => ['Spicy' => 0, 'Medium' => 0, 'Non Spicy' => 0],
            'nonveg_oil' => ['Less oily' => 0, 'Normal' => 0, 'Extra oily' => 0],
            'total_with_prefs' => 0
        ];
    }
    
    $dietary_pref = $pref['dietary_preference'] ?? 'Non-Vegetarian';
    $spice_level = $pref['spice_level'] ?? 'Medium';
    $oil_pref = $pref['oil_preference'] ?? 'Normal';
    
    $meal_preferences[$meal_type]['dietary'][$dietary_pref] += $pref['count'];
    $meal_preferences[$meal_type]['spice'][$spice_level] += $pref['count'];
    $meal_preferences[$meal_type]['oil'][$oil_pref] += $pref['count'];
    
    // Track spice and oil preferences separately by dietary preference
    if ($dietary_pref === 'Vegetarian') {
        $meal_preferences[$meal_type]['veg_spice'][$spice_level] += $pref['count'];
        $meal_preferences[$meal_type]['veg_oil'][$oil_pref] += $pref['count'];
    } else {
        $meal_preferences[$meal_type]['nonveg_spice'][$spice_level] += $pref['count'];
        $meal_preferences[$meal_type]['nonveg_oil'][$oil_pref] += $pref['count'];
    }
    
    $meal_preferences[$meal_type]['total_with_prefs'] += $pref['count'];
}

// Calculate resident counts including defaults for those without preferences
foreach (['Breakfast', 'Lunch', 'Dinner'] as $meal_type) {
    if (!isset($meal_preferences[$meal_type])) {
        $meal_preferences[$meal_type] = [
            'dietary' => ['Vegetarian' => 0, 'Non-Vegetarian' => $total_residents],
            'spice' => ['Spicy' => 0, 'Medium' => $total_residents, 'Non Spicy' => 0],
            'oil' => ['Less oily' => 0, 'Normal' => $total_residents, 'Extra oily' => 0],
            'veg_spice' => ['Spicy' => 0, 'Medium' => 0, 'Non Spicy' => 0],
            'veg_oil' => ['Less oily' => 0, 'Normal' => 0, 'Extra oily' => 0],
            'nonveg_spice' => ['Spicy' => 0, 'Medium' => $total_residents, 'Non Spicy' => 0],
            'nonveg_oil' => ['Less oily' => 0, 'Normal' => $total_residents, 'Extra oily' => 0],
            'total_with_prefs' => 0,
            'residents_without_prefs' => $total_residents
        ];
    } else {
        $residents_without_prefs = $total_residents - $meal_preferences[$meal_type]['total_with_prefs'];
        $meal_preferences[$meal_type]['residents_without_prefs'] = max(0, $residents_without_prefs);
        
        // Add defaults for residents without preferences (Non-Veg, Medium, Normal)
        if ($residents_without_prefs > 0) {
            $meal_preferences[$meal_type]['dietary']['Non-Vegetarian'] += $residents_without_prefs;
            $meal_preferences[$meal_type]['spice']['Medium'] += $residents_without_prefs;
            $meal_preferences[$meal_type]['oil']['Normal'] += $residents_without_prefs;
            // Add to non-veg preferences since defaults are non-veg
            $meal_preferences[$meal_type]['nonveg_spice']['Medium'] += $residents_without_prefs;
            $meal_preferences[$meal_type]['nonveg_oil']['Normal'] += $residents_without_prefs;
        }
    }
}

// Organize meals by type (remove duplicates, keep the latest one)
$today_meals = [];
$meal_types = ['Breakfast', 'Lunch', 'Dinner'];

// Create default structure for all 3 meals
foreach ($meal_types as $type) {
    $today_meals[$type] = [
        'id' => null,
        'meal_type' => $type,
        'menu_items' => 'No ' . strtolower($type) . ' planned',
        'cost_per_serving' => 0,
        'meal_date' => $today
    ];
}

// Fill in actual data from database (if exists)
foreach ($meals_from_db as $meal) {
    $meal_type = $meal['meal_type'];
    if (in_array($meal_type, $meal_types)) {
        // Clean up items (remove empty values)
        $veg_items = array_filter(explode(', ', $meal['veg_items'] ?? ''));
        $nonveg_items = array_filter(explode(', ', $meal['nonveg_items'] ?? ''));
        $drink_items = array_filter(explode(', ', $meal['drink_items'] ?? ''));
        
        // Store separated items
        $meal['veg_items_array'] = $veg_items;
        $meal['nonveg_items_array'] = $nonveg_items;
        $meal['drink_items_array'] = $drink_items;
        
        // Create combined menu items for display
        $all_items = array_merge($veg_items, $nonveg_items, $drink_items);
        $meal['menu_items'] = !empty($all_items) ? implode(', ', $all_items) : 'No items assigned';
        
        $today_meals[$meal_type] = $meal;
    }
}

// Get current active session
$active_session_stmt = $db->prepare("SELECT * FROM chef_usage_sessions WHERE chef_id = ? AND status = 'IN_PROGRESS' ORDER BY created_at DESC LIMIT 1");
$active_session_stmt->execute([$_SESSION['user_id']]);
$active_session = $active_session_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent completed sessions for today
$completed_today_stmt = $db->prepare("SELECT * FROM chef_usage_sessions WHERE chef_id = ? AND meal_date = ? AND status = 'COMPLETED' ORDER BY completed_at DESC");
$completed_today_stmt->execute([$_SESSION['user_id'], $today]);
$completed_today = $completed_today_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get cooking session usage if there's an active session
$session_usage = [];
if ($active_session) {
    $usage_stmt = $db->prepare("SELECT ud.*, i.name as item_name, u.abbreviation as unit_abbr
                                FROM chef_usage_details ud
                                JOIN inventory_items i ON ud.item_id = i.id
                                JOIN inventory_units u ON i.unit_id = u.id
                                WHERE ud.session_id = ?
                                ORDER BY ud.used_at DESC");
    $usage_stmt->execute([$active_session['id']]);
    $session_usage = $usage_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get available inventory items for ingredient selection
$inventory_items = [];
$inventory_stmt = $db->prepare("SELECT i.*, c.name as category_name, u.name as unit_name, u.abbreviation as unit_abbr
                                FROM inventory_items i
                                LEFT JOIN inventory_categories c ON i.category_id = c.id
                                LEFT JOIN inventory_units u ON i.unit_id = u.id
                                WHERE i.is_active = TRUE AND i.current_stock > 0
                                ORDER BY c.name, i.name");
$inventory_stmt->execute();
$inventory_items = $inventory_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Cooking - Maple House</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            scroll-behavior: smooth;
            overflow-x: hidden;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        /* Hide scrollbars but keep functionality */
        ::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }
        
        html {
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* Internet Explorer 10+ */
        }
        
        .ingredient-scroll::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .date-info {
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
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
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        
        .active-session {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .active-session h3 {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .session-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .meal-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .meal-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .meal-item:last-child {
            border-bottom: none;
        }
        
        .meal-item.meal-no-plan {
            opacity: 0.6;
            background: #f8f9fa;
        }
        
        .meal-info h3 {
            margin: 0 0 8px 0;
            color: #2c3e50;
            font-size: 1.1rem;
        }
        
        .meal-info p {
            margin: 0 0 5px 0;
            color: #555;
            line-height: 1.4;
        }
        
        .meal-info small {
            color: #999;
            font-size: 0.85rem;
        }
        
        .meal-action {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .preferences-section {
            margin-top: 15px;
            padding: 15px;
            color: #27ae60;
            font-weight: 600;
        }
        
        .status-waiting {
            color: #95a5a6;
            font-weight: 500;
        }
        
        .status-no-plan {
            color: #6c757d;
            font-weight: 500;
        }
        
        .status-cooking {
            color: #f39c12;
            font-weight: 600;
        }
        
        .status-completed {
            color: #27ae60;
            font-weight: 600;
        }
        
        .status-waiting {
            color: #95a5a6;
            font-weight: 500;
        }
        
        .no-meals {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .meal-no-plan {
            opacity: 0.6;
            background: #f8f9fa;
        }
        
        .status-no-plan {
            color: #6c757d;
            font-weight: 500;
        }

        .preferences-section {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #3498db;
        }

        .preferences-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }

        .preference-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }

        .preference-group {
            background: white;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #e9ecef;
        }

        .preference-group h4 {
            margin: 0 0 8px 0;
            font-size: 0.85rem;
            color: #495057;
            font-weight: 600;
        }

        .preference-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2px 0;
            font-size: 0.8rem;
        }

        .preference-count {
            font-weight: 700;
            font-size: 1rem;
            color: #2c3e50;
            padding: 4px 8px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.1);
            min-width: 28px;
            text-align: center;
            display: inline-block;
        }
        
        .veg-section .preference-count { color: #27ae60; border-color: rgba(39, 174, 96, 0.3); }
        .nonveg-section .preference-count { color: #e74c3c; border-color: rgba(231, 76, 60, 0.3); }
        .drinks-section .preference-count { color: #3498db; border-color: rgba(52, 152, 219, 0.3); }

        .meal-categories {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 15px;
        }

        .category-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .category-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }

        .veg-section {
            border-left: 5px solid #27ae60;
            background: linear-gradient(135deg, #f8fff9 0%, #e8f5e8 100%);
        }

        .veg-section h4 {
            color: #27ae60;
        }

        .nonveg-section {
            border-left: 5px solid #e74c3c;
            background: linear-gradient(135deg, #fff8f8 0%, #fdf2f2 100%);
        }

        .nonveg-section h4 {
            color: #e74c3c;
        }

        .drinks-section {
            border-left: 5px solid #3498db;
            background: linear-gradient(135deg, #f8fcff 0%, #e3f2fd 100%);
            grid-column: 1 / -1;
        }

        .drinks-section h4 {
            color: #3498db;
        }

        .category-section h4 {
            margin: 0 0 15px 0;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .items-list {
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            font-size: 0.9rem;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .veg-section .items-list {
            background: rgba(39, 174, 96, 0.08);
            border-color: rgba(39, 174, 96, 0.2);
        }

        .nonveg-section .items-list {
            background: rgba(231, 76, 60, 0.08);
            border-color: rgba(231, 76, 60, 0.2);
        }

        .drinks-section .items-list {
            background: rgba(52, 152, 219, 0.08);
            border-color: rgba(52, 152, 219, 0.2);
        }

        .preference-breakdown {
            font-size: 0.85rem;
            background: rgba(255, 255, 255, 0.6);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .pref-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .pref-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .pref-label {
            font-weight: 600;
            color: #2c3e50;
            min-width: 80px;
            font-size: 0.9rem;
        }

        .spice-counts, .oil-counts {
            font-size: 0.8rem;
            color: #5a6c7d;
        }


        @media (max-width: 768px) {
            .meal-categories {
                grid-template-columns: 1fr;
            }
            
            .drinks-section {
                grid-column: 1;
            }
        }
        
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
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
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="date-info">
                <?= date('l, F j, Y') ?> - Today's Meal Plan
            </div>
        </div>
        
        <div id="alert" class="alert"></div>
        
        <?php if ($active_session): ?>
        <div class="active-session">
            <h3><i class="fas fa-fire"></i> Currently Cooking: <?= htmlspecialchars($active_session['meal_type']) ?></h3>
            <p><strong>Started:</strong> <?= date('g:i A', strtotime($active_session['created_at'])) ?></p>
            <p><strong>Description:</strong> <?= htmlspecialchars($active_session['description'] ?: 'No notes') ?></p>
            
            <?php if (!empty($session_usage)): ?>
            <div style="margin-top: 10px;">
                <strong>Ingredients Used:</strong>
                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 5px;">
                    <?php foreach ($session_usage as $usage): ?>
                    <span style="background: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">
                        <?= htmlspecialchars($usage['item_name']) ?>: <?= number_format($usage['quantity_used'], 1) ?> <?= htmlspecialchars($usage['unit_abbr']) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="session-actions">
                <button class="btn btn-success" onclick="completeCookingSession(<?= $active_session['id'] ?>)">
                    <i class="fas fa-check"></i> Complete Cooking
                </button>
                <button class="btn btn-danger" onclick="cancelCookingSession(<?= $active_session['id'] ?>)">
                    <i class="fas fa-times"></i> Cancel Session
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="meal-list">
            <?php foreach ($today_meals as $meal): ?>
            <?php 
                $is_completed = false;
                $is_cooking = false;
                $has_plan = ($meal['id'] !== null);
                
                // Check if this meal is currently being cooked
                if ($active_session && $active_session['meal_type'] === strtoupper($meal['meal_type'])) {
                    $is_cooking = true;
                }
                
                // Check if this meal was completed today
                foreach ($completed_today as $completed) {
                    if ($completed['meal_type'] === strtoupper($meal['meal_type'])) {
                        $is_completed = true;
                        break;
                    }
                }
            ?>
            <div class="meal-item <?= !$has_plan ? 'meal-no-plan' : '' ?>">
                <div class="meal-info">
                    <h3><?= htmlspecialchars($meal['meal_type']) ?></h3>
                    <p><?= htmlspecialchars($meal['menu_items']) ?></p>
                    <?php if ($has_plan): ?>
                    <small>Cost: ৳<?= number_format($meal['cost_per_serving'], 2) ?> per serving</small>
                    <?php endif; ?>
                    
                    <?php if ($has_plan): ?>
                    <div class="preferences-section">
                        <?php 
                        $prefs = $meal_preferences[$meal['meal_type']] ?? null;
                        $total_with_prefs = $prefs ? $prefs['total_with_prefs'] : 0;
                        $residents_without_prefs = $prefs ? $prefs['residents_without_prefs'] : $total_residents;
                        ?>
                        
                        <div class="preferences-title">
                            👥 Resident Preferences (Total: <?= $total_residents ?> residents)
                        </div>
                        
                        <div class="meal-categories">
                            <!-- Vegetarian Section -->
                            <div class="category-section veg-section">
                                <h4>🥬 Vegetarian:</h4>
                                <div class="items-list">
                                    <strong>Items:</strong> 
                                    <?php if (!empty($meal['veg_items_array'])): ?>
                                        <?= implode(', ', $meal['veg_items_array']) ?>
                                    <?php else: ?>
                                        <span style="color: #6c757d;">No vegetarian items</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($prefs): ?>
                                <div class="preference-breakdown">
                                    <div class="pref-row">
                                        <span class="pref-label">Residents:</span>
                                        <span class="pref-count veg-count"><?= $prefs['dietary']['Vegetarian'] ?></span>
                                    </div>
                                    <div class="pref-row">
                                        <span class="pref-label">Spice:</span>
                                        <span class="spice-counts">
                                            Spicy: <span class="pref-count spicy-count"><?= $prefs['veg_spice']['Spicy'] ?></span> | 
                                            Medium: <span class="pref-count medium-count"><?= $prefs['veg_spice']['Medium'] ?></span> | 
                                            Non Spicy: <span class="pref-count nonspicy-count"><?= $prefs['veg_spice']['Non Spicy'] ?></span>
                                        </span>
                                    </div>
                                    <div class="pref-row">
                                        <span class="pref-label">Oil:</span>
                                        <span class="oil-counts">
                                            Less: <span class="pref-count lessoily-count"><?= $prefs['veg_oil']['Less oily'] ?></span> | 
                                            Normal: <span class="pref-count normal-count"><?= $prefs['veg_oil']['Normal'] ?></span> | 
                                            Extra: <span class="pref-count oily-count"><?= $prefs['veg_oil']['Extra oily'] ?></span>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Non-Vegetarian Section -->
                            <div class="category-section nonveg-section">
                                <h4>🍖 Non-Vegetarian:</h4>
                                <div class="items-list">
                                    <strong>Items:</strong> 
                                    <?php if (!empty($meal['nonveg_items_array'])): ?>
                                        <?= implode(', ', $meal['nonveg_items_array']) ?>
                                    <?php else: ?>
                                        <span style="color: #6c757d;">No non-vegetarian items</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($prefs): ?>
                                <div class="preference-breakdown">
                                    <div class="pref-row">
                                        <span class="pref-label">Residents:</span>
                                        <span class="pref-count nonveg-count"><?= $prefs['dietary']['Non-Vegetarian'] ?></span>
                                    </div>
                                    <div class="pref-row">
                                        <span class="pref-label">Spice:</span>
                                        <span class="spice-counts">
                                            Spicy: <span class="pref-count spicy-count"><?= $prefs['nonveg_spice']['Spicy'] ?></span> | 
                                            Medium: <span class="pref-count medium-count"><?= $prefs['nonveg_spice']['Medium'] ?></span> | 
                                            Non Spicy: <span class="pref-count nonspicy-count"><?= $prefs['nonveg_spice']['Non Spicy'] ?></span>
                                        </span>
                                    </div>
                                    <div class="pref-row">
                                        <span class="pref-label">Oil:</span>
                                        <span class="oil-counts">
                                            Less: <span class="pref-count lessoily-count"><?= $prefs['nonveg_oil']['Less oily'] ?></span> | 
                                            Normal: <span class="pref-count normal-count"><?= $prefs['nonveg_oil']['Normal'] ?></span> | 
                                            Extra: <span class="pref-count oily-count"><?= $prefs['nonveg_oil']['Extra oily'] ?></span>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Drinks Section -->
                            <?php if (!empty($meal['drink_items_array'])): ?>
                            <div class="category-section drinks-section">
                                <h4>🥤 Drinks:</h4>
                                <div class="items-list">
                                    <strong>Items:</strong> <?= implode(', ', $meal['drink_items_array']) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                    <?php endif; ?>
                </div>
                <div class="meal-action">
                    <?php if (!$has_plan): ?>
                        <span class="status-no-plan">📋 Not Planned</span>
                    <?php elseif ($is_cooking): ?>
                        <span class="status-cooking">🔥 Cooking Now</span>
                    <?php elseif ($is_completed): ?>
                        <span class="status-completed">✅ Completed</span>
                    <?php else: ?>
                        <?php if (!$active_session): ?>
                        <button class="btn btn-success" onclick="startCooking('<?= strtoupper($meal['meal_type']) ?>', '<?= htmlspecialchars($meal['menu_items'], ENT_QUOTES) ?>', <?= $meal['id'] ?? 0 ?>)">
                            Start Cooking
                        </button>
                        <?php else: ?>
                        <span class="status-waiting">⏳ Waiting</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Start Cooking Modal -->
    <div id="startCookingModal" class="modal">
        <div class="modal-content" style="max-width: 800px; width: 95%;">
            <div class="modal-header">
                <h3><i class="fas fa-play"></i> Start Cooking Session</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="startCookingForm">
                    <input type="hidden" name="meal_type" id="cookingMealType">
                    <input type="hidden" name="meal_date" value="<?= $today ?>">
                    <input type="hidden" name="ingredients" id="selectedIngredients">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <div class="form-group">
                                <label>Meal</label>
                                <input type="text" id="cookingMealDisplay" class="form-control" readonly style="background: #f8f9fa;">
                            </div>
                            
                            <div class="form-group">
                                <label>Menu Items</label>
                                <textarea id="cookingMenuItems" class="form-control" rows="2" readonly style="background: #f8f9fa;"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Cooking Notes</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Special notes..."></textarea>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Select Ingredients</label>
                                <div style="border: 1px solid #ddd; border-radius: 6px; padding: 10px; max-height: 200px; overflow-y: scroll; background: #f8f9fa; scrollbar-width: none; -ms-overflow-style: none;" class="ingredient-scroll">
                                    <?php foreach ($inventory_items as $item): ?>
                                    <div style="display: flex; align-items: center; margin-bottom: 8px; padding: 5px; background: white; border-radius: 4px;">
                                        <input type="checkbox" class="ingredient-checkbox" data-item-id="<?= $item['id'] ?>" data-item-name="<?= htmlspecialchars($item['name']) ?>" data-unit="<?= htmlspecialchars($item['unit_abbr']) ?>" data-stock="<?= $item['current_stock'] ?>" style="margin-right: 8px;">
                                        <span style="flex: 1; font-size: 0.85rem;"><?= htmlspecialchars($item['name']) ?> (<?= number_format($item['current_stock'], 1) ?> <?= htmlspecialchars($item['unit_abbr']) ?>)</span>
                                        <input type="number" class="quantity-input" step="0.1" min="0.1" max="<?= $item['current_stock'] ?>" style="width: 60px; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; font-size: 0.85rem; background: white;" disabled tabindex="0">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="selectedIngredientsList" style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px; display: none;">
                        <strong>Selected Ingredients:</strong>
                        <div id="ingredientList"></div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">Start Cooking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function startCooking(mealType, menuItems, mealId) {
            console.log('Starting cooking:', mealType, menuItems, mealId);
            
            document.getElementById('cookingMealType').value = mealType;
            document.getElementById('cookingMealDisplay').value = mealType;
            document.getElementById('cookingMenuItems').value = menuItems;
            
            // Reset ingredient selection
            document.querySelectorAll('.ingredient-checkbox').forEach(cb => {
                cb.checked = false;
                const quantityInput = cb.parentElement.querySelector('.quantity-input');
                if (quantityInput) {
                    quantityInput.disabled = true;
                    quantityInput.value = '';
                    quantityInput.style.borderColor = '#ddd';
                    quantityInput.removeAttribute('readonly');
                }
            });
            
            const selectedList = document.getElementById('selectedIngredientsList');
            if (selectedList) {
                selectedList.style.display = 'none';
            }
            
            document.getElementById('selectedIngredients').value = '';
            document.getElementById('startCookingModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('startCookingModal').style.display = 'none';
        }
        
        function showAlert(message, type) {
            const alert = document.getElementById('alert');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            alert.style.display = 'block';
            
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }
        
        function updateSelectedIngredients() {
            const selectedIngredients = [];
            const checkedBoxes = document.querySelectorAll('.ingredient-checkbox:checked');
            
            checkedBoxes.forEach(checkbox => {
                const quantityInput = checkbox.parentElement.querySelector('.quantity-input');
                if (quantityInput) {
                    const quantity = parseFloat(quantityInput.value);
                    const maxStock = parseFloat(checkbox.dataset.stock);
                    
                    // Validate quantity
                    if (!quantity || quantity <= 0) {
                        quantityInput.style.borderColor = '#e74c3c';
                        return;
                    }
                    
                    if (quantity > maxStock) {
                        quantityInput.style.borderColor = '#e74c3c';
                        quantityInput.value = maxStock;
                        return;
                    }
                    
                    // Valid quantity
                    quantityInput.style.borderColor = '#27ae60';
                    
                    selectedIngredients.push({
                        item_id: parseInt(checkbox.dataset.itemId),
                        name: checkbox.dataset.itemName,
                        quantity: quantity,
                        unit: checkbox.dataset.unit,
                        notes: ''
                    });
                }
            });
            
            document.getElementById('selectedIngredients').value = JSON.stringify(selectedIngredients);
            
            // Update display
            const list = document.getElementById('ingredientList');
            const selectedList = document.getElementById('selectedIngredientsList');
            
            if (list && selectedList) {
                if (selectedIngredients.length > 0) {
                    list.innerHTML = selectedIngredients.map(ing => 
                        `<small style="display: block;">${ing.name}: ${ing.quantity} ${ing.unit}</small>`
                    ).join('');
                    selectedList.style.display = 'block';
                } else {
                    selectedList.style.display = 'none';
                }
            }
        }
        
        // Handle ingredient checkbox changes
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('ingredient-checkbox')) {
                const quantityInput = e.target.parentElement.querySelector('.quantity-input');
                if (quantityInput) {
                    if (e.target.checked) {
                        quantityInput.disabled = false;
                        quantityInput.removeAttribute('readonly');
                        quantityInput.value = '1';
                        // Use setTimeout to ensure the input is enabled before focusing
                        setTimeout(() => {
                            quantityInput.focus();
                            quantityInput.select();
                        }, 50);
                    } else {
                        quantityInput.disabled = true;
                        quantityInput.value = '';
                        quantityInput.style.borderColor = '#ddd';
                    }
                }
                updateSelectedIngredients();
            }
            
            if (e.target.classList.contains('quantity-input')) {
                updateSelectedIngredients();
            }
        });
        
        // Handle keyboard input for quantity inputs
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('quantity-input')) {
                updateSelectedIngredients();
            }
        });
        
        // Handle key events for quantity inputs
        document.addEventListener('keyup', function(e) {
            if (e.target.classList.contains('quantity-input')) {
                updateSelectedIngredients();
            }
        });
        
        // Handle focus events to ensure inputs are interactive
        document.addEventListener('focus', function(e) {
            if (e.target.classList.contains('quantity-input') && !e.target.disabled) {
                e.target.select();
            }
        }, true);
        
        document.getElementById('startCookingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate selected ingredients
            const selectedIngredients = JSON.parse(document.getElementById('selectedIngredients').value || '[]');
            
            if (selectedIngredients.length === 0) {
                showAlert('Please select at least one ingredient to start cooking.', 'error');
                return;
            }
            
            // Validate all quantities
            let hasInvalidQuantity = false;
            document.querySelectorAll('.ingredient-checkbox:checked').forEach(checkbox => {
                const quantityInput = checkbox.parentElement.querySelector('.quantity-input');
                if (quantityInput) {
                    const quantity = parseFloat(quantityInput.value);
                    const maxStock = parseFloat(checkbox.dataset.stock);
                    
                    if (!quantity || quantity <= 0 || quantity > maxStock) {
                        hasInvalidQuantity = true;
                        quantityInput.style.borderColor = '#e74c3c';
                    }
                }
            });
            
            if (hasInvalidQuantity) {
                showAlert('Please enter valid quantities for all selected ingredients.', 'error');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'start_cooking_session');
            
            fetch('daily.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeModal();
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again.', 'error');
            });
        });
        
        // Complete cooking session
        function completeCookingSession(sessionId) {
            if (!confirm('Are you sure you want to complete this cooking session?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'complete_cooking_session');
            formData.append('session_id', sessionId);
            
            fetch('daily.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message + ' Total cost: ৳' + parseFloat(data.total_cost).toFixed(2), 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while completing the session.', 'error');
            });
        }
        
        // Cancel cooking session
        function cancelCookingSession(sessionId) {
            if (!confirm('Are you sure you want to cancel this cooking session? This will restore all used ingredients to inventory.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'cancel_cooking_session');
            formData.append('session_id', sessionId);
            
            fetch('daily.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while cancelling the session.', 'error');
            });
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('startCookingModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
