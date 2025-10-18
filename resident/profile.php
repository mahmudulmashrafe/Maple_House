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
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            padding: 20px;
            line-height: 1.6;
        }

        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .profile-header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            text-align: center;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
            color: white;
        }

        .profile-header h1 {
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .profile-header p {
            color: #6c757d;
            margin-bottom: 15px;
        }

        .profile-badges {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-resident {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .badge-plan {
            background: #e9ecef;
            color: #495057;
        }

        .badge-room {
            background: #d4edda;
            color: #155724;
        }

        /* Profile Sections */
        .profile-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h2 {
            margin: 0;
            font-size: 1.2rem;
        }

        .section-body {
            padding: 25px;
        }

        .info-group {
            margin-bottom: 20px;
        }

        .info-group:last-child {
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            display: block;
        }

        .info-value {
            color: #6c757d;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-value:last-child {
            border-bottom: none;
        }

        /* Edit Form */
        .edit-form {
            display: none;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }

        .form-group textarea {
            height: 80px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        /* Plan Details */
        .plan-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .plan-feature {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .plan-feature:last-child {
            border-bottom: none;
        }

        .plan-feature-name {
            color: #2c3e50;
        }

        .plan-feature-value {
            color: #667eea;
            font-weight: 600;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .profile-sections {
                grid-template-columns: 1fr;
            }

            .profile-badges {
                justify-content: center;
            }

            .form-actions {
                flex-direction: column;
            }
        }

        /* Success Message */
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h1><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></h1>
            <p>Resident since <?php echo date('M Y', strtotime($resident['created_at'] ?? '2023-01-01')); ?></p>
            <div class="profile-badges">
                <span class="badge badge-resident">Resident</span>
                <span class="badge badge-plan"><?php echo htmlspecialchars($resident['plan_name']); ?></span>
                <span class="badge badge-room">Room <?php echo htmlspecialchars($resident['room_number']); ?></span>
            </div>
        </div>

        <!-- Success Message -->
        <div id="successMessage" class="success-message">
            <i class="fas fa-check-circle"></i> Profile updated successfully!
        </div>

        <!-- Profile Sections -->
        <div class="profile-sections">
            <!-- Personal Information -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-user"></i>
                    <h2>Personal Information</h2>
                </div>
                <div class="section-body">
                    <div id="personalInfo" class="info-display">
                        <div class="info-group">
                            <span class="info-label">Full Name</span>
                            <div class="info-value"><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></div>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Email</span>
                            <div class="info-value"><?php echo htmlspecialchars($resident['email']); ?></div>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Phone</span>
                            <div class="info-value"><?php echo htmlspecialchars($resident['phone'] ?? 'Not provided'); ?></div>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Date of Birth</span>
                            <div class="info-value"><?php echo $resident['date_of_birth'] ? date('M j, Y', strtotime($resident['date_of_birth'])) : 'Not provided'; ?></div>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Gender</span>
                            <div class="info-value"><?php echo htmlspecialchars($resident['gender'] ?? 'Not specified'); ?></div>
                        </div>
                    </div>

                    <div id="personalForm" class="edit-form">
                        <form>
                            <div class="form-group">
                                <label for="firstName">First Name</label>
                                <input type="text" id="firstName" value="<?php echo htmlspecialchars($resident['first_name']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="lastName">Last Name</label>
                                <input type="text" id="lastName" value="<?php echo htmlspecialchars($resident['last_name']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" value="<?php echo htmlspecialchars($resident['email']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" value="<?php echo htmlspecialchars($resident['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="dateOfBirth">Date of Birth</label>
                                <input type="date" id="dateOfBirth" value="<?php echo $resident['date_of_birth']; ?>">
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select id="gender">
                                    <option value="Male" <?php echo ($resident['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($resident['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($resident['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </form>
                    </div>

                    <div class="form-actions">
                        <button id="editPersonalBtn" class="btn btn-primary" onclick="toggleEdit('personal')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button id="savePersonalBtn" class="btn btn-success" onclick="savePersonal()" style="display: none;">
                            <i class="fas fa-save"></i> Save
                        </button>
                        <button id="cancelPersonalBtn" class="btn btn-secondary" onclick="cancelEdit('personal')" style="display: none;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Plan & Room Information -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-home"></i>
                    <h2>Plan & Room Details</h2>
                </div>
                <div class="section-body">
                    <div class="info-group">
                        <span class="info-label">Current Plan</span>
                        <div class="info-value"><?php echo htmlspecialchars($resident['plan_name']); ?></div>
                        <div class="plan-details">
                            <div class="plan-feature">
                                <span class="plan-feature-name">Monthly Fee</span>
                                <span class="plan-feature-value">₹<?php echo number_format($resident['monthly_fee']); ?></span>
                            </div>
                            <div class="plan-feature">
                                <span class="plan-feature-name">Laundry Limit</span>
                                <span class="plan-feature-value"><?php echo $resident['laundry_limit']; ?> times/month</span>
                            </div>
                            <div class="plan-feature">
                                <span class="plan-feature-name">Cleaning Limit</span>
                                <span class="plan-feature-value"><?php echo $resident['cleaning_limit']; ?> times/month</span>
                            </div>
                        </div>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Room Number</span>
                        <div class="info-value">Room <?php echo htmlspecialchars($resident['room_number']); ?></div>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Move-in Date</span>
                        <div class="info-value"><?php echo $resident['move_in_date'] ? date('M j, Y', strtotime($resident['move_in_date'])) : 'Not available'; ?></div>
                    </div>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-phone"></i>
                    <h2>Emergency Contact</h2>
                </div>
                <div class="section-body">
                    <div id="emergencyInfo" class="info-display">
                        <div class="info-group">
                            <span class="info-label">Contact Name</span>
                            <div class="info-value"><?php echo htmlspecialchars($resident['emergency_contact_name'] ?? 'Not provided'); ?></div>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Contact Phone</span>
                            <div class="info-value"><?php echo htmlspecialchars($resident['emergency_contact_phone'] ?? 'Not provided'); ?></div>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Relationship</span>
                            <div class="info-value"><?php echo htmlspecialchars($resident['emergency_contact_relationship'] ?? 'Not specified'); ?></div>
                        </div>
                    </div>

                    <div id="emergencyForm" class="edit-form">
                        <form>
                            <div class="form-group">
                                <label for="emergencyName">Contact Name</label>
                                <input type="text" id="emergencyName" value="<?php echo htmlspecialchars($resident['emergency_contact_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="emergencyPhone">Contact Phone</label>
                                <input type="tel" id="emergencyPhone" value="<?php echo htmlspecialchars($resident['emergency_contact_phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="emergencyRelationship">Relationship</label>
                                <input type="text" id="emergencyRelationship" value="<?php echo htmlspecialchars($resident['emergency_contact_relationship'] ?? ''); ?>">
                            </div>
                        </form>
                    </div>

                    <div class="form-actions">
                        <button id="editEmergencyBtn" class="btn btn-primary" onclick="toggleEdit('emergency')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button id="saveEmergencyBtn" class="btn btn-success" onclick="saveEmergency()" style="display: none;">
                            <i class="fas fa-save"></i> Save
                        </button>
                        <button id="cancelEmergencyBtn" class="btn btn-secondary" onclick="cancelEdit('emergency')" style="display: none;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Preferences -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-cog"></i>
                    <h2>Preferences</h2>
                </div>
                <div class="section-body">
                    <div class="info-group">
                        <span class="info-label">Dietary Restrictions</span>
                        <div class="info-value"><?php echo htmlspecialchars($resident['dietary_restrictions'] ?? 'None specified'); ?></div>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Medical Notes</span>
                        <div class="info-value"><?php echo htmlspecialchars($resident['medical_notes'] ?? 'None provided'); ?></div>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Special Requests</span>
                        <div class="info-value"><?php echo htmlspecialchars($resident['special_requests'] ?? 'None'); ?></div>
                    </div>
                    
                    <div class="form-actions">
                        <button class="btn btn-primary" onclick="editPreferences()">
                            <i class="fas fa-edit"></i> Edit Preferences
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleEdit(section) {
            const infoDiv = document.getElementById(section + 'Info');
            const formDiv = document.getElementById(section + 'Form');
            const editBtn = document.getElementById('edit' + section.charAt(0).toUpperCase() + section.slice(1) + 'Btn');
            const saveBtn = document.getElementById('save' + section.charAt(0).toUpperCase() + section.slice(1) + 'Btn');
            const cancelBtn = document.getElementById('cancel' + section.charAt(0).toUpperCase() + section.slice(1) + 'Btn');

            infoDiv.style.display = 'none';
            formDiv.style.display = 'block';
            editBtn.style.display = 'none';
            saveBtn.style.display = 'inline-flex';
            cancelBtn.style.display = 'inline-flex';
        }

        function cancelEdit(section) {
            const infoDiv = document.getElementById(section + 'Info');
            const formDiv = document.getElementById(section + 'Form');
            const editBtn = document.getElementById('edit' + section.charAt(0).toUpperCase() + section.slice(1) + 'Btn');
            const saveBtn = document.getElementById('save' + section.charAt(0).toUpperCase() + section.slice(1) + 'Btn');
            const cancelBtn = document.getElementById('cancel' + section.charAt(0).toUpperCase() + section.slice(1) + 'Btn');

            infoDiv.style.display = 'block';
            formDiv.style.display = 'none';
            editBtn.style.display = 'inline-flex';
            saveBtn.style.display = 'none';
            cancelBtn.style.display = 'none';
        }

        function savePersonal() {
            // Here you would typically send the data to a PHP script
            // For now, we'll just show a success message
            showSuccessMessage();
            cancelEdit('personal');
        }

        function saveEmergency() {
            // Here you would typically send the data to a PHP script
            // For now, we'll just show a success message
            showSuccessMessage();
            cancelEdit('emergency');
        }

        function editPreferences() {
            alert('Preferences editor would open here.\n\nThis would allow you to update dietary restrictions, medical notes, and special requests.');
        }

        function showSuccessMessage() {
            const successMessage = document.getElementById('successMessage');
            successMessage.style.display = 'block';
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 3000);
        }
    </script>
</body>
</html>
