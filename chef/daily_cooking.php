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
    
    if (isset($_POST['cancel_cooking'])) {
        $meal_date = $_POST['meal_date'];
        $meal_type = $_POST['meal_type'];
        
        $cancel_query = "UPDATE chef_cooking_sessions 
                        SET cooking_status = 'Cancelled', completed_at = NOW() 
                        WHERE chef_id = ? AND meal_date = ? AND meal_type = ?";
        $cancel_stmt = $db->prepare($cancel_query);
        $cancel_stmt->execute([$chef_id, $meal_date, $meal_type]);
        
        $success_message = "Cooking cancelled for $meal_type on " . date('M j, Y', strtotime($meal_date));
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

        .btn-danger {
            background: #dc3545;
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 25px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header.cancel-header {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        .modal-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
            transition: transform 0.2s ease;
        }

        .close:hover {
            transform: scale(1.2);
        }

        .modal-body {
            padding: 30px;
        }

        .modal-meal-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .modal-meal-info h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-meal-info p {
            margin: 5px 0;
            color: #6c757d;
        }

        .modal-confirmation {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .modal-confirmation.danger {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }

        .modal-confirmation p {
            margin: 0;
            color: #856404;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-confirmation.danger p {
            color: #721c24;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .modal-btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-btn-cancel {
            background: #6c757d;
            color: white;
        }

        .modal-btn-cancel:hover {
            background: #5a6268;
        }

        .modal-btn-confirm {
            background: #28a745;
            color: white;
        }

        .modal-btn-confirm:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .modal-btn-danger {
            background: #dc3545;
            color: white;
        }

        .modal-btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
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

            .modal-content {
                margin: 20% auto;
                width: 95%;
            }

            .modal-actions {
                flex-direction: column;
            }

            .modal-btn {
                width: 100%;
                justify-content: center;
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
                            <button type="button" 
                                    onclick="openCompleteModal('<?php echo $assignment['meal_date']; ?>', '<?php echo $assignment['meal_type']; ?>', '<?php echo $assignment['total_residents']; ?>')" 
                                    class="btn btn-success">
                                <i class="fas fa-check"></i> Mark Complete
                            </button>
                            <button type="button" 
                                    onclick="openCancelModal('<?php echo $assignment['meal_date']; ?>', '<?php echo $assignment['meal_type']; ?>', '<?php echo $assignment['total_residents']; ?>')" 
                                    class="btn btn-danger"
                                    style="background: #dc3545;">
                                <i class="fas fa-times-circle"></i> Cancel Cooking
                            </button>
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

    <!-- Complete Cooking Modal -->
    <div id="completeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-check-circle"></i> Complete Cooking
                </h3>
                <span class="close" onclick="closeCompleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="modal-meal-info">
                    <h4>
                        <i class="fas fa-utensils"></i> Meal Details
                    </h4>
                    <p><strong>Meal Type:</strong> <span id="modalMealType"></span></p>
                    <p><strong>Date:</strong> <span id="modalMealDate"></span></p>
                    <p><strong>Total Residents:</strong> <span id="modalResidentCount"></span></p>
                </div>

                <div class="modal-confirmation">
                    <p>
                        <i class="fas fa-info-circle"></i>
                        <strong>Are you sure you have completed cooking for this meal?</strong>
                    </p>
                </div>

                <p style="color: #6c757d; font-size: 0.95rem; margin-bottom: 25px;">
                    <i class="fas fa-lightbulb" style="color: #ffc107;"></i>
                    This will mark the meal as completed and update the cooking status. Make sure all preparations are finished before confirming.
                </p>

                <form id="completeForm" method="POST">
                    <input type="hidden" name="meal_date" id="completeMealDate">
                    <input type="hidden" name="meal_type" id="completeMealType">
                    <input type="hidden" name="complete_cooking" value="1">
                    
                    <div class="modal-actions">
                        <button type="button" class="modal-btn modal-btn-cancel" onclick="closeCompleteModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="modal-btn modal-btn-confirm">
                            <i class="fas fa-check-circle"></i> Yes, Mark Complete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cancel Cooking Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header cancel-header">
                <h3>
                    <i class="fas fa-exclamation-triangle"></i> Cancel Cooking
                </h3>
                <span class="close" onclick="closeCancelModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="modal-meal-info">
                    <h4>
                        <i class="fas fa-utensils"></i> Meal Details
                    </h4>
                    <p><strong>Meal Type:</strong> <span id="cancelModalMealType"></span></p>
                    <p><strong>Date:</strong> <span id="cancelModalMealDate"></span></p>
                    <p><strong>Total Residents:</strong> <span id="cancelModalResidentCount"></span></p>
                </div>

                <div class="modal-confirmation danger">
                    <p>
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Are you sure you want to cancel this cooking session?</strong>
                    </p>
                </div>

                <p style="color: #721c24; font-size: 0.95rem; margin-bottom: 25px; background: #f8d7da; padding: 15px; border-radius: 8px;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Warning:</strong> Cancelling will stop the cooking session. Any inventory items already used will remain recorded. This action cannot be undone.
                </p>

                <form id="cancelForm" method="POST">
                    <input type="hidden" name="meal_date" id="cancelMealDate">
                    <input type="hidden" name="meal_type" id="cancelMealType">
                    <input type="hidden" name="cancel_cooking" value="1">
                    
                    <div class="modal-actions">
                        <button type="button" class="modal-btn modal-btn-cancel" onclick="closeCancelModal()">
                            <i class="fas fa-arrow-left"></i> Go Back
                        </button>
                        <button type="submit" class="modal-btn modal-btn-danger">
                            <i class="fas fa-times-circle"></i> Yes, Cancel Cooking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCompleteModal(mealDate, mealType, residentCount) {
            // Set modal values
            document.getElementById('completeMealDate').value = mealDate;
            document.getElementById('completeMealType').value = mealType;
            
            // Display values
            document.getElementById('modalMealType').textContent = mealType;
            
            // Format date nicely
            const dateObj = new Date(mealDate);
            const formattedDate = dateObj.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            document.getElementById('modalMealDate').textContent = formattedDate;
            document.getElementById('modalResidentCount').textContent = residentCount;
            
            // Show modal
            document.getElementById('completeModal').style.display = 'block';
        }

        function closeCompleteModal() {
            document.getElementById('completeModal').style.display = 'none';
        }

        function openCancelModal(mealDate, mealType, residentCount) {
            // Set modal values
            document.getElementById('cancelMealDate').value = mealDate;
            document.getElementById('cancelMealType').value = mealType;
            
            // Display values
            document.getElementById('cancelModalMealType').textContent = mealType;
            
            // Format date nicely
            const dateObj = new Date(mealDate);
            const formattedDate = dateObj.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            document.getElementById('cancelModalMealDate').textContent = formattedDate;
            document.getElementById('cancelModalResidentCount').textContent = residentCount;
            
            // Show modal
            document.getElementById('cancelModal').style.display = 'block';
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const completeModal = document.getElementById('completeModal');
            const cancelModal = document.getElementById('cancelModal');
            
            if (event.target === completeModal) {
                closeCompleteModal();
            }
            if (event.target === cancelModal) {
                closeCancelModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeCompleteModal();
                closeCancelModal();
            }
        });
    </script>
</body>
</html>
