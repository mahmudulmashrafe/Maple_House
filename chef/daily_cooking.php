<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Chef') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get chef ID
$chef_query = "SELECT id FROM chefs WHERE user_id = :user_id";
$chef_stmt = $db->prepare($chef_query);
$chef_stmt->bindParam(':user_id', $_SESSION['user_id']);
$chef_stmt->execute();
$chef = $chef_stmt->fetch(PDO::FETCH_ASSOC);

if (!$chef) {
    header("Location: ../login.php");
    exit();
}

$chef_id = $chef['id'];

// Handle cooking session updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['start_cooking'])) {
        $meal_date = $_POST['meal_date'];
        $meal_type = $_POST['meal_type'];
        
        // Create or update cooking session
        $session_query = "INSERT INTO chef_cooking_sessions (chef_id, meal_date, meal_type, cooking_status, started_at) 
                         VALUES (?, ?, ?, 'In Progress', NOW()) 
                         ON DUPLICATE KEY UPDATE cooking_status = 'In Progress', started_at = NOW()";
        $session_stmt = $db->prepare($session_query);
        $session_stmt->execute([$chef_id, $meal_date, $meal_type]);
        
        $success_message = "Cooking session started for $meal_type on " . date('M j, Y', strtotime($meal_date));
    }
    
    if (isset($_POST['complete_cooking'])) {
        $meal_date = $_POST['meal_date'];
        $meal_type = $_POST['meal_type'];
        
        $complete_query = "UPDATE chef_cooking_sessions 
                          SET cooking_status = 'Completed', completed_at = NOW() 
                          WHERE chef_id = ? AND meal_date = ? AND meal_type = ?";
        $complete_stmt = $db->prepare($complete_query);
        $complete_stmt->execute([$chef_id, $meal_date, $meal_type]);
        
        $success_message = "Cooking completed for $meal_type on " . date('M j, Y', strtotime($meal_date));
    }
    
    if (isset($_POST['use_inventory'])) {
        $cooking_session_id = $_POST['cooking_session_id'];
        $inventory_item_id = $_POST['inventory_item_id'];
        $quantity_used = floatval($_POST['quantity_used']);
        $usage_type = $_POST['usage_type'];
        $notes = $_POST['notes'] ?? '';
        
        // Get item cost
        $cost_query = "SELECT unit_cost, current_stock FROM inventory_items WHERE id = ?";
        $cost_stmt = $db->prepare($cost_query);
        $cost_stmt->execute([$inventory_item_id]);
        $item = $cost_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item && $item['current_stock'] >= $quantity_used) {
            $unit_cost = $item['unit_cost'];
            $total_cost = $quantity_used * $unit_cost;
            
            // Record inventory usage
            $usage_query = "INSERT INTO chef_inventory_usage (cooking_session_id, inventory_item_id, quantity_used, unit_cost, total_cost, usage_type, notes) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            $usage_stmt = $db->prepare($usage_query);
            $usage_stmt->execute([$cooking_session_id, $inventory_item_id, $quantity_used, $unit_cost, $total_cost, $usage_type, $notes]);
            
            // Update inventory stock
            $new_stock = $item['current_stock'] - $quantity_used;
            $update_query = "UPDATE inventory_items SET current_stock = ?, after_usage_current_stock = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$new_stock, $new_stock, $inventory_item_id]);
            
            // Record inventory transaction
            $trans_query = "INSERT INTO inventory_transactions (item_id, transaction_type, quantity, previous_stock, new_stock, reference_type, notes, performed_by) 
                           VALUES (?, 'OUT', ?, ?, ?, 'CHEF_USAGE', ?, ?)";
            $trans_stmt = $db->prepare($trans_query);
            $trans_stmt->execute([$inventory_item_id, $quantity_used, $item['current_stock'], $new_stock, "Chef usage: $usage_type - $notes", $_SESSION['user_id']]);
            
            $success_message = "Inventory usage recorded successfully!";
        } else {
            $error_message = "Insufficient stock or invalid item!";
        }
    }
}

// Get today's assignments for this chef
$today = date('Y-m-d');
$assignments_query = "SELECT 
    dm.id as daily_meal_id,
    dm.meal_date,
    dm.meal_type,
    CASE 
        WHEN dm.meal_type = 'Breakfast' THEN dm.breakfast_chef_id
        WHEN dm.meal_type = 'Lunch' THEN dm.lunch_chef_id
        WHEN dm.meal_type = 'Dinner' THEN dm.dinner_chef_id
    END as assigned_chef_id,
    COUNT(r.id) as total_residents,
    COUNT(CASE WHEN mp.spice_level = 'Spicy' THEN 1 END) as spicy_count,
    COUNT(CASE WHEN mp.spice_level = 'Non Spicy' THEN 1 END) as non_spicy_count,
    COUNT(CASE WHEN mp.spice_level = 'Medium' THEN 1 END) as medium_spicy_count,
    COUNT(CASE WHEN mp.oil_preference = 'Less oily' THEN 1 END) as less_oily_count,
    COUNT(CASE WHEN mp.oil_preference = 'Normal' THEN 1 END) as normal_oil_count,
    COUNT(CASE WHEN mp.oil_preference = 'Extra oily' THEN 1 END) as extra_oily_count,
    cs.cooking_status,
    cs.started_at,
    cs.completed_at,
    cs.id as session_id
FROM daily_meals dm
LEFT JOIN residents r ON 1=1
LEFT JOIN meal_preferences mp ON r.id = mp.resident_id 
    AND mp.meal_date = dm.meal_date 
    AND mp.meal_type = dm.meal_type
LEFT JOIN chef_cooking_sessions cs ON cs.chef_id = ? 
    AND cs.meal_date = dm.meal_date 
    AND cs.meal_type = dm.meal_type
WHERE dm.meal_date >= ? 
    AND dm.meal_date <= DATE_ADD(?, INTERVAL 2 DAY)
    AND (
        (dm.meal_type = 'Breakfast' AND dm.breakfast_chef_id = ?) OR
        (dm.meal_type = 'Lunch' AND dm.lunch_chef_id = ?) OR
        (dm.meal_type = 'Dinner' AND dm.dinner_chef_id = ?)
    )
GROUP BY dm.id, dm.meal_date, dm.meal_type, assigned_chef_id, cs.cooking_status, cs.started_at, cs.completed_at, cs.id
ORDER BY dm.meal_date, FIELD(dm.meal_type, 'Breakfast', 'Lunch', 'Dinner')";

$assignments_stmt = $db->prepare($assignments_query);
$assignments_stmt->execute([$chef_id, $today, $today, $chef_id, $chef_id, $chef_id]);
$assignments = $assignments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get inventory items
$inventory_query = "SELECT ii.*, ic.name as category_name, iu.abbreviation as unit_abbr 
                   FROM inventory_items ii 
                   LEFT JOIN inventory_categories ic ON ii.category_id = ic.id 
                   LEFT JOIN inventory_units iu ON ii.unit_id = iu.id 
                   WHERE ii.is_active = 1 AND ii.current_stock > 0 
                   ORDER BY ic.name, ii.name";
$inventory_stmt = $db->prepare($inventory_query);
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

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            padding: 20px;
            line-height: 1.6;
        }

        .cooking-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .cooking-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            text-align: center;
        }

        .cooking-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .cooking-header p {
            color: #6c757d;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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

        .assignments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .assignment-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .assignment-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .meal-info {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .meal-date {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .assignment-body {
            padding: 20px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .status-not-started {
            background: #fff3cd;
            color: #856404;
        }

        .status-in-progress {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .preferences-section {
            margin-bottom: 20px;
        }

        .preferences-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .preferences-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }

        .preference-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #667eea;
        }

        .preference-count {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }

        .preference-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 2px;
        }

        .cooking-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .inventory-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .inventory-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 10px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 5px;
            color: #495057;
        }

        .form-group select,
        .form-group input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .usage-types {
            display: flex;
            gap: 10px;
            margin: 10px 0;
        }

        .usage-type {
            padding: 5px 10px;
            background: #e9ecef;
            border-radius: 15px;
            font-size: 0.8rem;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .usage-type.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .assignments-grid {
                grid-template-columns: 1fr;
            }

            .inventory-form {
                grid-template-columns: 1fr;
            }

            .cooking-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="cooking-container">
        <!-- Header -->
        <div class="cooking-header">
            <h1><i class="fas fa-fire"></i> Daily Cooking Dashboard</h1>
            <p>Manage your cooking assignments and track inventory usage</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Assignments Grid -->
        <div class="assignments-grid">
            <?php foreach ($assignments as $assignment): ?>
            <div class="assignment-card">
                <div class="assignment-header">
                    <div class="meal-info">
                        <?php 
                        $icon = '';
                        if ($assignment['meal_type'] === 'Breakfast') $icon = 'fa-coffee';
                        elseif ($assignment['meal_type'] === 'Lunch') $icon = 'fa-hamburger';
                        else $icon = 'fa-moon';
                        ?>
                        <i class="fas <?php echo $icon; ?>"></i>
                        <?php echo $assignment['meal_type']; ?>
                    </div>
                    <div class="meal-date"><?php echo date('M j, Y', strtotime($assignment['meal_date'])); ?></div>
                </div>
                <div class="assignment-body">
                    <!-- Status Badge -->
                    <div class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $assignment['cooking_status'] ?? 'not-started')); ?>">
                        <?php echo $assignment['cooking_status'] ?? 'Not Started'; ?>
                    </div>

                    <!-- Resident Preferences -->
                    <div class="preferences-section">
                        <div class="preferences-title">Resident Preferences (<?php echo $assignment['total_residents']; ?> residents)</div>
                        <div class="preferences-grid">
                            <div class="preference-item">
                                <div class="preference-count"><?php echo $assignment['spicy_count']; ?></div>
                                <div class="preference-label">🌶️ Spicy</div>
                            </div>
                            <div class="preference-item">
                                <div class="preference-count"><?php echo $assignment['non_spicy_count']; ?></div>
                                <div class="preference-label">🥛 Non Spicy</div>
                            </div>
                            <div class="preference-item">
                                <div class="preference-count"><?php echo $assignment['medium_spicy_count']; ?></div>
                                <div class="preference-label">🌿 Medium</div>
                            </div>
                            <div class="preference-item">
                                <div class="preference-count"><?php echo $assignment['less_oily_count']; ?></div>
                                <div class="preference-label">💧 Less Oily</div>
                            </div>
                            <div class="preference-item">
                                <div class="preference-count"><?php echo $assignment['normal_oil_count']; ?></div>
                                <div class="preference-label">🥄 Normal Oil</div>
                            </div>
                            <div class="preference-item">
                                <div class="preference-count"><?php echo $assignment['extra_oily_count']; ?></div>
                                <div class="preference-label">🛢️ Extra Oily</div>
                            </div>
                        </div>
                    </div>

                    <!-- Cooking Actions -->
                    <div class="cooking-actions">
                        <?php if (!$assignment['cooking_status'] || $assignment['cooking_status'] === 'Not Started'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="meal_date" value="<?php echo $assignment['meal_date']; ?>">
                                <input type="hidden" name="meal_type" value="<?php echo $assignment['meal_type']; ?>">
                                <button type="submit" name="start_cooking" class="btn btn-primary">
                                    <i class="fas fa-play"></i> Start Cooking
                                </button>
                            </form>
                        <?php elseif ($assignment['cooking_status'] === 'In Progress'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="meal_date" value="<?php echo $assignment['meal_date']; ?>">
                                <input type="hidden" name="meal_type" value="<?php echo $assignment['meal_type']; ?>">
                                <button type="submit" name="complete_cooking" class="btn btn-success">
                                    <i class="fas fa-check"></i> Mark Complete
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($assignment['cooking_status'] === 'Completed'): ?>
                            <span class="btn btn-success" style="cursor: default;">
                                <i class="fas fa-check-circle"></i> Completed
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Inventory Usage Section -->
                    <?php if ($assignment['session_id'] && $assignment['cooking_status'] === 'In Progress'): ?>
                    <div class="inventory-section">
                        <h4><i class="fas fa-boxes"></i> Use Inventory Items</h4>
                        <form method="POST">
                            <input type="hidden" name="cooking_session_id" value="<?php echo $assignment['session_id']; ?>">
                            
                            <div class="inventory-form">
                                <div class="form-group">
                                    <label>Inventory Item:</label>
                                    <select name="inventory_item_id" required>
                                        <option value="">Select Item</option>
                                        <?php foreach ($inventory_items as $item): ?>
                                            <option value="<?php echo $item['id']; ?>">
                                                <?php echo $item['name']; ?> 
                                                (<?php echo $item['current_stock']; ?> <?php echo $item['unit_abbr']; ?> available)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Quantity Used:</label>
                                    <input type="number" name="quantity_used" step="0.01" min="0.01" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Usage Type:</label>
                                    <select name="usage_type" required>
                                        <option value="General">General</option>
                                        <option value="Spicy">For Spicy</option>
                                        <option value="Non Spicy">For Non Spicy</option>
                                        <option value="Less oily">For Less Oily</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" name="use_inventory" class="btn btn-info">
                                        <i class="fas fa-plus"></i> Use Item
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin-top: 10px;">
                                <label>Notes:</label>
                                <input type="text" name="notes" placeholder="Optional notes about usage...">
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($assignments)): ?>
        <div class="assignment-card">
            <div class="assignment-body" style="text-align: center; padding: 40px;">
                <i class="fas fa-calendar-times" style="font-size: 3rem; color: #dee2e6; margin-bottom: 15px;"></i>
                <h3 style="color: #6c757d;">No cooking assignments for today</h3>
                <p style="color: #adb5bd;">Check back later or contact the admin for meal assignments.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
