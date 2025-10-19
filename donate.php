<?php
session_start();
require_once 'config/database.php';

$success_message = '';
$error_message = '';

if ($_POST && isset($_POST['donate'])) {
    $donor_name = trim($_POST['donor_name']);
    $donor_email = trim($_POST['donor_email']);
    $donor_phone = trim($_POST['donor_phone']);
    $amount = floatval($_POST['amount']);
    $purpose = trim($_POST['purpose']);
    $payment_method = $_POST['payment_method'];
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    $custom_amount = floatval($_POST['custom_amount']);
    
    // Use custom amount if provided
    if ($custom_amount > 0) {
        $amount = $custom_amount;
    }
    
    // Validation
    if ($amount <= 0) {
        $error_message = 'Please enter a valid donation amount.';
    } elseif (!$is_anonymous && (empty($donor_name) || empty($donor_email))) {
        $error_message = 'Please provide your name and email for non-anonymous donations.';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Generate transaction ID
            $transaction_id = 'TXN' . time() . rand(100, 999);
            
            // Insert donation
            $donation_query = "INSERT INTO donations (donor_name, donor_email, donor_phone, amount, purpose, payment_method, transaction_id, donation_date, is_anonymous, is_verified) 
                               VALUES (:donor_name, :donor_email, :donor_phone, :amount, :purpose, :payment_method, :transaction_id, CURDATE(), :is_anonymous, 0)";
            
            $donation_stmt = $db->prepare($donation_query);
            
            // Handle anonymous donations properly
            $bind_donor_name = $is_anonymous ? null : $donor_name;
            $bind_donor_email = $is_anonymous ? null : $donor_email;
            $bind_donor_phone = $is_anonymous ? null : $donor_phone;
            
            $donation_stmt->bindParam(':donor_name', $bind_donor_name);
            $donation_stmt->bindParam(':donor_email', $bind_donor_email);
            $donation_stmt->bindParam(':donor_phone', $bind_donor_phone);
            $donation_stmt->bindParam(':amount', $amount);
            $donation_stmt->bindParam(':purpose', $purpose);
            $donation_stmt->bindParam(':payment_method', $payment_method);
            $donation_stmt->bindParam(':transaction_id', $transaction_id);
            $donation_stmt->bindParam(':is_anonymous', $is_anonymous);
            $donation_stmt->execute();
            
            $success_message = "Thank you for your generous donation of ৳" . number_format($amount) . "! Your transaction ID is: " . $transaction_id . ". Your donation will be verified and processed soon.";
            
            // Clear form data
            $_POST = array();
            
        } catch (Exception $e) {
            $error_message = 'Donation submission failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donate - Maple House</title>
    <link rel="icon" type="image/jpeg" href="images/favicon.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="images/favicon.jpg">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .donation-form-section {
            position: relative;
            overflow: hidden;
        }
        .donation-bg-1, .donation-bg-2, .donation-bg-3 {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            filter: blur(3px);
            z-index: 0;
        }
        .donation-bg-1 {
            background-image: url('images/bg-1.jpg');
            opacity: 1;
        }
        .donation-bg-2 {
            background-image: url('images/bg-2.jpg');
            opacity: 0;
        }
        .donation-bg-3 {
            background-image: url('images/bg-3.jpg');
            opacity: 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <h2><i class="fas fa-home"></i> Maple House</h2>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="index.php#about">About</a></li>
                <li><a href="index.php#services">Services</a></li>
                <li><a href="index.php#contact">Contact</a></li>
                <li><a href="donate.php" class="donate-btn">Donate</a></li>
                <li><a href="login.php" class="login-btn">Login</a></li>
            </ul>
        </div>
    </nav>

    <!-- Donation Hero and Form Section with Background -->
    <section class="donation-form-section">
        <!-- Background Images -->
        <div class="donation-bg-1"></div>
        <div class="donation-bg-2"></div>
        <div class="donation-bg-3"></div>
        
        <!-- Donation Hero -->
        <div class="donation-hero" style="color: white; padding: 120px 0 40px; text-align: center; position: relative; z-index: 10;">
            <div class="container">
                <h1 style="font-size: 3rem; margin-bottom: 20px; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">Make a Difference</h1>
                <p style="font-size: 1.2rem; max-width: 600px; margin: 0 auto; text-shadow: 1px 1px 3px rgba(0,0,0,0.5);">Your generous donation helps us provide better care, facilities, and services to our elderly residents. Every contribution makes a meaningful impact.</p>
            </div>
        </div>

        <!-- Donation Form -->
        <div style="padding: 40px 0 60px; position: relative; z-index: 10;">
            <div class="container">
                <div style="max-width: 800px; margin: 0 auto;">
                    <div class="auth-card" style="background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 20px; padding: 40px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);">
                        <div class="auth-header">
                            <h2 style="color: white;"><i class="fas fa-heart"></i> Make a Donation</h2>
                            <p style="color: white;">Help us continue providing excellent care for our residents</p>
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
                        
                        <form class="auth-form" method="POST" action="" style="color: white;">
                            <!-- Donation Amount -->
                            <div class="form-group">
                                <label style="color: white;"><i class="fas fa-dollar-sign"></i> Donation Amount</label>
                                <div class="amount-options" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px;">
                                    <div class="amount-option" onclick="selectAmount(1000)">
                                        <input type="radio" name="amount" value="1000" id="amount_1000">
                                        <label for="amount_1000">৳1,000</label>
                                    </div>
                                    <div class="amount-option" onclick="selectAmount(2500)">
                                        <input type="radio" name="amount" value="2500" id="amount_2500">
                                        <label for="amount_2500">৳2,500</label>
                                    </div>
                                    <div class="amount-option" onclick="selectAmount(5000)">
                                        <input type="radio" name="amount" value="5000" id="amount_5000">
                                        <label for="amount_5000">৳5,000</label>
                                    </div>
                                    <div class="amount-option" onclick="selectAmount(10000)">
                                        <input type="radio" name="amount" value="10000" id="amount_10000">
                                        <label for="amount_10000">৳10,000</label>
                                    </div>
                                    <div class="amount-option" onclick="selectAmount(25000)">
                                        <input type="radio" name="amount" value="25000" id="amount_25000">
                                        <label for="amount_25000">৳25,000</label>
                                    </div>
                                    <div class="amount-option" onclick="selectCustom()">
                                        <input type="radio" name="amount" value="custom" id="amount_custom">
                                        <label for="amount_custom">Custom</label>
                                    </div>
                                </div>
                                <input type="number" name="custom_amount" id="custom_amount" placeholder="Enter custom amount" 
                                       style="display: none; margin-top: 10px; color: black;" min="100" step="50">
                            </div>
                            
                            <!-- Purpose -->
                            <div class="form-group">
                                <label for="purpose" style="color: white;">
                                    <i class="fas fa-bullseye"></i>
                                    Donation Purpose
                                </label>
                                <select id="purpose" name="purpose" required style="color: black;">
                                    <option value="General Support">General Support</option>
                                    <option value="Medical Equipment">Medical Equipment</option>
                                    <option value="Food & Nutrition">Food & Nutrition</option>
                                    <option value="Recreation & Activities">Recreation & Activities</option>
                                    <option value="Infrastructure Development">Infrastructure Development</option>
                                    <option value="Emergency Fund">Emergency Fund</option>
                                    <option value="Staff Training">Staff Training</option>
                                </select>
                            </div>
                            
                            <!-- Payment Method -->
                            <div class="form-group">
                                <label for="payment_method" style="color: white;">
                                    <i class="fas fa-credit-card"></i>
                                    Payment Method
                                </label>
                                <select id="payment_method" name="payment_method" required style="color: black;">
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Online Payment">Online Payment (bKash/Nagad/Rocket)</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Cheque">Cheque</option>
                                </select>
                            </div>
                            
                            <!-- Anonymous Donation -->
                            <div class="form-group">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; color: white;">
                                    <input type="checkbox" name="is_anonymous" id="is_anonymous" onchange="toggleAnonymous()">
                                    <i class="fas fa-user-secret"></i>
                                    Make this an anonymous donation
                                </label>
                            </div>
                            
                            <div id="donor_info">
                                <h3 style="color: white; margin: 30px 0 20px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">Donor Information</h3>
                                
                                <div class="form-group">
                                    <label for="donor_name" style="color: white;">
                                        <i class="fas fa-user"></i>
                                        Full Name *
                                    </label>
                                    <input type="text" id="donor_name" name="donor_name" style="color: black;"
                                           value="<?php echo isset($_POST['donor_name']) ? htmlspecialchars($_POST['donor_name']) : ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="donor_email" style="color: white;">
                                        <i class="fas fa-envelope"></i>
                                        Email Address *
                                    </label>
                                    <input type="email" id="donor_email" name="donor_email" style="color: black;"
                                           value="<?php echo isset($_POST['donor_email']) ? htmlspecialchars($_POST['donor_email']) : ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="donor_phone" style="color: white;">
                                        <i class="fas fa-phone"></i>
                                        Phone Number
                                    </label>
                                    <input type="tel" id="donor_phone" name="donor_phone" style="color: black;"
                                           value="<?php echo isset($_POST['donor_phone']) ? htmlspecialchars($_POST['donor_phone']) : ''; ?>">
                                </div>
                            </div>
                            
                            <button type="submit" name="donate" class="btn btn-primary btn-full">
                                <i class="fas fa-heart"></i>
                                Donate Now
                            </button>
                        </form>
                        
                        <!-- Payment Information -->
                        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.2);">
                            <h4 style="color: white; margin-bottom: 15px; text-shadow: 1px 1px 3px rgba(0,0,0,0.3);">Payment Information</h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; font-size: 0.9rem; color: white;">
                                <div>
                                    <strong>Bank Transfer:</strong><br>
                                    Account: Maple House<br>
                                    A/C No: 1234567890<br>
                                    Bank: ABC Bank Ltd.
                                </div>
                                <div>
                                    <strong>bKash:</strong><br>
                                    Personal: 01234567890<br>
                                    <strong>Nagad:</strong><br>
                                    Personal: 01234567890
                                </div>
                                <div>
                                    <strong>Cash/Cheque:</strong><br>
                                    Visit our office at:<br>
                                    123 Care Street, Dhaka<br>
                                    Office Hours: 9 AM - 5 PM
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Impact Section -->
    <section style="padding: 80px 0; background: white;">
        <div class="container">
            <h2 style="text-align: center; color: #2c5aa0; margin-bottom: 50px;">Your Impact</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px;">
                <div style="text-align: center; padding: 30px; background: #f8f9fa; border-radius: 15px;">
                    <i class="fas fa-user-md" style="font-size: 3rem; color: #27ae60; margin-bottom: 20px;"></i>
                    <h3>Medical Care</h3>
                    <p>৳5,000 can provide medical supplies for 10 residents for a month</p>
                </div>
                <div style="text-align: center; padding: 30px; background: #f8f9fa; border-radius: 15px;">
                    <i class="fas fa-utensils" style="font-size: 3rem; color: #f39c12; margin-bottom: 20px;"></i>
                    <h3>Nutrition</h3>
                    <p>৳2,500 can provide nutritious meals for one resident for a month</p>
                </div>
                <div style="text-align: center; padding: 30px; background: #f8f9fa; border-radius: 15px;">
                    <i class="fas fa-gamepad" style="font-size: 3rem; color: #9b59b6; margin-bottom: 20px;"></i>
                    <h3>Recreation</h3>
                    <p>৳1,000 can sponsor recreational activities for all residents</p>
                </div>
                <div style="text-align: center; padding: 30px; background: #f8f9fa; border-radius: 15px;">
                    <i class="fas fa-home" style="font-size: 3rem; color: #e74c3c; margin-bottom: 20px;"></i>
                    <h3>Maintenance</h3>
                    <p>৳10,000 can help maintain and improve our facilities</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Maple House</h3>
                    <p>Providing compassionate care and a loving home for our elderly community.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="index.php#about">About</a></li>
                        <li><a href="index.php#services">Services</a></li>
                        <li><a href="donate.php">Donate</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact Info</h4>
                    <p><i class="fas fa-phone"></i> +880 1234 567890</p>
                    <p><i class="fas fa-envelope"></i> info@maplehouse.com</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Maple House. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <style>
        .amount-option {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .amount-option:hover,
        .amount-option.selected {
            border-color: rgba(255, 255, 255, 0.8);
            background: rgba(255, 255, 255, 0.3);
        }
        
        .amount-option input {
            display: none;
        }
        
        .amount-option label {
            cursor: pointer;
            font-weight: 500;
            color: white;
            margin: 0;
        }
    </style>

    <script>
        function selectAmount(amount) {
            // Remove selected class from all options
            document.querySelectorAll('.amount-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            document.querySelector('#amount_' + amount).closest('.amount-option').classList.add('selected');
            
            // Hide custom amount input
            document.getElementById('custom_amount').style.display = 'none';
            document.getElementById('custom_amount').value = '';
        }
        
        function selectCustom() {
            // Remove selected class from all options
            document.querySelectorAll('.amount-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to custom option
            document.querySelector('#amount_custom').closest('.amount-option').classList.add('selected');
            
            // Show custom amount input
            document.getElementById('custom_amount').style.display = 'block';
            document.getElementById('custom_amount').focus();
        }
        
        function toggleAnonymous() {
            const isAnonymous = document.getElementById('is_anonymous').checked;
            const donorInfo = document.getElementById('donor_info');
            const nameInput = document.getElementById('donor_name');
            const emailInput = document.getElementById('donor_email');
            
            if (isAnonymous) {
                donorInfo.style.display = 'none';
                nameInput.removeAttribute('required');
                emailInput.removeAttribute('required');
            } else {
                donorInfo.style.display = 'block';
                nameInput.setAttribute('required', 'required');
                emailInput.setAttribute('required', 'required');
            }
        }
        
        // Initialize form state
        document.addEventListener('DOMContentLoaded', function() {
            toggleAnonymous();
        });
    </script>
</body>
</html>
