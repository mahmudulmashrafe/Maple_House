<?php
/**
 * Room Availability Dashboard - Shows real-time room occupancy status
 */

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Manager'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get comprehensive room data
$room_query = "SELECT r.room_number, u.first_name, u.last_name, u.is_active, u.email,
                      pp.plan_name, pp.monthly_fee, r.admission_date,
                      CASE 
                          WHEN u.is_active = 1 THEN 'Occupied'
                          WHEN u.is_active = 0 THEN 'Available (Inactive Resident)'
                          ELSE 'Available'
                      END as room_status,
                      CASE 
                          WHEN u.is_active = 1 THEN '#dc3545'
                          WHEN u.is_active = 0 THEN '#ffc107'
                          ELSE '#28a745'
                      END as status_color
               FROM residents r 
               JOIN users u ON r.user_id = u.id 
               LEFT JOIN payment_plans pp ON r.plan_id = pp.id
               ORDER BY CAST(r.room_number AS UNSIGNED) ASC";

$room_stmt = $db->prepare($room_query);
$room_stmt->execute();
$rooms = $room_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
                   COUNT(DISTINCT r.room_number) as total_rooms,
                   COUNT(CASE WHEN u.is_active = 1 THEN 1 END) as occupied_rooms,
                   COUNT(CASE WHEN u.is_active = 0 THEN 1 END) as inactive_rooms,
                   SUM(CASE WHEN u.is_active = 1 THEN pp.monthly_fee ELSE 0 END) as monthly_revenue
               FROM residents r 
               JOIN users u ON r.user_id = u.id 
               LEFT JOIN payment_plans pp ON r.plan_id = pp.id";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$free_rooms = $stats['total_rooms'] - $stats['occupied_rooms'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Availability Dashboard - Maple House</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 1.1rem;
        }
        
        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .room-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s ease;
        }
        
        .room-card:hover {
            transform: translateY(-2px);
        }
        
        .room-header {
            padding: 20px;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .room-body {
            padding: 20px;
        }
        
        .room-info {
            margin-bottom: 10px;
        }
        
        .room-info strong {
            color: #333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            color: white;
            margin-top: 10px;
        }
        
        .legend {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .legend-item {
            display: inline-block;
            margin-right: 20px;
            margin-bottom: 10px;
        }
        
        .legend-color {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 4px;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        .refresh-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #3498db;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
            transition: all 0.3s ease;
        }
        
        .refresh-btn:hover {
            background: #2980b9;
            transform: scale(1.1);
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .rooms-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-building"></i> Room Availability Dashboard</h1>
            <p>Real-time room occupancy status and resident information</p>
            <p style="color: #666; margin: 0;">Last updated: <?php echo date('F j, Y - g:i A'); ?></p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" style="color: #2c5aa0;"><?php echo $stats['total_rooms']; ?></div>
                <div class="stat-label">Total Rooms</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #dc3545;"><?php echo $stats['occupied_rooms']; ?></div>
                <div class="stat-label">Occupied Rooms</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #28a745;"><?php echo $free_rooms; ?></div>
                <div class="stat-label">Available Rooms</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #17a2b8;">৳<?php echo number_format($stats['monthly_revenue']); ?></div>
                <div class="stat-label">Monthly Revenue</div>
            </div>
        </div>

        <!-- Legend -->
        <div class="legend">
            <h3 style="margin: 0 0 15px 0;"><i class="fas fa-info-circle"></i> Room Status Legend</h3>
            <div class="legend-item">
                <span class="legend-color" style="background: #dc3545;"></span>
                <strong>Occupied:</strong> Active resident living in room
            </div>
            <div class="legend-item">
                <span class="legend-color" style="background: #ffc107;"></span>
                <strong>Available (Inactive):</strong> Room can be reassigned (previous resident inactive)
            </div>
            <div class="legend-item">
                <span class="legend-color" style="background: #28a745;"></span>
                <strong>Available:</strong> Room is completely free
            </div>
        </div>

        <!-- Rooms Grid -->
        <div class="rooms-grid">
            <?php foreach ($rooms as $room): ?>
            <div class="room-card">
                <div class="room-header" style="background: <?php echo $room['status_color']; ?>;">
                    <i class="fas fa-door-open"></i> Room <?php echo htmlspecialchars($room['room_number']); ?>
                </div>
                <div class="room-body">
                    <?php if ($room['first_name']): ?>
                        <div class="room-info">
                            <strong>Resident:</strong> <?php echo htmlspecialchars($room['first_name'] . ' ' . $room['last_name']); ?>
                        </div>
                        <div class="room-info">
                            <strong>Email:</strong> <?php echo htmlspecialchars($room['email']); ?>
                        </div>
                        <div class="room-info">
                            <strong>Plan:</strong> <?php echo htmlspecialchars($room['plan_name']); ?> (৳<?php echo number_format($room['monthly_fee']); ?>/month)
                        </div>
                        <div class="room-info">
                            <strong>Admitted:</strong> <?php echo date('M j, Y', strtotime($room['admission_date'])); ?>
                        </div>
                        <div class="room-info">
                            <strong>Status:</strong> 
                            <span style="color: <?php echo $room['is_active'] ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo $room['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="room-info" style="text-align: center; color: #28a745; font-size: 1.1rem;">
                            <i class="fas fa-check-circle"></i> Room Available
                        </div>
                        <div class="room-info" style="text-align: center; color: #666;">
                            Ready for new resident
                        </div>
                    <?php endif; ?>
                    
                    <span class="status-badge" style="background: <?php echo $room['status_color']; ?>;">
                        <?php echo $room['room_status']; ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center; margin-top: 40px; padding: 20px;">
            <a href="residents.php" style="display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;">
                <i class="fas fa-users"></i> Manage Residents
            </a>
            <a href="test_validation.php" style="display: inline-block; padding: 12px 24px; background: #17a2b8; color: white; text-decoration: none; border-radius: 5px;">
                <i class="fas fa-vial"></i> Test Validation
            </a>
        </div>
    </div>

    <button class="refresh-btn" onclick="window.location.reload()" title="Refresh Data">
        <i class="fas fa-sync-alt"></i>
    </button>

    <script>
        // Auto-refresh every 5 minutes
        setTimeout(() => {
            window.location.reload();
        }, 300000);
        
        // Add loading animation to refresh button
        document.querySelector('.refresh-btn').addEventListener('click', function() {
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        });
    </script>
</body>
</html>
