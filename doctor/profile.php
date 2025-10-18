<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get doctor information
$doctor_query = "SELECT d.*, u.first_name, u.last_name, u.email, u.phone, u.date_of_birth, u.gender, u.address 
                 FROM doctors d 
                 JOIN users u ON d.user_id = u.id 
                 WHERE d.user_id = :user_id";
$doctor_stmt = $db->prepare($doctor_query);
$doctor_stmt->bindParam(':user_id', $_SESSION['user_id']);
$doctor_stmt->execute();
$doctor = $doctor_stmt->fetch(PDO::FETCH_ASSOC);

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
                    $message = "Password updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Failed to update password.";
                    $message_type = "error";
                }
            } else {
                $message = "New password must be at least 6 characters long.";
                $message_type = "error";
            }
        } else {
            $message = "New passwords do not match.";
            $message_type = "error";
        }
    } else {
        $message = "Current password is incorrect.";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Doctor Portal</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            text-align: center;
            position: fixed;
            top: 0;
            left: 20px;
            right: 20px;
            z-index: 100;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
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
            color: #667eea;
            flex-shrink: 0;
        }
        
        .profile-header-text {
            text-align: left;
        }
        
        .profile-header h1 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .profile-header p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .profile-content {
            padding: 20px;
        }
        
        .section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .section h2 {
            color: #2c3e50;
            font-size: 1.3rem;
            margin-bottom: 20px;
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
            background: white;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #667eea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
        }
        
        .info-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .info-item:nth-child(1) { border-left-color: #667eea; }
        .info-item:nth-child(2) { border-left-color: #f093fb; }
        .info-item:nth-child(3) { border-left-color: #43e97b; }
        .info-item:nth-child(4) { border-left-color: #fa709a; }
        .info-item:nth-child(5) { border-left-color: #fee140; }
        
        .info-label {
            font-size: 0.75rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 1.1rem;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.9rem;
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
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
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
            <i class="fas fa-user-md"></i>
        </div>
        <div class="profile-header-text">
            <h1>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h1>
            <p><?php echo htmlspecialchars($doctor['specialization']); ?></p>
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
                        <div class="info-value"><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($doctor['email']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <div class="info-value"><?php echo htmlspecialchars($doctor['phone'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value"><?php echo $doctor['date_of_birth'] ? date('M d, Y', strtotime($doctor['date_of_birth'])) : 'N/A'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Gender</div>
                        <div class="info-value"><?php echo htmlspecialchars($doctor['gender'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Professional Information -->
            <div class="section">
                <h2><i class="fas fa-briefcase"></i> Professional Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Role</div>
                        <div class="info-value">Doctor</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Specialization</div>
                        <div class="info-value"><?php echo htmlspecialchars($doctor['specialization'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">License Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($doctor['license_number'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Experience</div>
                        <div class="info-value"><?php echo htmlspecialchars($doctor['experience_years'] ?? '0'); ?> Years</div>
                    </div>
                </div>
            </div>
            
            <!-- Address Information -->
            <div class="section">
                <h2><i class="fas fa-map-marker-alt"></i> Address Information</h2>
                <div class="info-grid">
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <div class="info-label">Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($doctor['address'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Password Reset -->
            <div class="section">
                <h2><i class="fas fa-lock"></i> Reset Password</h2>
                
                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" name="current_password" id="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" name="new_password" id="new_password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required minlength="6">
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
