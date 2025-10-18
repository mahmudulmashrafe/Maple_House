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

// Get only active residents from the residents table
$occupied_rooms = [];

try {
    // First, get only active residents with their room numbers
    $residents_query = "SELECT r.*, u.first_name, u.last_name, u.email, u.phone, u.is_active 
                       FROM residents r 
                       JOIN users u ON r.user_id = u.id 
                       WHERE u.is_active = 1 
                       ORDER BY r.room_number";
    $residents_stmt = $db->prepare($residents_query);
    $residents_stmt->execute();
    $residents = $residents_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Check what columns exist in residents table
    if (!empty($residents)) {
        $sample_resident = $residents[0];
        // This will help us see what fields are available
    }
    
    foreach ($residents as $resident) {
        // User details are already available from the JOIN query
        $user_name = trim($resident['first_name'] . ' ' . $resident['last_name']);
        $user_email = $resident['email'] ?: 'N/A';
        $user_phone = $resident['phone'] ?: 'N/A';
        
        // Get payment plan details if plan_id exists
        $plan_name = 'No Plan';
        $monthly_fee = 0;
        $plan_type = 'Standard';
        $plan_class = 'standard';
        
        if (isset($resident['plan_id']) && $resident['plan_id']) {
            try {
                // Try different possible column names for payment plans
                $plan_query = "SELECT * FROM payment_plans WHERE id = ?";
                $plan_stmt = $db->prepare($plan_query);
                $plan_stmt->execute([$resident['plan_id']]);
                $plan_data = $plan_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($plan_data) {
                    // Check for different possible column names
                    $plan_name = $plan_data['plan_name'] ?? $plan_data['name'] ?? $plan_data['title'] ?? 'Plan ' . $resident['plan_id'];
                    $monthly_fee = $plan_data['monthly_fee'] ?? $plan_data['fee'] ?? $plan_data['price'] ?? $plan_data['amount'] ?? 0;
                    $plan_type = $plan_data['plan_type'] ?? $plan_data['type'] ?? $plan_data['category'] ?? 'Standard';
                    
                    // Set plan class based on type or name
                    $plan_lower = strtolower($plan_type . ' ' . $plan_name);
                    if (strpos($plan_lower, 'basic') !== false || strpos($plan_lower, 'plan 1') !== false) {
                        $plan_class = 'basic';
                        $plan_type = 'Basic';
                    } elseif (strpos($plan_lower, 'premium') !== false || strpos($plan_lower, 'plan 2') !== false) {
                        $plan_class = 'premium';
                        $plan_type = 'Premium';
                    } elseif (strpos($plan_lower, 'vip') !== false || strpos($plan_lower, 'plan 3') !== false || strpos($plan_lower, 'deluxe') !== false) {
                        $plan_class = 'vip';
                        $plan_type = 'VIP';
                    } else {
                        $plan_class = 'standard';
                        $plan_type = 'Standard';
                    }
                }
            } catch (Exception $e) {
                // If payment_plans table doesn't exist, try to infer from plan_id
                if ($resident['plan_id'] == 1) {
                    $plan_name = 'Basic Plan';
                    $monthly_fee = 15000;
                    $plan_type = 'Basic';
                    $plan_class = 'basic';
                } elseif ($resident['plan_id'] == 2) {
                    $plan_name = 'Premium Plan';
                    $monthly_fee = 25000;
                    $plan_type = 'Premium';
                    $plan_class = 'premium';
                } elseif ($resident['plan_id'] == 3) {
                    $plan_name = 'VIP Plan';
                    $monthly_fee = 35000;
                    $plan_type = 'VIP';
                    $plan_class = 'vip';
                } else {
                    $plan_name = 'Plan ' . $resident['plan_id'];
                    $monthly_fee = 20000;
                    $plan_type = 'Standard';
                    $plan_class = 'standard';
                }
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
    // If there's an error, show empty array (no dummy data)
    $occupied_rooms = [];
}

// Generate all room numbers (assuming rooms 101-120, 201-220, 301-320)
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
        'resident_email' => null,
        'resident_phone' => null,
        'admission_date' => null,
        'plan_name' => null,
        'monthly_fee' => null,
        'plan_type' => null,
        'days_stayed' => 0,
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

// Get recent admissions (only active residents)
$recent_admissions_query = "SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) as resident_name, pp.plan_name
                            FROM residents r
                            JOIN users u ON r.user_id = u.id
                            LEFT JOIN payment_plans pp ON r.plan_id = pp.id
                            WHERE u.is_active = 1
                            ORDER BY r.admission_date DESC
                            LIMIT 8";
$recent_admissions_stmt = $db->prepare($recent_admissions_query);
$recent_admissions_stmt->execute();
$recent_admissions = $recent_admissions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get room occupancy details (only active residents)
$room_occupancy_query = "SELECT r.room_number, CONCAT(u.first_name, ' ', u.last_name) as resident_name,
                         r.admission_date, pp.plan_name, pp.monthly_fee,
                         DATEDIFF(CURDATE(), r.admission_date) as days_stayed
                         FROM residents r
                         JOIN users u ON r.user_id = u.id
                         LEFT JOIN payment_plans pp ON r.plan_id = pp.id
                         WHERE u.is_active = 1
                         ORDER BY r.room_number ASC
                         LIMIT 8";
$room_occupancy_stmt = $db->prepare($room_occupancy_query);
$room_occupancy_stmt->execute();
$room_occupancy = $room_occupancy_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment plan distribution (only active residents)
$plan_distribution_query = "SELECT pp.plan_name, COUNT(r.id) as resident_count, pp.monthly_fee,
                            SUM(pp.monthly_fee) as total_monthly_revenue
                            FROM payment_plans pp
                            LEFT JOIN residents r ON pp.id = r.plan_id
                            LEFT JOIN users u ON r.user_id = u.id
                            WHERE u.is_active = 1 OR r.id IS NULL
                            GROUP BY pp.id, pp.plan_name, pp.monthly_fee
                            ORDER BY resident_count DESC
                            LIMIT 6";
$plan_distribution_stmt = $db->prepare($plan_distribution_query);
$plan_distribution_stmt->execute();
$plan_distribution = $plan_distribution_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly admission trends (last 6 months, only active residents)
$admission_trends_query = "SELECT 
                           DATE_FORMAT(r.admission_date, '%Y-%m') as month,
                           COUNT(*) as admissions
                           FROM residents r
                           JOIN users u ON r.user_id = u.id
                           WHERE r.admission_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                           AND u.is_active = 1
                           GROUP BY DATE_FORMAT(r.admission_date, '%Y-%m')
                           ORDER BY month DESC
                           LIMIT 6";
$admission_trends_stmt = $db->prepare($admission_trends_query);
$admission_trends_stmt->execute();
$admission_trends = $admission_trends_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Occupancy Reports - Maple House</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
            padding-top: 80px; /* Reduced space for fixed filter bar */
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
        
        .main-content {
            padding: 20px;
            margin-top: 40px; /* Add space between filter bar and content */
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
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
        
        .filter-select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        .residents-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .residents-btn:hover {
            background: #2980b9;
            transform: translateY(-1px);
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
        
        @media (max-width: 768px) {
            .filter-controls {
                gap: 10px;
                padding: 10px 15px;
            }
            
            .filter-group {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .stats-summary {
                margin-left: 0;
                margin-top: 10px;
                width: 100%;
                text-align: center;
            }
            
            body {
                padding-top: 140px;
            }
        }
        
        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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
        
        .room-card.occupied {
            border-left-color: #dc3545;
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
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        }
        
        @media (max-width: 768px) {
            .occupancy-buttons {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .occupancy-buttons {
                grid-template-columns: 1fr;
            }
        }
        
        .occupancy-card {
            background: white;
            border-radius: 15px;
            padding: 20px 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            text-decoration: none;
            color: inherit;
        }
        
        .occupancy-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #3498db;
        }
        
        .occupancy-card.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .occupancy-card.occupied {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        
        .occupancy-card.available {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            color: #333;
        }
        
        .occupancy-card.rate {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .occupancy-card i {
            font-size: 2.5rem;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .occupancy-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .occupancy-label {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .occupancy-desc {
            font-size: 0.9rem;
            opacity: 0.8;
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-header {
            padding: 20px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header.admissions {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .section-header.rooms {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
        }
        
        .section-header.plans {
            background: linear-gradient(135deg, #ffecd2, #fcb69f);
            color: #333;
        }
        
        .section-header.trends {
            background: linear-gradient(135deg, #f093fb, #f5576c);
        }
        
        .section-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }
        
        .section-content {
            padding: 0;
        }
        
        .occupancy-row {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s ease;
        }
        
        .occupancy-row:last-child {
            border-bottom: none;
        }
        
        .occupancy-row:hover {
            background: #f8f9fa;
        }
        
        .occupancy-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .occupancy-details {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.4;
        }
        
        .occupancy-meta {
            font-size: 0.8rem;
            color: #888;
            margin-top: 5px;
        }
        
        .room-badge {
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
            background: #e3f2fd;
            color: #1976d2;
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
    </style>
</head>
<body>
    <!-- Filter Controls -->
    <div class="filter-controls">
        <button class="residents-btn" onclick="showAllResidents()">
            <i class="fas fa-users"></i> View All Residents
        </button>
        
        <div class="filter-group">
            <label><strong>Filter by Status:</strong></label>
            <select id="statusFilter" class="filter-select" onchange="filterRooms()">
                <option value="all">All Rooms</option>
                <option value="occupied">Occupied</option>
                <option value="available">Available</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label><strong>Filter by Plan:</strong></label>
            <select id="planFilter" class="filter-select" onchange="filterRooms()">
                <option value="all">All Plans</option>
                <option value="basic">Basic</option>
                <option value="premium">Premium</option>
                <option value="vip">VIP</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label><strong>Filter by Floor:</strong></label>
            <select id="floorFilter" class="filter-select" onchange="filterRooms()">
                <option value="all">All Floors</option>
                <option value="1">1st Floor</option>
                <option value="2">2nd Floor</option>
                <option value="3">3rd Floor</option>
            </select>
        </div>
        
        <div class="stats-summary">
            Total: <?php echo $total_rooms; ?> | Occupied: <?php echo $occupied_count; ?> | Available: <?php echo $available_count; ?> | Rate: <?php echo $occupancy_rate; ?>%
        </div>
    </div>

    <div class="main-content">
        <!-- Rooms Grid -->
        <div class="rooms-grid" id="roomsGrid">
        <?php foreach ($room_status as $room_number => $room): ?>
            <div class="room-card <?php echo $room['occupied'] ? 'occupied ' . $room['plan_class'] : 'available'; ?>" 
                 data-room="<?php echo $room_number; ?>"
                 data-status="<?php echo $room['occupied'] ? 'occupied' : 'available'; ?>"
                 data-plan="<?php echo $room['plan_class']; ?>"
                 data-floor="<?php echo substr($room_number, 0, 1); ?>"
                 onclick="showRoomDetails('<?php echo $room_number; ?>')">
                
                <div class="room-header">
                    <div class="room-number">Room <?php echo $room_number; ?></div>
                    <div class="room-status <?php echo $room['occupied'] ? 'status-occupied' : 'status-available'; ?>">
                        <?php echo $room['occupied'] ? 'Occupied' : 'Available'; ?>
                    </div>
                </div>
                
                <?php if ($room['occupied']): ?>
                    <div class="resident-info">
                        <div class="resident-name">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($room['resident_name']); ?>
                        </div>
                        <div class="resident-details">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($room['resident_email']); ?><br>
                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($room['resident_phone']); ?>
                        </div>
                        <div class="stay-duration">
                            <i class="fas fa-calendar"></i> 
                            Staying for <?php echo $room['days_stayed']; ?> days
                            (since <?php echo date('M j, Y', strtotime($room['admission_date'])); ?>)
                        </div>
                    </div>
                    
                    <?php if ($room['plan_name']): ?>
                        <div class="plan-info">
                            <span class="plan-name">
                                <i class="fas fa-star"></i> <?php echo htmlspecialchars($room['plan_name']); ?>
                            </span>
                            <span class="plan-fee">৳<?php echo number_format($room['monthly_fee'] ?: 0); ?>/month</span>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="resident-info">
                        <div class="resident-details" style="text-align: center; color: #999; padding: 20px 0;">
                            <i class="fas fa-door-open" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                            Room Available
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>

    </div> <!-- End main-content -->

    <!-- Room Details Modal -->
    <div id="roomDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalRoomTitle">Room Details</h3>
                <button class="close" onclick="closeRoomModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalRoomContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- All Residents Modal -->
    <div id="allResidentsModal" class="modal">
        <div class="modal-content" style="max-width: 1000px;">
            <div class="modal-header">
                <h3 class="modal-title">All Residents Information</h3>
                <button class="close" onclick="closeResidentsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 20px;">
                    <input type="text" id="residentSearch" placeholder="Search residents..." 
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"
                           onkeyup="filterResidents()">
                </div>
                <div id="residentsTable" style="max-height: 500px; overflow-y: auto;">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Store room data for JavaScript access
        const roomData = <?php echo json_encode($room_status); ?>;

        function filterRooms() {
            const statusFilter = document.getElementById('statusFilter').value;
            const planFilter = document.getElementById('planFilter').value;
            const floorFilter = document.getElementById('floorFilter').value;
            
            const roomCards = document.querySelectorAll('.room-card');
            
            roomCards.forEach(card => {
                let show = true;
                
                // Status filter
                if (statusFilter !== 'all' && card.dataset.status !== statusFilter) {
                    show = false;
                }
                
                // Plan filter
                if (planFilter !== 'all' && card.dataset.plan !== planFilter) {
                    show = false;
                }
                
                // Floor filter
                if (floorFilter !== 'all' && card.dataset.floor !== floorFilter) {
                    show = false;
                }
                
                card.style.display = show ? 'block' : 'none';
            });
        }

        function showRoomDetails(roomNumber) {
            const room = roomData[roomNumber];
            const modal = document.getElementById('roomDetailsModal');
            const title = document.getElementById('modalRoomTitle');
            const content = document.getElementById('modalRoomContent');
            
            title.textContent = `Room ${roomNumber} Details`;
            
            let html = `
                <div style="margin-bottom: 20px;">
                    <h4 style="color: #2c3e50; margin-bottom: 15px;">
                        <i class="fas fa-bed"></i> Room Information
                    </h4>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <p><strong>Room Number:</strong> ${roomNumber}</p>
                        <p><strong>Floor:</strong> ${roomNumber.charAt(0)} Floor</p>
                        <p><strong>Status:</strong> 
                            <span class="room-status ${room.occupied ? 'status-occupied' : 'status-available'}">
                                ${room.occupied ? 'Occupied' : 'Available'}
                            </span>
                        </p>
                    </div>
                </div>
            `;
            
            if (room.occupied) {
                html += `
                    <div style="margin-bottom: 20px;">
                        <h4 style="color: #2c3e50; margin-bottom: 15px;">
                            <i class="fas fa-user"></i> Current Resident
                        </h4>
                        <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">
                            <p><strong>Name:</strong> ${room.resident_name || 'N/A'}</p>
                            <p><strong>Email:</strong> ${room.resident_email || 'N/A'}</p>
                            <p><strong>Phone:</strong> ${room.resident_phone || 'N/A'}</p>
                            <p><strong>Admission Date:</strong> ${room.admission_date ? new Date(room.admission_date).toLocaleDateString() : 'N/A'}</p>
                            <p><strong>Days Stayed:</strong> ${room.days_stayed} days</p>
                        </div>
                    </div>
                `;
                
                if (room.plan_name) {
                    html += `
                        <div style="margin-bottom: 20px;">
                            <h4 style="color: #2c3e50; margin-bottom: 15px;">
                                <i class="fas fa-star"></i> Payment Plan
                            </h4>
                            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
                                <p><strong>Plan Name:</strong> ${room.plan_name}</p>
                                <p><strong>Plan Type:</strong> ${room.plan_type || 'Standard'}</p>
                                <p><strong>Monthly Fee:</strong> ৳${room.monthly_fee ? parseInt(room.monthly_fee).toLocaleString() : '0'}</p>
                            </div>
                        </div>
                    `;
                }
                
                html += `
                    <div style="margin-bottom: 20px;">
                        <h4 style="color: #2c3e50; margin-bottom: 15px;">
                            <i class="fas fa-history"></i> Stay History
                        </h4>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                            <p><strong>Total Stay Duration:</strong> ${room.days_stayed} days</p>
                            <p><strong>Estimated Monthly Cost:</strong> ৳${room.monthly_fee ? parseInt(room.monthly_fee).toLocaleString() : '0'}</p>
                            <p><strong>Total Estimated Cost:</strong> ৳${room.monthly_fee ? Math.round((room.days_stayed / 30) * room.monthly_fee).toLocaleString() : '0'}</p>
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div style="margin-bottom: 20px;">
                        <h4 style="color: #2c3e50; margin-bottom: 15px;">
                            <i class="fas fa-door-open"></i> Availability
                        </h4>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                            <i class="fas fa-door-open" style="font-size: 3rem; color: #6c757d; margin-bottom: 15px;"></i>
                            <p style="font-size: 1.1rem; color: #6c757d;">This room is currently available for new residents.</p>
                            <p style="color: #666;">Contact administration to assign a new resident to this room.</p>
                        </div>
                    </div>
                `;
            }
            
            content.innerHTML = html;
            modal.style.display = 'block';
        }

        function closeRoomModal() {
            document.getElementById('roomDetailsModal').style.display = 'none';
        }

        function showAllResidents() {
            const modal = document.getElementById('allResidentsModal');
            const table = document.getElementById('residentsTable');
            
            // Get all residents data (both current and historical)
            const allResidents = [];
            
            // Add current residents
            Object.keys(roomData).forEach(roomNumber => {
                const room = roomData[roomNumber];
                if (room.occupied) {
                    allResidents.push({
                        name: room.resident_name,
                        email: room.resident_email,
                        phone: room.resident_phone,
                        room: roomNumber,
                        plan: room.plan_name,
                        fee: room.monthly_fee,
                        admission: room.admission_date,
                        days: room.days_stayed,
                        status: 'Current',
                        type: 'current'
                    });
                }
            });
            
            // Generate table HTML
            let html = `
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Name</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Room</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Contact</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Plan</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Admission</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Duration</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            allResidents.forEach(resident => {
                const statusClass = resident.type === 'current' ? 'status-occupied' : 'status-available';
                html += `
                    <tr class="resident-row" style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <strong>${resident.name}</strong>
                        </td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <span class="room-badge" style="background: #e9ecef; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem;">
                                Room ${resident.room}
                            </span>
                        </td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <div style="font-size: 0.85rem;">
                                <div><i class="fas fa-envelope"></i> ${resident.email}</div>
                                <div><i class="fas fa-phone"></i> ${resident.phone}</div>
                            </div>
                        </td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <div><strong>${resident.plan}</strong></div>
                            <div style="color: #28a745; font-weight: 600;">৳${parseInt(resident.fee || 0).toLocaleString()}/month</div>
                        </td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            ${new Date(resident.admission).toLocaleDateString()}
                        </td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            ${resident.days} days
                        </td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <span class="room-status ${statusClass}">${resident.status}</span>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            if (allResidents.length === 0) {
                html = `
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.5;"></i>
                        <p>No residents found in the database.</p>
                    </div>
                `;
            }
            
            table.innerHTML = html;
            modal.style.display = 'block';
        }

        function closeResidentsModal() {
            document.getElementById('allResidentsModal').style.display = 'none';
        }

        function filterResidents() {
            const searchTerm = document.getElementById('residentSearch').value.toLowerCase();
            const rows = document.querySelectorAll('.resident-row');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const roomModal = document.getElementById('roomDetailsModal');
            const residentsModal = document.getElementById('allResidentsModal');
            
            if (event.target == roomModal) {
                closeRoomModal();
            }
            if (event.target == residentsModal) {
                closeResidentsModal();
            }
        }
    </script>
</body>
</html>
