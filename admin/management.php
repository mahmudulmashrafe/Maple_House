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

// Get actual resident count from residents table
$resident_count_query = "SELECT 
                            COUNT(*) as total_residents,
                            COUNT(CASE WHEN u.is_active = 1 THEN 1 END) as active_residents
                         FROM residents r
                         JOIN users u ON r.user_id = u.id";
$resident_count_stmt = $db->prepare($resident_count_query);
$resident_count_stmt->execute();
$resident_stats = $resident_count_stmt->fetch(PDO::FETCH_ASSOC);
$resident_count = $resident_stats['total_residents'];
$active_resident_count = $resident_stats['active_residents'];

// Get user counts for other types
$counts_query = "SELECT 
                    ur.role_name,
                    COUNT(u.id) as user_count,
                    COUNT(CASE WHEN u.is_active = 1 THEN 1 END) as active_count
                 FROM user_roles ur
                 LEFT JOIN users u ON ur.id = u.role_id
                 WHERE ur.role_name IN ('Staff', 'Doctor', 'Chef')
                 GROUP BY ur.id, ur.role_name
                 ORDER BY ur.id";

$counts_stmt = $db->prepare($counts_query);
$counts_stmt->execute();
$user_counts = $counts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent residents
$recent_residents_query = "SELECT u.first_name, u.last_name, u.email, r.room_number, r.admission_date, pp.plan_name
                           FROM residents r
                           JOIN users u ON r.user_id = u.id
                           JOIN payment_plans pp ON r.plan_id = pp.id
                           ORDER BY r.admission_date DESC LIMIT 4";
$recent_residents_stmt = $db->prepare($recent_residents_query);
$recent_residents_stmt->execute();
$recent_residents = $recent_residents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent staff
$recent_staff_query = "SELECT u.first_name, u.last_name, u.email, s.employee_id, s.department, s.position, s.hire_date
                       FROM staff s
                       JOIN users u ON s.user_id = u.id
                       WHERE u.role_id = 5
                       ORDER BY s.hire_date DESC LIMIT 4";
$recent_staff_stmt = $db->prepare($recent_staff_query);
$recent_staff_stmt->execute();
$recent_staff = $recent_staff_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent doctors
$recent_doctors_query = "SELECT u.first_name, u.last_name, u.email, d.license_number, d.specialization, d.consultation_fee
                         FROM doctors d
                         JOIN users u ON d.user_id = u.id
                         ORDER BY d.created_at DESC LIMIT 4";
$recent_doctors_stmt = $db->prepare($recent_doctors_query);
$recent_doctors_stmt->execute();
$recent_doctors = $recent_doctors_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent chefs
$recent_chefs_query = "SELECT u.first_name, u.last_name, u.email, s.employee_id, s.position, s.hire_date
                       FROM staff s
                       JOIN users u ON s.user_id = u.id
                       WHERE u.role_id = 4 AND s.department = 'Kitchen'
                       ORDER BY s.hire_date DESC LIMIT 4";
$recent_chefs_stmt = $db->prepare($recent_chefs_query);
$recent_chefs_stmt->execute();
$recent_chefs = $recent_chefs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create counts array for easy access
$counts = [];
foreach ($user_counts as $count) {
    $counts[$count['role_name']] = $count;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Maple House</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            color: #2c5aa0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .user-type-buttons {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 0;
        }
        
        @media (max-width: 768px) {
            .user-type-buttons {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .user-type-buttons {
                grid-template-columns: 1fr;
            }
        }
        
        .user-type-card {
            background: white;
            border-radius: 12px;
            padding: 8px 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            text-decoration: none;
            color: inherit;
        }
        
        .user-type-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .user-type-card.residents:hover {
            border-color: #667eea;
        }
        
        .user-type-card.staff:hover {
            border-color: #f5576c;
        }
        
        .user-type-card.doctors:hover {
            border-color: #4facfe;
        }
        
        .user-type-card.chefs:hover {
            border-color: #43e97b;
        }
        
        .user-type-card.residents {
            background: white;
            color: #667eea;
            border: 1px solid #667eea;
        }
        
        .user-type-card.staff {
            background: white;
            color: #f5576c;
            border: 1px solid #f5576c;
        }
        
        .user-type-card.doctors {
            background: white;
            color: #4facfe;
            border: 1px solid #4facfe;
        }
        
        .user-type-card.chefs {
            background: white;
            color: #43e97b;
            border: 1px solid #43e97b;
        }
        
        .user-type-card i {
            font-size: 1.3rem;
            margin-bottom: 3px;
            opacity: 0.9;
        }
        
        .user-count {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 1px;
        }
        
        .user-type-name {
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .active-count {
            font-size: 0.7rem;
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
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-header {
            padding: 20px;
            background: white;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .section-header.residents {
            background: white;
            color: #667eea;
        }
        
        .section-header.staff {
            background: white;
            color: #f5576c;
        }
        
        .section-header.doctors {
            background: white;
            color: #4facfe;
        }
        
        .section-header.chefs {
            background: white;
            color: #43e97b;
        }
        
        .section-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }
        
        .section-content {
            padding: 0;
        }
        
        .user-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s ease;
        }
        
        .user-item:last-child {
            border-bottom: none;
        }
        
        .user-item:hover {
            background: #f8f9fa;
        }
        
        .user-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .user-details {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.4;
        }
        
        .user-meta {
            font-size: 0.8rem;
            color: #888;
            margin-top: 5px;
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
        
        .page-wrapper {
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 5px 20px 20px 20px;
        }
        
        .fixed-header {
            flex-shrink: 0;
            margin-bottom: 16px;
        }
        
        .scrollable-content {
            flex: 1;
            overflow-y: auto;
            padding-right: 5px;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Fixed Header with User Type Buttons -->
        <div class="fixed-header">
            <div class="user-type-buttons">
        <a href="residents.php" class="user-type-card residents">
            <i class="fas fa-users"></i>
            <div class="user-count"><?php echo $resident_count; ?></div>
            <div class="user-type-name">Residents</div>
            <div class="active-count"><?php echo $active_resident_count; ?> Active</div>
        </a>
        
        <a href="staff.php" class="user-type-card staff">
            <i class="fas fa-user-tie"></i>
            <div class="user-count"><?php echo $counts['Staff']['user_count'] ?? 0; ?></div>
            <div class="user-type-name">Staff</div>
            <div class="active-count"><?php echo $counts['Staff']['active_count'] ?? 0; ?> Active</div>
        </a>
        
        <a href="doctors.php" class="user-type-card doctors">
            <i class="fas fa-user-md"></i>
            <div class="user-count"><?php echo $counts['Doctor']['user_count'] ?? 0; ?></div>
            <div class="user-type-name">Doctors</div>
            <div class="active-count"><?php echo $counts['Doctor']['active_count'] ?? 0; ?> Active</div>
        </a>
        
        <a href="chefs.php" class="user-type-card chefs">
            <i class="fas fa-utensils"></i>
            <div class="user-count"><?php echo $counts['Chef']['user_count'] ?? 0; ?></div>
            <div class="user-type-name">Chefs</div>
            <div class="active-count"><?php echo $counts['Chef']['active_count'] ?? 0; ?> Active</div>
        </a>
            </div>
        </div>
        
        <!-- Scrollable Content -->
        <div class="scrollable-content">
            <!-- Recent Users Sections -->
            <div class="recent-sections">
        <!-- Recent Residents -->
        <div class="recent-section">
            <div class="section-header residents">
                <i class="fas fa-users"></i>
                <h3>Recent Residents</h3>
            </div>
            <div class="section-content">
                <?php if (!empty($recent_residents)): ?>
                    <?php foreach ($recent_residents as $resident): ?>
                        <div class="user-item">
                            <div class="user-name"><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></div>
                            <div class="user-details">
                                Room: <?php echo htmlspecialchars($resident['room_number']); ?> • 
                                Plan: <?php echo htmlspecialchars($resident['plan_name']); ?>
                            </div>
                            <div class="user-meta">
                                Admitted: <?php echo date('M j, Y', strtotime($resident['admission_date'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="residents.php" class="view-all-btn">
                        <i class="fas fa-arrow-right"></i> View All Residents
                    </a>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No residents found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Staff -->
        <div class="recent-section">
            <div class="section-header staff">
                <i class="fas fa-user-tie"></i>
                <h3>Recent Staff</h3>
            </div>
            <div class="section-content">
                <?php if (!empty($recent_staff)): ?>
                    <?php foreach ($recent_staff as $staff): ?>
                        <div class="user-item">
                            <div class="user-name"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></div>
                            <div class="user-details">
                                <?php echo htmlspecialchars($staff['position']); ?> • 
                                <?php echo htmlspecialchars($staff['department']); ?>
                            </div>
                            <div class="user-meta">
                                ID: <?php echo htmlspecialchars($staff['employee_id']); ?> • 
                                Hired: <?php echo date('M j, Y', strtotime($staff['hire_date'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="staff.php" class="view-all-btn">
                        <i class="fas fa-arrow-right"></i> View All Staff
                    </a>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-tie"></i>
                        <p>No staff found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Doctors -->
        <div class="recent-section">
            <div class="section-header doctors">
                <i class="fas fa-user-md"></i>
                <h3>Recent Doctors</h3>
            </div>
            <div class="section-content">
                <?php if (!empty($recent_doctors)): ?>
                    <?php foreach ($recent_doctors as $doctor): ?>
                        <div class="user-item">
                            <div class="user-name">Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></div>
                            <div class="user-details">
                                <?php echo htmlspecialchars($doctor['specialization']); ?>
                            </div>
                            <div class="user-meta">
                                License: <?php echo htmlspecialchars($doctor['license_number']); ?> • 
                                Fee: ৳<?php echo number_format($doctor['consultation_fee']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="doctors.php" class="view-all-btn">
                        <i class="fas fa-arrow-right"></i> View All Doctors
                    </a>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-md"></i>
                        <p>No doctors found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Chefs -->
        <div class="recent-section">
            <div class="section-header chefs">
                <i class="fas fa-utensils"></i>
                <h3>Recent Chefs</h3>
            </div>
            <div class="section-content">
                <?php if (!empty($recent_chefs)): ?>
                    <?php foreach ($recent_chefs as $chef): ?>
                        <div class="user-item">
                            <div class="user-name"><?php echo htmlspecialchars($chef['first_name'] . ' ' . $chef['last_name']); ?></div>
                            <div class="user-details">
                                <?php echo htmlspecialchars($chef['position']); ?>
                            </div>
                            <div class="user-meta">
                                ID: <?php echo htmlspecialchars($chef['employee_id']); ?> • 
                                Hired: <?php echo date('M j, Y', strtotime($chef['hire_date'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="chefs.php" class="view-all-btn">
                        <i class="fas fa-arrow-right"></i> View All Chefs
                    </a>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-utensils"></i>
                        <p>No chefs found</p>
                    </div>
                <?php endif; ?>
            </div>
            </div>
        </div>
        </div>
    </div>
</body>
</html>
