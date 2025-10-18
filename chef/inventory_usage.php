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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'start_session':
                $stmt = $db->prepare("INSERT INTO chef_usage_sessions (chef_id, meal_type, meal_date, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $_POST['meal_type'],
                    $_POST['meal_date'],
                    $_POST['description']
                ]);
                $session_id = $db->lastInsertId();
                echo json_encode(['success' => true, 'session_id' => $session_id, 'message' => 'Cooking session started']);
                break;
                
            case 'add_usage':
                // Check if item has enough stock
                $stmt = $db->prepare("SELECT current_stock, unit_cost FROM inventory_items WHERE id = ? AND is_active = TRUE");
                $stmt->execute([$_POST['item_id']]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$item) {
                    echo json_encode(['success' => false, 'message' => 'Item not found']);
                    break;
                }
                
                if ($item['current_stock'] < $_POST['quantity']) {
                    echo json_encode(['success' => false, 'message' => 'Insufficient stock. Available: ' . $item['current_stock']]);
                    break;
                }
                
                // Add usage detail
                $total_cost = $_POST['quantity'] * $item['unit_cost'];
                $stmt = $db->prepare("INSERT INTO chef_usage_details (session_id, item_id, quantity_used, unit_cost, total_cost, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['session_id'],
                    $_POST['item_id'],
                    $_POST['quantity'],
                    $item['unit_cost'],
                    $total_cost,
                    $_POST['notes']
                ]);
                
                // Update inventory stock
                $new_stock = $item['current_stock'] - $_POST['quantity'];
                $stmt = $db->prepare("UPDATE inventory_items SET current_stock = ? WHERE id = ?");
                $stmt->execute([$new_stock, $_POST['item_id']]);
                
                // Log transaction
                $stmt = $db->prepare("INSERT INTO inventory_transactions (item_id, transaction_type, quantity, previous_stock, new_stock, reference_type, reference_id, notes, performed_by) VALUES (?, 'OUT', ?, ?, ?, 'CHEF_USAGE', ?, ?, ?)");
                $stmt->execute([
                    $_POST['item_id'],
                    $_POST['quantity'],
                    $item['current_stock'],
                    $new_stock,
                    $_POST['session_id'],
                    $_POST['notes'],
                    $_SESSION['user_id']
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Item used successfully', 'new_stock' => $new_stock]);
                break;
                
            case 'complete_session':
                // Calculate total cost
                $stmt = $db->prepare("SELECT SUM(total_cost) as total FROM chef_usage_details WHERE session_id = ?");
                $stmt->execute([$_POST['session_id']]);
                $total_cost = $stmt->fetchColumn() ?: 0;
                
                // Update session
                $stmt = $db->prepare("UPDATE chef_usage_sessions SET status = 'COMPLETED', total_cost = ?, completed_at = NOW() WHERE id = ?");
                $stmt->execute([$total_cost, $_POST['session_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Cooking session completed']);
                break;
                
            case 'cancel_session':
                // Get all usage details for this session
                $stmt = $db->prepare("SELECT * FROM chef_usage_details WHERE session_id = ?");
                $stmt->execute([$_POST['session_id']]);
                $usage_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Reverse all stock changes
                foreach ($usage_details as $detail) {
                    // Add stock back
                    $stmt = $db->prepare("UPDATE inventory_items SET current_stock = current_stock + ? WHERE id = ?");
                    $stmt->execute([$detail['quantity_used'], $detail['item_id']]);
                    
                    // Log reversal transaction
                    $stmt = $db->prepare("INSERT INTO inventory_transactions (item_id, transaction_type, quantity, previous_stock, new_stock, reference_type, reference_id, notes, performed_by) VALUES (?, 'IN', ?, (SELECT current_stock - ? FROM inventory_items WHERE id = ?), (SELECT current_stock FROM inventory_items WHERE id = ?), 'ADJUSTMENT', ?, 'Session cancelled - stock restored', ?)");
                    $stmt->execute([
                        $detail['item_id'],
                        $detail['quantity_used'],
                        $detail['quantity_used'],
                        $detail['item_id'],
                        $detail['item_id'],
                        $_POST['session_id'],
                        $_SESSION['user_id']
                    ]);
                }
                
                // Cancel session
                $stmt = $db->prepare("UPDATE chef_usage_sessions SET status = 'CANCELLED' WHERE id = ?");
                $stmt->execute([$_POST['session_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Session cancelled and stock restored']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Get current active session
$current_session = null;
$stmt = $db->prepare("SELECT * FROM chef_usage_sessions WHERE chef_id = ? AND status = 'IN_PROGRESS' ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$current_session = $stmt->fetch(PDO::FETCH_ASSOC);

// Get inventory items available for use
$inventory_query = "SELECT i.*, c.name as category_name, u.name as unit_name, u.abbreviation as unit_abbr
                    FROM inventory_items i
                    LEFT JOIN inventory_categories c ON i.category_id = c.id
                    LEFT JOIN inventory_units u ON i.unit_id = u.id
                    WHERE i.is_active = TRUE AND i.current_stock > 0
                    ORDER BY c.name, i.name";
$inventory_stmt = $db->prepare($inventory_query);
$inventory_stmt->execute();
$inventory_items = $inventory_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current session usage details if there's an active session
$session_usage = [];
if ($current_session) {
    $stmt = $db->prepare("SELECT ud.*, i.name as item_name, u.abbreviation as unit_abbr
                          FROM chef_usage_details ud
                          JOIN inventory_items i ON ud.item_id = i.id
                          JOIN inventory_units u ON i.unit_id = u.id
                          WHERE ud.session_id = ?
                          ORDER BY ud.used_at DESC");
    $stmt->execute([$current_session['id']]);
    $session_usage = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get recent completed sessions
$recent_sessions_stmt = $db->prepare("SELECT * FROM chef_usage_sessions WHERE chef_id = ? AND status != 'IN_PROGRESS' ORDER BY created_at DESC LIMIT 5");
$recent_sessions_stmt->execute([$_SESSION['user_id']]);
$recent_sessions = $recent_sessions_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chef Inventory Usage - Maple House</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        html {
            scroll-behavior: smooth;
            overflow-x: hidden;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* Internet Explorer 10+ */
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        /* Hide scrollbars but keep functionality */
        ::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .header-info h2 {
            color: #2c3e50;
            font-size: 1.5rem;
            margin: 0 0 5px 0;
        }
        
        .header-info p {
            color: #666;
            font-size: 0.9rem;
            margin: 0;
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
        
        .session-status {
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .status-active {
            border-left: 4px solid #27ae60;
        }
        .status-inactive {
            border-left: 4px solid #95a5a6;
        }
        
        .inventory-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .inventory-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .inventory-item:last-child {
            border-bottom: none;
        }
        
        .item-info h4 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-size: 1.1rem;
        }
        
        .item-info p {
            margin: 0 0 3px 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .item-info small {
            color: #27ae60;
            font-weight: 500;
        }
        
        .item-stock {
            text-align: right;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stock-amount {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .stock-ok {
            color: #27ae60;
        }
        
        .stock-low {
            color: #e74c3c;
        }
        
        .no-inventory {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .current-session {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .session-header {
            background: #27ae60;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .session-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }
        
        .session-actions {
            display: flex;
            gap: 10px;
        }
        
        .session-info {
            padding: 20px;
        }
        
        .session-info p {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        
        .session-usage {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .session-usage h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 1rem;
        }
        
        .usage-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 0.9rem;
        }
        
        .usage-total {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
            text-align: right;
            color: #27ae60;
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
            border-radius: 12px;
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
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .total-cost {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-top: 15px;
        }
        .total-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">


        <div class="inventory-list">
            <?php if (!empty($inventory_items)): ?>
                <?php foreach ($inventory_items as $item): ?>
                <div class="inventory-item">
                    <div class="item-info">
                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                        <p><?php echo htmlspecialchars($item['category_name']); ?></p>
                        <small>৳<?php echo number_format($item['unit_cost'], 2); ?> per <?php echo htmlspecialchars($item['unit_abbr']); ?></small>
                    </div>
                    <div class="item-stock">
                        <div class="stock-amount <?php echo $item['current_stock'] <= $item['minimum_stock'] ? 'stock-low' : 'stock-ok'; ?>">
                            <?php echo number_format($item['current_stock'], 1); ?> <?php echo htmlspecialchars($item['unit_abbr']); ?>
                        </div>
                        <?php if ($current_session): ?>
                        <button class="btn btn-primary" onclick="showUseModal(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                            Use
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-inventory">
                    <p>No inventory items available.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($current_session): ?>
        <div class="current-session">
            <div class="session-header">
                <h3>🔥 Current Cooking Session</h3>
                <div class="session-actions">
                    <button class="btn btn-success" onclick="completeSession(<?php echo $current_session['id']; ?>)">
                        <i class="fas fa-check"></i> Complete
                    </button>
                    <button class="btn btn-danger" onclick="cancelSession(<?php echo $current_session['id']; ?>)">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
            <div class="session-info">
                <p><strong>Meal:</strong> <?php echo htmlspecialchars($current_session['meal_type']); ?></p>
                <p><strong>Started:</strong> <?php echo date('g:i A', strtotime($current_session['created_at'])); ?></p>
                
                <?php if (!empty($session_usage)): ?>
                <div class="session-usage">
                    <h4>Ingredients Used:</h4>
                    <?php 
                    $total_cost = 0;
                    foreach ($session_usage as $usage): 
                        $total_cost += $usage['total_cost'];
                    ?>
                    <div class="usage-item">
                        <span><?php echo htmlspecialchars($usage['item_name']); ?> - <?php echo number_format($usage['quantity_used'], 1); ?> <?php echo htmlspecialchars($usage['unit_abbr']); ?></span>
                        <span>৳<?php echo number_format($usage['total_cost'], 2); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="usage-total">
                        <strong>Total: ৳<?php echo number_format($total_cost, 2); ?></strong>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Start Session Modal -->
    <div id="startSessionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-play"></i> Start Cooking Session</h3>
                <span class="close" onclick="closeModal('startSessionModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="startSessionForm">
                    <div class="form-group">
                        <label>Meal Type *</label>
                        <select name="meal_type" class="form-control" required>
                            <option value="">Select Meal Type</option>
                            <option value="BREAKFAST">Breakfast</option>
                            <option value="LUNCH">Lunch</option>
                            <option value="DINNER">Dinner</option>
                            <option value="SNACK">Snack</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Meal Date *</label>
                        <input type="date" name="meal_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="What are you cooking today?"></textarea>
                    </div>
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('startSessionModal')">Cancel</button>
                        <button type="submit" class="btn btn-success">Start Session</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Use Item Modal -->
    <div id="useItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-utensils"></i> Use Ingredient</h3>
                <span class="close" onclick="closeModal('useItemModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="useItemForm">
                    <input type="hidden" name="session_id" value="<?php echo $current_session['id'] ?? ''; ?>">
                    <input type="hidden" name="item_id" id="use_item_id">
                    <div class="form-group">
                        <label>Item</label>
                        <input type="text" id="use_item_name" class="form-control" readonly style="background: #f8f9fa;">
                    </div>
                    <div class="form-group">
                        <label>Available Stock</label>
                        <input type="text" id="use_available_stock" class="form-control" readonly style="background: #f8f9fa;">
                    </div>
                    <div class="form-group">
                        <label>Quantity to Use *</label>
                        <input type="number" name="quantity" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="How will this be used?"></textarea>
                    </div>
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('useItemModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Use Ingredient</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showStartSessionModal() {
            document.getElementById('startSessionModal').style.display = 'block';
        }

        function showUseModal(item) {
            document.getElementById('use_item_id').value = item.id;
            document.getElementById('use_item_name').value = item.name;
            document.getElementById('use_available_stock').value = item.current_stock + ' ' + item.unit_abbr;
            document.getElementById('useItemModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Form submissions
        document.getElementById('startSessionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, 'start_session', function() {
                location.reload();
            });
        });

        document.getElementById('useItemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, 'add_usage', function() {
                closeModal('useItemModal');
                location.reload();
            });
        });

        function submitForm(form, action, successCallback) {
            const formData = new FormData(form);
            formData.append('action', action);
            
            fetch('inventory_usage.php', {
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

        function completeSession(sessionId) {
            if (confirm('Are you sure you want to complete this cooking session?')) {
                const formData = new FormData();
                formData.append('action', 'complete_session');
                formData.append('session_id', sessionId);
                
                fetch('inventory_usage.php', {
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

        function cancelSession(sessionId) {
            if (confirm('Are you sure you want to cancel this session? All ingredient usage will be reversed.')) {
                const formData = new FormData();
                formData.append('action', 'cancel_session');
                formData.append('session_id', sessionId);
                
                fetch('inventory_usage.php', {
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

