<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get resident information
$resident_query = "SELECT u.*, r.*, pp.plan_name, pp.monthly_fee, pp.laundry_limit, pp.cleaning_limit
                   FROM users u 
                   JOIN residents r ON u.id = r.user_id 
                   JOIN payment_plans pp ON r.plan_id = pp.id
                   WHERE u.id = :user_id";
$resident_stmt = $db->prepare($resident_query);
$resident_stmt->bindParam(':user_id', $_SESSION['user_id']);
$resident_stmt->execute();
$resident = $resident_stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    header("Location: ../login.php");
    exit();
}

// Handle personal info update
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $phone = $_POST['phone'];
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    
    try {
        $update_query = "UPDATE users SET first_name = :first_name, last_name = :last_name, 
                        phone = :phone, date_of_birth = :date_of_birth, gender = :gender 
                        WHERE id = :user_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':first_name', $first_name);
        $update_stmt->bindParam(':last_name', $last_name);
        $update_stmt->bindParam(':phone', $phone);
        $update_stmt->bindParam(':date_of_birth', $date_of_birth);
        $update_stmt->bindParam(':gender', $gender);
        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
        
        if ($update_stmt->execute()) {
            $message = "Profile updated successfully!";
            $message_type = "success";
            // Refresh resident data
            $resident_stmt->execute();
            $resident = $resident_stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = "Failed to update profile.";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle password reset
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
    <title>Profile Settings - Maple House</title>
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
            padding: 20px;
        }

        .profile-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .info-card:nth-child(2) { border-left-color: #f093fb; }
        .info-card:nth-child(3) { border-left-color: #43e97b; }
        .info-card:nth-child(4) { border-left-color: #fa709a; }
        .info-card:nth-child(5) { border-left-color: #fee140; }
        .info-card:nth-child(6) { border-left-color: #38f9d7; }

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

        .section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
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

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <!-- Personal Information -->
        <div class="section">
            <h2><i class="fas fa-user"></i> Personal Information</h2>
            
            <?php if ($message && !isset($_POST['reset_password'])): ?>
                <div class="message <?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="info-grid">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($resident['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($resident['last_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email_display">Email (Read-only)</label>
                        <input type="email" id="email_display" value="<?php echo htmlspecialchars($resident['email']); ?>" readonly style="background: #f8f9fa; cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label for="room_display">Room Number (Read-only)</label>
                        <input type="text" id="room_display" value="Room <?php echo htmlspecialchars($resident['room_number']); ?>" readonly style="background: #f8f9fa; cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($resident['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo $resident['date_of_birth']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select name="gender" id="gender" style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 0.95rem;">
                            <option value="Male" <?php echo ($resident['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($resident['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($resident['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary" style="margin-top: 10px;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>

        <!-- Plan Information -->
        <div class="section">
            <h2><i class="fas fa-home"></i> Plan Information</h2>
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label">Plan Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($resident['plan_name']); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Monthly Fee</div>
                    <div class="info-value">৳<?php echo number_format($resident['monthly_fee']); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Laundry Limit</div>
                    <div class="info-value"><?php echo $resident['laundry_limit']; ?> times/month</div>
                </div>
                <div class="info-card">
                    <div class="info-label">Cleaning Limit</div>
                    <div class="info-value"><?php echo $resident['cleaning_limit']; ?> times/month</div>
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
</body>
</html>
