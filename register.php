<?php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

// Get payment plans
$database = new Database();
$db = $database->getConnection();

$plans_query = "SELECT * FROM payment_plans ORDER BY monthly_fee ASC";
$plans_stmt = $db->prepare($plans_query);
$plans_stmt->execute();
$payment_plans = $plans_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_POST && isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $emergency_contact_phone = trim($_POST['emergency_contact_phone']);
    $plan_id = $_POST['plan_id'];
    $medical_conditions = trim($_POST['medical_conditions']);
    $allergies = trim($_POST['allergies']);
    $family_contact_info = trim($_POST['family_contact_info']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $error_message = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } else {
        // Check if username or email already exists
        $check_query = "SELECT id FROM users WHERE username = :username OR email = :email";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':username', $username);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $error_message = 'Username or email already exists.';
        } else {
            try {
                $db->beginTransaction();
                
                // Get resident role ID
                $role_query = "SELECT id FROM user_roles WHERE role_name = 'Resident'";
                $role_stmt = $db->prepare($role_query);
                $role_stmt->execute();
                $role_id = $role_stmt->fetchColumn();
                
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user
                $user_query = "INSERT INTO users (username, email, password, role_id, first_name, last_name, phone, address, date_of_birth, gender, emergency_contact_name, emergency_contact_phone) 
                               VALUES (:username, :email, :password, :role_id, :first_name, :last_name, :phone, :address, :date_of_birth, :gender, :emergency_contact_name, :emergency_contact_phone)";
                
                $user_stmt = $db->prepare($user_query);
                $user_stmt->bindParam(':username', $username);
                $user_stmt->bindParam(':email', $email);
                $user_stmt->bindParam(':password', $hashed_password);
                $user_stmt->bindParam(':role_id', $role_id);
                $user_stmt->bindParam(':first_name', $first_name);
                $user_stmt->bindParam(':last_name', $last_name);
                $user_stmt->bindParam(':phone', $phone);
                $user_stmt->bindParam(':address', $address);
                $user_stmt->bindParam(':date_of_birth', $date_of_birth);
                $user_stmt->bindParam(':gender', $gender);
                $user_stmt->bindParam(':emergency_contact_name', $emergency_contact_name);
                $user_stmt->bindParam(':emergency_contact_phone', $emergency_contact_phone);
                $user_stmt->execute();
                
                $user_id = $db->lastInsertId();
                
                // Generate room number
                $room_number = 'R' . str_pad($user_id, 3, '0', STR_PAD_LEFT);
                
                // Insert resident
                $resident_query = "INSERT INTO residents (user_id, room_number, plan_id, admission_date, medical_conditions, allergies, family_contact_info, next_payment_due) 
                                   VALUES (:user_id, :room_number, :plan_id, CURDATE(), :medical_conditions, :allergies, :family_contact_info, DATE_ADD(CURDATE(), INTERVAL 1 MONTH))";
                
                $resident_stmt = $db->prepare($resident_query);
                $resident_stmt->bindParam(':user_id', $user_id);
                $resident_stmt->bindParam(':room_number', $room_number);
                $resident_stmt->bindParam(':plan_id', $plan_id);
                $resident_stmt->bindParam(':medical_conditions', $medical_conditions);
                $resident_stmt->bindParam(':allergies', $allergies);
                $resident_stmt->bindParam(':family_contact_info', $family_contact_info);
                $resident_stmt->execute();
                
                $db->commit();
                $success_message = 'Registration successful! You can now login with your credentials.';
                
                // Clear form data
                $_POST = array();
                
            } catch (Exception $e) {
                $db->rollBack();
                $error_message = 'Registration failed. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Maple House</title>
    <link rel="icon" type="image/jpeg" href="images/favicon.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="images/favicon.jpg">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="auth-body">
    <div class="auth-container" style="max-width: 600px;">
        <div class="auth-card">
            <div class="auth-header">
                <h2><i class="fas fa-home"></i> Maple House</h2>
                <p>Register as a new resident</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <form class="auth-form register-form" method="POST" action="">
                <h3 style="color: #2c5aa0; margin-bottom: 20px;">Personal Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">
                            <i class="fas fa-user"></i>
                            First Name *
                        </label>
                        <input type="text" id="first_name" name="first_name" required 
                               value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">
                            <i class="fas fa-user"></i>
                            Last Name *
                        </label>
                        <input type="text" id="last_name" name="last_name" required 
                               value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">
                            <i class="fas fa-at"></i>
                            Username *
                        </label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            Email *
                        </label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i>
                            Password *
                        </label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i>
                            Confirm Password *
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">
                            <i class="fas fa-phone"></i>
                            Phone Number
                        </label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_of_birth">
                            <i class="fas fa-calendar"></i>
                            Date of Birth
                        </label>
                        <input type="date" id="date_of_birth" name="date_of_birth" 
                               value="<?php echo isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="gender">
                        <i class="fas fa-venus-mars"></i>
                        Gender
                    </label>
                    <select id="gender" name="gender">
                        <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="address">
                        <i class="fas fa-map-marker-alt"></i>
                        Address
                    </label>
                    <textarea id="address" name="address" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
                
                <h3 style="color: #2c5aa0; margin: 30px 0 20px;">Emergency Contact</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="emergency_contact_name">
                            <i class="fas fa-user-friends"></i>
                            Emergency Contact Name
                        </label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" 
                               value="<?php echo isset($_POST['emergency_contact_name']) ? htmlspecialchars($_POST['emergency_contact_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="emergency_contact_phone">
                            <i class="fas fa-phone-alt"></i>
                            Emergency Contact Phone
                        </label>
                        <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" 
                               value="<?php echo isset($_POST['emergency_contact_phone']) ? htmlspecialchars($_POST['emergency_contact_phone']) : ''; ?>">
                    </div>
                </div>
                
                <h3 style="color: #2c5aa0; margin: 30px 0 20px;">Care Plan Selection</h3>
                
                <div class="plan-selection">
                    <?php foreach ($payment_plans as $plan): ?>
                        <div class="plan-option" onclick="selectPlan(<?php echo $plan['id']; ?>)">
                            <input type="radio" name="plan_id" value="<?php echo $plan['id']; ?>" id="plan_<?php echo $plan['id']; ?>" 
                                   <?php echo (isset($_POST['plan_id']) && $_POST['plan_id'] == $plan['id']) ? 'checked' : ''; ?>>
                            <h4><?php echo htmlspecialchars($plan['plan_name']); ?></h4>
                            <div class="price">
                                <?php echo $plan['monthly_fee'] > 0 ? '৳' . number_format($plan['monthly_fee']) . '/month' : 'Free'; ?>
                            </div>
                            <div class="features">
                                <?php if ($plan['laundry_limit'] == -1): ?>
                                    Unlimited laundry
                                <?php elseif ($plan['laundry_limit'] > 0): ?>
                                    <?php echo $plan['laundry_limit']; ?> free laundry items
                                <?php else: ?>
                                    No free laundry
                                <?php endif; ?>
                                <br>
                                <?php if ($plan['cleaning_limit'] == -1): ?>
                                    Daily cleaning
                                <?php elseif ($plan['cleaning_limit'] > 0): ?>
                                    <?php echo $plan['cleaning_limit']; ?> free cleanings
                                <?php else: ?>
                                    No free cleaning
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <h3 style="color: #2c5aa0; margin: 30px 0 20px;">Medical Information</h3>
                
                <div class="form-group">
                    <label for="medical_conditions">
                        <i class="fas fa-notes-medical"></i>
                        Medical Conditions
                    </label>
                    <textarea id="medical_conditions" name="medical_conditions" rows="3" 
                              placeholder="List any existing medical conditions"><?php echo isset($_POST['medical_conditions']) ? htmlspecialchars($_POST['medical_conditions']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="allergies">
                        <i class="fas fa-exclamation-triangle"></i>
                        Allergies
                    </label>
                    <textarea id="allergies" name="allergies" rows="2" 
                              placeholder="List any allergies"><?php echo isset($_POST['allergies']) ? htmlspecialchars($_POST['allergies']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="family_contact_info">
                        <i class="fas fa-users"></i>
                        Family Contact Information
                    </label>
                    <textarea id="family_contact_info" name="family_contact_info" rows="3" 
                              placeholder="Family members contact details"><?php echo isset($_POST['family_contact_info']) ? htmlspecialchars($_POST['family_contact_info']) : ''; ?></textarea>
                </div>
                
                <button type="submit" name="register" class="btn btn-primary btn-full">
                    <i class="fas fa-user-plus"></i>
                    Register
                </button>
            </form>
            
            <div class="auth-links">
                <p>Already have an account? <a href="login.php">Login here</a></p>
                <p><a href="index.php">← Back to Home</a></p>
            </div>
        </div>
    </div>
    
    <script>
        function selectPlan(planId) {
            // Remove selected class from all plans
            document.querySelectorAll('.plan-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked plan
            document.querySelector('#plan_' + planId).closest('.plan-option').classList.add('selected');
            
            // Check the radio button
            document.querySelector('#plan_' + planId).checked = true;
        }
        
        // Set initial selection if exists
        document.addEventListener('DOMContentLoaded', function() {
            const checkedPlan = document.querySelector('input[name="plan_id"]:checked');
            if (checkedPlan) {
                checkedPlan.closest('.plan-option').classList.add('selected');
            }
        });
    </script>
</body>
</html>
