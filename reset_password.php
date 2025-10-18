<?php
session_start();
require_once 'config/database.php';

$message = '';
$message_type = '';
$step = 'email'; // email, verify, reset

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    // Step 1: Verify email and send reset code
    if (isset($_POST['send_code'])) {
        $email = trim($_POST['email']);
        
        $query = "SELECT id, username, first_name FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Generate 6-digit code
            $reset_code = sprintf("%06d", mt_rand(1, 999999));
            $_SESSION['reset_code'] = $reset_code;
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['code_expiry'] = time() + 600; // 10 minutes
            
            // Send email with reset code
            $to = $email;
            $subject = "Password Reset Code - Maple House";
            
            $email_message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                    .code-box { background: white; border: 3px dashed #667eea; padding: 20px; text-align: center; margin: 20px 0; border-radius: 10px; }
                    .code { font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 5px; }
                    .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                    .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🏠 Maple House</h1>
                        <h2>Password Reset Request</h2>
                    </div>
                    <div class='content'>
                        <p>Hello <strong>" . htmlspecialchars($user['first_name']) . "</strong>,</p>
                        
                        <p>We received a request to reset your password. Use the code below to complete the password reset process:</p>
                        
                        <div class='code-box'>
                            <p style='margin: 0; color: #666; font-size: 14px;'>Your Reset Code</p>
                            <div class='code'>" . $reset_code . "</div>
                        </div>
                        
                        <div class='warning'>
                            <strong>⚠️ Important:</strong>
                            <ul style='margin: 10px 0 0 0;'>
                                <li>This code is valid for <strong>10 minutes</strong></li>
                                <li>Do not share this code with anyone</li>
                                <li>If you didn't request this, please ignore this email</li>
                            </ul>
                        </div>
                        
                        <p>To reset your password, return to the password reset page and enter this code.</p>
                        
                        <p style='margin-top: 30px;'>Best regards,<br><strong>Maple House Team</strong></p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated email. Please do not reply to this message.</p>
                        <p>&copy; 2024 Maple House. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            // Email headers
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Maple House <noreply@maplehouse.com>" . "\r\n";
            $headers .= "Reply-To: support@maplehouse.com" . "\r\n";
            
            // Try to send email
            try {
                $email_sent = @mail($to, $subject, $email_message, $headers);
            } catch (Exception $e) {
                $email_sent = false;
            }
            
            // Always show code on localhost (email may not work)
            if ($email_sent) {
                $message = "A reset code has been sent to <strong>" . htmlspecialchars($email) . "</strong>. Please check your inbox.<br><br>For testing purposes, your code is: <strong>$reset_code</strong> (Valid for 10 minutes)";
            } else {
                $message = "Your reset code is: <strong>$reset_code</strong> (Valid for 10 minutes)<br><small>Note: Email service is not configured on localhost.</small>";
            }
            $message_type = 'success';
            $step = 'verify';
        } else {
            $message = 'Email address not found.';
            $message_type = 'error';
        }
    }
    
    // Step 2: Verify code
    elseif (isset($_POST['verify_code'])) {
        $entered_code = trim($_POST['code']);
        
        if (!isset($_SESSION['reset_code']) || time() > $_SESSION['code_expiry']) {
            $message = 'Reset code has expired. Please request a new one.';
            $message_type = 'error';
            $step = 'email';
            unset($_SESSION['reset_code'], $_SESSION['reset_email'], $_SESSION['reset_user_id'], $_SESSION['code_expiry']);
        } elseif ($entered_code === $_SESSION['reset_code']) {
            $message = 'Code verified! Please enter your new password.';
            $message_type = 'success';
            $step = 'reset';
        } else {
            $message = 'Invalid code. Please try again.';
            $message_type = 'error';
            $step = 'verify';
        }
    }
    
    // Step 3: Reset password
    elseif (isset($_POST['reset_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $message = 'Passwords do not match.';
            $message_type = 'error';
            $step = 'reset';
        } elseif (strlen($new_password) < 6) {
            $message = 'Password must be at least 6 characters long.';
            $message_type = 'error';
            $step = 'reset';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $update_query = "UPDATE users SET password = :password WHERE id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':password', $hashed_password);
            $update_stmt->bindParam(':user_id', $_SESSION['reset_user_id']);
            
            if ($update_stmt->execute()) {
                $message = 'Password reset successfully! You can now login with your new password.';
                $message_type = 'success';
                unset($_SESSION['reset_code'], $_SESSION['reset_email'], $_SESSION['reset_user_id'], $_SESSION['code_expiry']);
                
                // Redirect to login after 3 seconds
                header("refresh:3;url=login.php");
            } else {
                $message = 'Failed to reset password. Please try again.';
                $message_type = 'error';
                $step = 'reset';
            }
        }
    }
} elseif (isset($_SESSION['reset_code'])) {
    // If session exists, determine step
    if (time() > $_SESSION['code_expiry']) {
        unset($_SESSION['reset_code'], $_SESSION['reset_email'], $_SESSION['reset_user_id'], $_SESSION['code_expiry']);
        $step = 'email';
    } else {
        $step = 'verify';
    }
}

// Reset session if user clicks back to start over
if (isset($_GET['reset'])) {
    unset($_SESSION['reset_code'], $_SESSION['reset_email'], $_SESSION['reset_user_id'], $_SESSION['code_expiry']);
    $step = 'email';
    header('Location: reset_password.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Maple House</title>
    <link rel="icon" type="image/jpeg" href="images/favicon.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="images/favicon.jpg">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="auth-body">
    <div class="auth-bg auth-bg-1" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-image: url('images/bg-1.jpg'); background-size: cover; background-position: center; z-index: -2; filter: blur(3px);"></div>
    <div class="auth-bg auth-bg-2" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-image: url('images/bg-2.jpg'); background-size: cover; background-position: center; z-index: -2; filter: blur(3px); opacity: 0;"></div>
    <div class="auth-bg auth-bg-3" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-image: url('images/bg-3.jpg'); background-size: cover; background-position: center; z-index: -2; filter: blur(3px); opacity: 0;"></div>
    <div class="auth-container" style="position: relative; z-index: 10;">
        <div class="auth-card" style="background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 20px; padding: 40px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);">
            <div class="auth-header">
                <h2><i class="fas fa-key"></i> Reset Password</h2>
                <p>
                    <?php 
                    if ($step === 'email') echo 'Enter your email to receive a reset code';
                    elseif ($step === 'verify') echo 'Enter the code sent to your email';
                    elseif ($step === 'reset') echo 'Enter your new password';
                    ?>
                </p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($step === 'email'): ?>
                <!-- Step 1: Enter Email -->
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            Email Address
                        </label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <button type="submit" name="send_code" class="btn btn-primary btn-full">
                        <i class="fas fa-paper-plane"></i>
                        Send Reset Code
                    </button>
                </form>
                
            <?php elseif ($step === 'verify'): ?>
                <!-- Step 2: Verify Code -->
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="code">
                            <i class="fas fa-shield-alt"></i>
                            Reset Code
                        </label>
                        <input type="text" id="code" name="code" required maxlength="6" pattern="[0-9]{6}" placeholder="Enter 6-digit code">
                    </div>
                    
                    <button type="submit" name="verify_code" class="btn btn-primary btn-full">
                        <i class="fas fa-check"></i>
                        Verify Code
                    </button>
                </form>
                
            <?php elseif ($step === 'reset'): ?>
                <!-- Step 3: Reset Password -->
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="new_password">
                            <i class="fas fa-lock"></i>
                            New Password
                        </label>
                        <input type="password" id="new_password" name="new_password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i>
                            Confirm Password
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                    
                    <button type="submit" name="reset_password" class="btn btn-primary btn-full">
                        <i class="fas fa-save"></i>
                        Reset Password
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="auth-links">
                <?php if ($step !== 'email'): ?>
                    <p><a href="reset_password.php?reset=1">🔄 Start Over</a></p>
                <?php endif; ?>
                <p><a href="login.php">← Back to Login</a></p>
            </div>
        </div>
    </div>
</body>
</html>
