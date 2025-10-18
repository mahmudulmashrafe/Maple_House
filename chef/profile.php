<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a chef
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Chef') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get chef information
$chef_query = "SELECT c.*, u.first_name, u.last_name, u.email, u.phone, u.date_of_birth, u.gender, u.address 
               FROM chefs c 
               JOIN users u ON c.user_id = u.id 
               WHERE c.user_id = :user_id";
$chef_stmt = $db->prepare($chef_query);
$chef_stmt->bindParam(':user_id', $_SESSION['user_id']);
$chef_stmt->execute();
$chef = $chef_stmt->fetch(PDO::FETCH_ASSOC);

// Handle password reset
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $user_query = "SELECT password FROM users WHERE id = :user_id";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $user_stmt->execute();
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = :password WHERE id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':password', $hashed_password);
                $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if ($update_stmt->execute()) {
                    $message = 'Password updated successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to update password.';
                    $message_type = 'error';
                }
            } else {
                $message = 'New password must be at least 6 characters long.';
                $message_type = 'error';
            }
        } else {
            $message = 'New passwords do not match.';
            $message_type = 'error';
        }
    } else {
        $message = 'Current password is incorrect.';
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background: #f8f9fa;
            padding: 0;
            padding-top: 100px;
        }
        
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            padding: 15px 30px;
            text-align: center;
            position: fixed;
            top: 0;
            left: 20px;
            right: 20px;
            z-index: 100;
            box-shadow: 0 4px 15px rgba(67, 233, 123, 0.3);
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
            color: #43e97b;
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
            margin: 5px 0 0 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .profile-content {
            padding: 20px;
        }
        
        .section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .section h2 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            font-size: 1.4rem;
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
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #43e97b;
            box-shadow: 0 0 0 3px rgba(67, 233, 123, 0.1);
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 233, 123, 0.4);
        }
        
        .message {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>
    <div class="profile-header">
        <div class="profile-avatar">
            <i class="fas fa-user-chef"></i>
        </div>
        <div class="profile-header-text">
            <h1><?php echo htmlspecialchars($chef['first_name'] . ' ' . $chef['last_name']); ?></h1>
            <p>Chef - Kitchen Operations</p>
        </div>
    </div>
    
    <div class="profile-container">
        <div class="profile-content">
            <!-- Personal Information -->
            <div class="section">
                <h2><i class="fas fa-user"></i> Personal Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($chef['first_name'] . ' ' . $chef['last_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($chef['email']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <div class="info-value"><?php echo htmlspecialchars($chef['phone'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value"><?php echo $chef['date_of_birth'] ? date('M d, Y', strtotime($chef['date_of_birth'])) : 'N/A'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Gender</div>
                        <div class="info-value"><?php echo htmlspecialchars($chef['gender'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Professional Information -->
            <div class="section">
                <h2><i class="fas fa-briefcase"></i> Professional Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Role</div>
                        <div class="info-value">Chef</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Shift Hours</div>
                        <div class="info-value"><?php echo htmlspecialchars($chef['shift_hours'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Specialization</div>
                        <div class="info-value"><?php echo htmlspecialchars($chef['specialization'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Experience</div>
                        <div class="info-value"><?php echo htmlspecialchars($chef['experience_years'] ?? '0'); ?> Years</div>
                    </div>
                </div>
            </div>
            
            <!-- Address Information -->
            <div class="section">
                <h2><i class="fas fa-map-marker-alt"></i> Address Information</h2>
                <div class="info-grid">
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <div class="info-label">Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($chef['address'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Password Reset -->
            <div class="section">
                <h2><i class="fas fa-lock"></i> Reset Password</h2>
                
                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
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
                    
                    <button type="submit" name="reset_password" class="btn btn-primary">
                        <i class="fas fa-key"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
