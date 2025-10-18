<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Staff') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get only active residents from the residents table
$occupied_rooms = [];

try {
    // Get only active residents with their room numbers
    $residents_query = "SELECT r.*, u.first_name, u.last_name, u.email, u.phone, u.is_active 
                       FROM residents r 
                       JOIN users u ON r.user_id = u.id 
                       WHERE u.is_active = 1 
                       ORDER BY r.room_number";
    $residents_stmt = $db->prepare($residents_query);
    $residents_stmt->execute();
    $residents = $residents_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($residents as $resident) {
        $user_name = trim($resident['first_name'] . ' ' . $resident['last_name']);
        $user_email = $resident['email'] ?: 'N/A';
        $user_phone = $resident['phone'] ?: 'N/A';
        
        // Get payment plan details
        $plan_name = 'No Plan';
        $monthly_fee = 0;
        $plan_type = 'Standard';
        $plan_class = 'standard';
        
        if (isset($resident['plan_id']) && $resident['plan_id']) {
            try {
                $plan_query = "SELECT * FROM payment_plans WHERE id = ?";
                $plan_stmt = $db->prepare($plan_query);
                $plan_stmt->execute([$resident['plan_id']]);
                $plan_data = $plan_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($plan_data) {
                    $plan_name = $plan_data['plan_name'] ?? 'Plan ' . $resident['plan_id'];
                    $monthly_fee = $plan_data['monthly_fee'] ?? 0;
                    $plan_type = $plan_data['plan_type'] ?? 'Standard';
                    
                    $plan_lower = strtolower($plan_type . ' ' . $plan_name);
                    if (strpos($plan_lower, 'basic') !== false) {
                        $plan_class = 'basic';
                        $plan_type = 'Basic';
                    } elseif (strpos($plan_lower, 'premium') !== false) {
                        $plan_class = 'premium';
                        $plan_type = 'Premium';
                    } elseif (strpos($plan_lower, 'vip') !== false) {
                        $plan_class = 'vip';
                        $plan_type = 'VIP';
                    }
                }
            } catch (Exception $e) {
                // Fallback
            }
        }
        
        // Calculate days stayed
        $admission_date = $resident['admission_date'] ?? $resident['created_at'] ?? date('Y-m-d');
        $days_stayed = max(0, floor((time() - strtotime($admission_date)) / (60 * 60 * 24)));
        
        $occupied_rooms[] = [
            'room_number' => $resident['room_number'],
            'resident_name' => $user_name,
            'resident_email' => $user_email,
            'resident_phone' => $user_phone,
            'admission_date' => $admission_date,
            'plan_name' => $plan_name,
            'monthly_fee' => $monthly_fee,
            'plan_type' => $plan_type,
            'days_stayed' => $days_stayed,
            'plan_class' => $plan_class
        ];
    }
    
} catch (Exception $e) {
    $occupied_rooms = [];
}

// Generate all room numbers (rooms 101-120, 201-220, 301-320)
$all_rooms = [];
for ($floor = 1; $floor <= 3; $floor++) {
    for ($room = 1; $room <= 20; $room++) {
        $room_number = $floor . str_pad($room, 2, '0', STR_PAD_LEFT);
        $all_rooms[] = $room_number;
    }
}

// Create room status array
$room_status = [];
foreach ($all_rooms as $room) {
    $room_status[$room] = [
        'occupied' => false,
        'resident_name' => null,
        'plan_class' => 'available'
    ];
}

// Fill in occupied room data
foreach ($occupied_rooms as $room) {
    if (isset($room_status[$room['room_number']])) {
        $room_status[$room['room_number']] = [
            'occupied' => true,
            'resident_name' => $room['resident_name'],
            'resident_email' => $room['resident_email'],
            'resident_phone' => $room['resident_phone'],
            'admission_date' => $room['admission_date'],
            'plan_name' => $room['plan_name'],
            'monthly_fee' => $room['monthly_fee'],
            'plan_type' => $room['plan_type'],
            'days_stayed' => $room['days_stayed'],
            'plan_class' => $room['plan_class']
        ];
    }
}

// Calculate statistics
$total_rooms = count($all_rooms);
$occupied_count = count($occupied_rooms);
$available_count = $total_rooms - $occupied_count;
$occupancy_rate = $total_rooms > 0 ? round(($occupied_count / $total_rooms) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residents - Maple House</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            padding: 0;
            padding-top: 80px;
        }
        
        .filter-controls {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            z-index: 100;
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
            border-bottom: 2px solid #e9ecef;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
        }
        
        .filter-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            background: white;
            min-width: 120px;
        }
        
        .stats-summary {
            margin-left: auto;
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #2c3e50;
            border: 1px solid #e9ecef;
        }
        
        .main-content {
            padding: 20px;
            margin-top: 20px;
        }
        
        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .room-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            border-left: 5px solid #e9ecef;
        }
        
        .room-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        
        .room-card.available {
            border-left-color: #28a745;
        }
        
        .room-card.basic {
            border-left-color: #17a2b8;
        }
        
        .room-card.premium {
            border-left-color: #ffc107;
        }
        
        .room-card.vip {
            border-left-color: #dc3545;
        }
        
        .room-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .room-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .room-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-occupied {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .resident-info {
            margin-bottom: 15px;
        }
        
        .resident-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .resident-details {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.4;
        }
        
        .plan-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        .plan-name {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .plan-fee {
            font-weight: bold;
            color: #28a745;
        }
        
        .stay-duration {
            font-size: 0.8rem;
            color: #888;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="filter-controls">
        <div class="filter-group">
            <label>Floor:</label>
            <select class="filter-select" id="floorFilter" onchange="filterRooms()">
                <option value="all">All Floors</option>
                <option value="1">Floor 1</option>
                <option value="2">Floor 2</option>
                <option value="3">Floor 3</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label>Status:</label>
            <select class="filter-select" id="statusFilter" onchange="filterRooms()">
                <option value="all">All Rooms</option>
                <option value="occupied">Occupied</option>
                <option value="available">Available</option>
            </select>
        </div>
        
        <div class="stats-summary">
            <i class="fas fa-door-open"></i> <?php echo $occupied_count; ?> / <?php echo $total_rooms; ?> Occupied (<?php echo $occupancy_rate; ?>%)
        </div>
    </div>
    
    <div class="main-content">
        <div class="rooms-grid" id="roomsGrid">
            <?php foreach ($room_status as $room_number => $room): ?>
                <div class="room-card <?php echo $room['plan_class']; ?>" 
                     data-floor="<?php echo substr($room_number, 0, 1); ?>"
                     data-status="<?php echo $room['occupied'] ? 'occupied' : 'available'; ?>">
                    <div class="room-header">
                        <div class="room-number">Room <?php echo $room_number; ?></div>
                        <span class="room-status status-<?php echo $room['occupied'] ? 'occupied' : 'available'; ?>">
                            <?php echo $room['occupied'] ? 'Occupied' : 'Available'; ?>
                        </span>
                    </div>
                    
                    <?php if ($room['occupied']): ?>
                        <div class="resident-info">
                            <div class="resident-name">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($room['resident_name']); ?>
                            </div>
                            <div class="resident-details">
                                <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($room['resident_email']); ?></div>
                                <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($room['resident_phone']); ?></div>
                            </div>
                        </div>
                        
                        <div class="plan-info">
                            <span class="plan-name"><?php echo htmlspecialchars($room['plan_name']); ?></span>
                            <span class="plan-fee">৳<?php echo number_format($room['monthly_fee']); ?></span>
                        </div>
                        
                        <div class="stay-duration">
                            <i class="fas fa-calendar-alt"></i> 
                            <?php echo $room['days_stayed']; ?> days since <?php echo date('M j, Y', strtotime($room['admission_date'])); ?>
                        </div>
                    <?php else: ?>
                        <div class="resident-info">
                            <div class="resident-details" style="text-align: center; color: #28a745; padding: 20px 0;">
                                <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                <div>Room Available</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        function filterRooms() {
            const floorFilter = document.getElementById('floorFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const cards = document.querySelectorAll('.room-card');
            
            cards.forEach(card => {
                const floor = card.getAttribute('data-floor');
                const status = card.getAttribute('data-status');
                
                let showCard = true;
                
                if (floorFilter !== 'all' && floor !== floorFilter) {
                    showCard = false;
                }
                
                if (statusFilter !== 'all' && status !== statusFilter) {
                    showCard = false;
                }
                
                card.style.display = showCard ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>
