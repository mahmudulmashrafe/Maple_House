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

$success_message = '';
$error_message = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All fields are required";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match";
    } elseif (strlen($new_password) < 6) {
        $error_message = "New password must be at least 6 characters long";
    } else {
        // Verify current password
        $verify_query = "SELECT password FROM users WHERE id = :user_id";
        $verify_stmt = $db->prepare($verify_query);
        $verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $verify_stmt->execute();
        $user = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($current_password, $user['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = :password WHERE id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':password', $hashed_password);
            $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
            
            if ($update_stmt->execute()) {
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Failed to update password";
            }
        } else {
            $error_message = "Current password is incorrect";
        }
    }
}

// Get staff information
$staff_query = "SELECT s.*, u.first_name, u.last_name, u.email, u.phone, u.address, u.date_of_birth, u.gender
                FROM staff s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.user_id = :user_id";
$staff_stmt = $db->prepare($staff_query);
$staff_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_stmt->execute();
$staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Maple House</title>
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
            padding-top: 100px;
        }
        
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 15px 30px;
            text-align: center;
            position: fixed;
            top: 0;
            left: 20px;
            right: 20px;
            z-index: 100;
            box-shadow: 0 4px 15px rgba(240, 147, 251, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            border-radius: 0 0 15px 15px;
            margin: 0 auto;
            max-width: 1000px;
        }
        
        .profile-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #f093fb;
            flex-shrink: 0;
        }
        
        .profile-header-text {
            text-align: left;
        }
        
        .profile-header h1 {
            margin: 0;
            font-size: 1.3rem;
        }
        
        .profile-header p {
            opacity: 0.9;
            font-size: 0.9rem;
            margin: 2px 0 0;
        }
        
        .profile-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin: 20px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.3rem;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            padding: 18px;
            background: white;
            border-radius: 12px;
            border-left: 5px solid #667eea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
        }
        
        .info-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .info-grid .info-item:nth-child(1) { border-left-color: #667eea; }
        .info-grid .info-item:nth-child(2) { border-left-color: #f093fb; }
        .info-grid .info-item:nth-child(3) { border-left-color: #4facfe; }
        .info-grid .info-item:nth-child(4) { border-left-color: #43e97b; }
        .info-grid .info-item:nth-child(5) { border-left-color: #fa709a; }
        .info-grid .info-item:nth-child(6) { border-left-color: #f5576c; }
        
        .info-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 1.1rem;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .password-form {
            max-width: 500px;
        }
    </style>
</head>
<body>
    <div class="profile-header">
        <div class="profile-avatar">
            <i class="fas fa-user"></i>
        </div>
        <div class="profile-header-text">
            <h1><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></h1>
            <p><?php echo htmlspecialchars($staff['position']); ?> - <?php echo htmlspecialchars($staff['department']); ?></p>
        </div>
    </div>
    
    <div class="profile-container">
        <div class="profile-content">
            <!-- Personal Information -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-user-circle"></i>
                    Personal Information
                </h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($staff['email']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <div class="info-value"><?php echo htmlspecialchars($staff['phone']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value"><?php echo date('M j, Y', strtotime($staff['date_of_birth'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Gender</div>
                        <div class="info-value"><?php echo htmlspecialchars($staff['gender']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($staff['address']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Employee ID</div>
                        <div class="info-value"><?php echo htmlspecialchars($staff['employee_id']); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Employment Information -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-briefcase"></i>
                    Employment Information
                </h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Department</div>
                        <div class="info-value"><?php echo htmlspecialchars($staff['department']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Position</div>
                        <div class="info-value"><?php echo htmlspecialchars($staff['position']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Hire Date</div>
                        <div class="info-value"><?php echo date('M j, Y', strtotime($staff['hire_date'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Shift Hours</div>
                        <div class="info-value"><?php echo htmlspecialchars($staff['shift_hours']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Monthly Salary</div>
                        <div class="info-value">৳<?php echo number_format($staff['salary'], 2); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-lock"></i>
                    Change Password
                </h2>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="password-form">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
