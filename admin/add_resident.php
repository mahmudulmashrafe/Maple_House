<?php
session_start();

// Include the database connection file
include_once '../config/database.php';

// Instantiate the Database class and get a connection
$database = new Database();
$db = $database->getConnection();

// Helper function to display messages
function displayMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $class = isset($_SESSION['message_type']) && $_SESSION['message_type'] === 'error' ? 'bg-red-500' : 'bg-green-500';
        echo "<div class='p-4 mb-4 text-white rounded-md " . $class . "'>" . htmlspecialchars($message) . "</div>";
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}

// Lightweight AJAX checks
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    try {
        if ($_GET['action'] === 'check_room') {
            $room = isset($_GET['room']) ? trim($_GET['room']) : '';
            if ($room === '') { 
                echo json_encode(['success' => true, 'available' => false, 'message' => 'Enter a room number']); 
                exit; 
            }
            
            // Check if editing mode - exclude current resident's room
            $exclude_resident_id = isset($_GET['exclude_resident_id']) ? (int)$_GET['exclude_resident_id'] : 0;
            
            if ($exclude_resident_id > 0) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM residents WHERE room_number = :room AND id != :exclude_id");
                $stmt->bindParam(':room', $room);
                $stmt->bindParam(':exclude_id', $exclude_resident_id);
            } else {
                $stmt = $db->prepare("SELECT COUNT(*) FROM residents WHERE room_number = :room");
                $stmt->bindParam(':room', $room);
            }
            
            $stmt->execute();
            $occupied = (int)$stmt->fetchColumn() > 0;
            echo json_encode(['success' => true, 'available' => !$occupied]);
            exit;
        }
        
        if ($_GET['action'] === 'check_username') {
            $username = isset($_GET['username']) ? trim($_GET['username']) : '';
            if ($username === '') { 
                echo json_encode(['success' => true, 'available' => false, 'message' => 'Enter a username']); 
                exit; 
            }
            
            // Check if editing mode - exclude current user's username
            $exclude_user_id = isset($_GET['exclude_user_id']) ? (int)$_GET['exclude_user_id'] : 0;
            
            if ($exclude_user_id > 0) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username AND id != :exclude_id");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':exclude_id', $exclude_user_id);
            } else {
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
                $stmt->bindParam(':username', $username);
            }
            
            $stmt->execute();
            $exists = (int)$stmt->fetchColumn() > 0;
            echo json_encode(['success' => true, 'available' => !$exists]);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Define CRUD operations for residents
// This section handles adding, updating, and deleting residents

// ADD a new resident
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    // Collect data from the form
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $username = $_POST['username']; // New username field
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $room_number = $_POST['room_number'];
    $admission_date = $_POST['admission_date'];
    $plan_id = $_POST['plan_id'];

    // Start a transaction for atomicity (both user and resident must be added)
    $db->beginTransaction();

    try {
        // Enforce unique username
        $check_user_stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $check_user_stmt->bindParam(':username', $username);
        $check_user_stmt->execute();
        if ((int)$check_user_stmt->fetchColumn() > 0) {
            throw new PDOException('Username already exists. Please choose another.');
        }

        // Enforce room availability (one resident per room)
        $check_room_stmt = $db->prepare("SELECT COUNT(*) FROM residents WHERE room_number = :room_number");
        $check_room_stmt->bindParam(':room_number', $room_number);
        $check_room_stmt->execute();
        if ((int)$check_room_stmt->fetchColumn() > 0) {
            throw new PDOException('Room is already occupied. Please choose a different room.');
        }

        // Hash the password for security
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // First, add the new user with a default 'Resident' role (assuming role_id 3 for Residents based on common database practices)
        $user_query = "INSERT INTO users (first_name, last_name, username, email, password, phone, role_id) VALUES (:first_name, :last_name, :username, :email, :password, :phone, 3)";
        $user_stmt = $db->prepare($user_query);

        // Bind parameters
        $user_stmt->bindParam(':first_name', $first_name);
        $user_stmt->bindParam(':last_name', $last_name);
        $user_stmt->bindParam(':username', $username); // Bind username
        $user_stmt->bindParam(':email', $email);
        $user_stmt->bindParam(':password', $hashed_password);
        $user_stmt->bindParam(':phone', $phone);
        $user_stmt->execute();

        // Get the ID of the newly created user
        $user_id = $db->lastInsertId();

        // Now, add the resident using the new user_id
        $resident_query = "INSERT INTO residents (user_id, room_number, admission_date, plan_id) VALUES (:user_id, :room_number, :admission_date, :plan_id)";
        $resident_stmt = $db->prepare($resident_query);

        // Bind parameters
        $resident_stmt->bindParam(':user_id', $user_id);
        $resident_stmt->bindParam(':room_number', $room_number);
        $resident_stmt->bindParam(':admission_date', $admission_date);
        $resident_stmt->bindParam(':plan_id', $plan_id);
        $resident_stmt->execute();

        // Commit the transaction
        $db->commit();
        $_SESSION['message'] = 'Resident added successfully!';
        $_SESSION['message_type'] = 'success';
    } catch (PDOException $e) {
        // Rollback the transaction on error
        $db->rollBack();
        $_SESSION['message'] = 'Error adding resident: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    // Redirect to the same page to show the updated list and prevent form resubmission
    header('Location: add_resident.php');
    exit();
}

// UPDATE an existing resident
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $resident_id = $_POST['resident_id'];
    $user_id = $_POST['user_id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $username = $_POST['username']; // New username field
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $room_number = $_POST['room_number'];
    $admission_date = $_POST['admission_date'];
    $plan_id = $_POST['plan_id'];

    $db->beginTransaction();

    try {
        // Enforce unique username excluding this user
        $check_user_stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username AND id <> :user_id");
        $check_user_stmt->bindParam(':username', $username);
        $check_user_stmt->bindParam(':user_id', $user_id);
        $check_user_stmt->execute();
        if ((int)$check_user_stmt->fetchColumn() > 0) {
            throw new PDOException('Username already exists. Please choose another.');
        }

        // Enforce room availability excluding this resident
        $check_room_stmt = $db->prepare("SELECT COUNT(*) FROM residents WHERE room_number = :room_number AND id <> :resident_id");
        $check_room_stmt->bindParam(':room_number', $room_number);
        $check_room_stmt->bindParam(':resident_id', $resident_id);
        $check_room_stmt->execute();
        if ((int)$check_room_stmt->fetchColumn() > 0) {
            throw new PDOException('Room is already occupied. Please choose a different room.');
        }

        // Update user information
        $user_query = "UPDATE users SET first_name = :first_name, last_name = :last_name, username = :username, email = :email, phone = :phone WHERE id = :user_id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':first_name', $first_name);
        $user_stmt->bindParam(':last_name', $last_name);
        $user_stmt->bindParam(':username', $username); // Bind username
        $user_stmt->bindParam(':email', $email);
        $user_stmt->bindParam(':phone', $phone);
        $user_stmt->bindParam(':user_id', $user_id);
        $user_stmt->execute();

        // Update resident information
        $resident_query = "UPDATE residents SET room_number = :room_number, admission_date = :admission_date, plan_id = :plan_id WHERE id = :resident_id";
        $resident_stmt = $db->prepare($resident_query);
        $resident_stmt->bindParam(':room_number', $room_number);
        $resident_stmt->bindParam(':admission_date', $admission_date);
        $resident_stmt->bindParam(':plan_id', $plan_id);
        $resident_stmt->bindParam(':resident_id', $resident_id);
        $resident_stmt->execute();

        $db->commit();
        $_SESSION['message'] = 'Resident updated successfully!';
        $_SESSION['message_type'] = 'success';
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['message'] = 'Error updating resident: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    header('Location: add_resident.php');
    exit();
}

// DELETE a resident
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['resident_id']) && isset($_GET['user_id'])) {
    $resident_id = $_GET['resident_id'];
    
    $db->beginTransaction();

    try {
        // Delete resident record. The foreign key with ON DELETE CASCADE will handle deleting the user record.
        $resident_query = "DELETE FROM residents WHERE id = :resident_id";
        $resident_stmt = $db->prepare($resident_query);
        $resident_stmt->bindParam(':resident_id', $resident_id);
        $resident_stmt->execute();

        $db->commit();
        $_SESSION['message'] = 'Resident deleted successfully!';
        $_SESSION['message_type'] = 'success';
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['message'] = 'Error deleting resident: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    // Redirect to the same page to show the updated list
    header('Location: add_resident.php');
    exit();
}

// Fetch all residents from the database to display in the table
$residents = [];
// This query now also gets the payment plan name
$query = "SELECT r.id AS resident_id, r.room_number, r.admission_date, r.plan_id, u.id AS user_id, u.first_name, u.last_name, u.username, u.email, u.phone, p.plan_name FROM residents r JOIN users u ON r.user_id = u.id LEFT JOIN payment_plans p ON r.plan_id = p.id";
$stmt = $db->prepare($query);
$stmt->execute();
$residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch payment plans for the form dropdown
$plans = [];
$plans_query = "SELECT id, plan_name FROM payment_plans";
$plans_stmt = $db->prepare($plans_query);
$plans_stmt->execute();
$plans = $plans_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Residents</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100 p-8">

    <div class="container mx-auto bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Manage Residents</h1>

        <?php displayMessage(); ?>

        <!-- Add/Edit Resident Form -->
        <div class="mb-8 p-6 bg-gray-50 border rounded-lg">
            <h2 class="text-2xl font-semibold mb-4 text-gray-700" id="form-title">Add New Resident</h2>
            <form id="resident-form" method="POST" action="add_resident.php" onsubmit="return validatePassword()" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="action" id="form-action" value="add">
                <input type="hidden" name="resident_id" id="resident-id">
                <input type="hidden" name="user_id" id="user-id">
                
                <div>
                    <label for="first_name" class="block text-gray-700 font-medium mb-1">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                <div>
                    <label for="last_name" class="block text-gray-700 font-medium mb-1">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                <div>
                    <label for="username" class="block text-gray-700 font-medium mb-1">Username</label>
                    <input type="text" id="username" name="username" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                    <small id="username-help" class="text-sm"></small>
                </div>
                <div>
                    <label for="email" class="block text-gray-700 font-medium mb-1">Email</label>
                    <input type="email" id="email" name="email" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                <div>
                    <label for="phone" class="block text-gray-700 font-medium mb-1">Phone</label>
                    <input type="tel" id="phone" name="phone" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="password" class="block text-gray-700 font-medium mb-1">Password</label>
                    <input type="password" id="password" name="password" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                <div>
                    <label for="confirm_password" class="block text-gray-700 font-medium mb-1">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                <div>
                    <label for="room_number" class="block text-gray-700 font-medium mb-1">Room Number</label>
                    <input type="text" id="room_number" name="room_number" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                    <small id="room-help" class="text-sm"></small>
                </div>
                <div>
                    <label for="admission_date" class="block text-gray-700 font-medium mb-1">Admission Date</label>
                    <input type="date" id="admission_date" name="admission_date" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                <div>
                    <label for="plan_id" class="block text-gray-700 font-medium mb-1">Payment Plan</label>
                    <select id="plan_id" name="plan_id" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                        <?php foreach ($plans as $plan): ?>
                            <option value="<?php echo htmlspecialchars($plan['id']); ?>"><?php echo htmlspecialchars($plan['plan_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="md:col-span-2 flex justify-end space-x-2">
                    <button type="submit" id="submit-button" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-md hover:bg-blue-700 transition duration-300">Add Resident</button>
                    <button type="button" id="cancel-button" class="bg-gray-400 text-white font-bold py-2 px-4 rounded-md hover:bg-gray-500 transition duration-300 hidden">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Residents List -->
        <h2 class="text-2xl font-semibold mb-4 text-gray-700">Existing Residents</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white rounded-lg overflow-hidden">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">First Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admission Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Plan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($residents) > 0): ?>
                        <?php foreach ($residents as $resident): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($resident['resident_id']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($resident['first_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($resident['last_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($resident['username']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($resident['email']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($resident['phone']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($resident['room_number']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($resident['admission_date']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($resident['plan_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="editResident(<?php echo htmlspecialchars(json_encode($resident)); ?>)" class="text-indigo-600 hover:text-indigo-900 mr-4">Edit</button>
                                    <a href="add_resident.php?action=delete&resident_id=<?php echo htmlspecialchars($resident['resident_id']); ?>&user_id=<?php echo htmlspecialchars($resident['user_id']); ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this resident?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="px-6 py-4 text-center text-gray-500">No residents found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Client-side password validation
        function validatePassword() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Only validate if a new password is being set or updated
            if (password || confirmPassword) {
                if (password !== confirmPassword) {
                    alert('Passwords do not match. Please try again.');
                    return false;
                }
            }
            return true;
        }

        function editResident(resident) {
            // Populate the form fields with resident data
            document.getElementById('form-title').textContent = 'Edit Resident';
            document.getElementById('form-action').value = 'update';
            document.getElementById('resident-id').value = resident.resident_id;
            document.getElementById('user-id').value = resident.user_id;
            document.getElementById('first_name').value = resident.first_name;
            document.getElementById('last_name').value = resident.last_name;
            document.getElementById('username').value = resident.username; // Populate username
            document.getElementById('email').value = resident.email;
            document.getElementById('phone').value = resident.phone;
            document.getElementById('room_number').value = resident.room_number;
            document.getElementById('admission_date').value = resident.admission_date;
            document.getElementById('plan_id').value = resident.plan_id;

            // Clear password fields for security
            document.getElementById('password').value = '';
            document.getElementById('confirm_password').value = '';

            // Update button text and show cancel button
            document.getElementById('submit-button').textContent = 'Update Resident';
            document.getElementById('cancel-button').classList.remove('hidden');

            // Clear any previous validation messages
            usernameHelp.textContent = '';
            roomHelp.textContent = '';
            usernameInput.classList.remove('border-green-500', 'border-red-500');
            roomInput.classList.remove('border-green-500', 'border-red-500');

            // Trigger validation for current values
            setTimeout(() => {
                checkUsername();
                checkRoom();
            }, 100);

            // Scroll to the form
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        document.getElementById('cancel-button').addEventListener('click', function() {
            // Reset the form to the "Add" state
            document.getElementById('form-title').textContent = 'Add New Resident';
            document.getElementById('form-action').value = 'add';
            document.getElementById('resident-id').value = '';
            document.getElementById('user-id').value = '';
            document.getElementById('resident-form').reset();

            // Clear validation messages
            usernameHelp.textContent = '';
            roomHelp.textContent = '';
            usernameInput.classList.remove('border-green-500', 'border-red-500');
            roomInput.classList.remove('border-green-500', 'border-red-500');

            // Reset button text and hide cancel button
            document.getElementById('submit-button').textContent = 'Add Resident';
            this.classList.add('hidden');
        });

        // Live username uniqueness check
        const usernameInput = document.getElementById('username');
        const usernameHelp = document.getElementById('username-help');
        const roomInput = document.getElementById('room_number');
        const roomHelp = document.getElementById('room-help');

        function setHelp(el, helpEl, ok, okMsg, errMsg) {
            if (!el.value.trim()) { 
                helpEl.textContent = ''; 
                el.classList.remove('border-green-500','border-red-500'); 
                return; 
            }
            if (ok) {
                helpEl.textContent = okMsg;
                helpEl.className = 'text-sm text-green-600';
                el.classList.remove('border-red-500');
                el.classList.add('border-green-500');
            } else {
                helpEl.textContent = errMsg;
                helpEl.className = 'text-sm text-red-600';
                el.classList.remove('border-green-500');
                el.classList.add('border-red-500');
            }
        }

        function checkUsername() {
            const val = usernameInput.value.trim();
            if (!val) { 
                setHelp(usernameInput, usernameHelp, true, '', ''); 
                return; 
            }
            
            const isEdit = document.getElementById('form-action').value === 'update';
            const userId = isEdit ? document.getElementById('user-id').value : '';
            
            let url = 'add_resident.php?action=check_username&username=' + encodeURIComponent(val);
            if (isEdit && userId) {
                url += '&exclude_user_id=' + encodeURIComponent(userId);
            }
            
            fetch(url)
                .then(r => {
                    if (!r.ok) throw new Error('Network error');
                    return r.json();
                })
                .then(data => {
                    if (data.success) {
                        setHelp(usernameInput, usernameHelp, data.available, '✓ Username available', '✗ Username already taken');
                    } else {
                        setHelp(usernameInput, usernameHelp, false, '', data.message || 'Error checking username');
                    }
                })
                .catch(e => {
                    console.error('Username check error:', e);
                    setHelp(usernameInput, usernameHelp, false, '', 'Error checking username availability');
                });
        }

        function checkRoom() {
            const val = roomInput.value.trim();
            if (!val) { 
                setHelp(roomInput, roomHelp, true, '', ''); 
                return; 
            }
            
            // Show loading state while checking
            roomHelp.textContent = '🔍 Checking room availability...';
            roomHelp.className = 'text-sm text-blue-600';
            roomInput.classList.remove('border-green-500', 'border-red-500');
            roomInput.classList.add('border-blue-500');
            
            const isEdit = document.getElementById('form-action').value === 'update';
            const residentId = isEdit ? document.getElementById('resident-id').value : '';
            
            let url = 'add_resident.php?action=check_room&room=' + encodeURIComponent(val);
            if (isEdit && residentId) {
                url += '&exclude_resident_id=' + encodeURIComponent(residentId);
            }
            
            fetch(url)
                .then(r => {
                    if (!r.ok) throw new Error('Network error');
                    return r.json();
                })
                .then(data => {
                    if (data.success) {
                        setHelp(roomInput, roomHelp, data.available, '✓ Room is available', '✗ Room is occupied');
                    } else {
                        setHelp(roomInput, roomHelp, false, '', data.message || 'Error checking room');
                    }
                })
                .catch(e => {
                    console.error('Room check error:', e);
                    setHelp(roomInput, roomHelp, false, '', 'Error checking room availability');
                });
        }

        function checkUsername() {
            const val = usernameInput.value.trim();
            if (!val) { 
                setHelp(usernameInput, usernameHelp, true, '', ''); 
                return; 
            }
            
            // Show loading state while checking
            usernameHelp.textContent = '🔍 Checking username availability...';
            usernameHelp.className = 'text-sm text-blue-600';
            usernameInput.classList.remove('border-green-500', 'border-red-500');
            usernameInput.classList.add('border-blue-500');
            
            const isEdit = document.getElementById('form-action').value === 'update';
            const userId = isEdit ? document.getElementById('user-id').value : '';
            
            let url = 'add_resident.php?action=check_username&username=' + encodeURIComponent(val);
            if (isEdit && userId) {
                url += '&exclude_user_id=' + encodeURIComponent(userId);
            }
            
            fetch(url)
                .then(r => {
                    if (!r.ok) throw new Error('Network error');
                    return r.json();
                })
                .then(data => {
                    if (data.success) {
                        setHelp(usernameInput, usernameHelp, data.available, '✓ Username available', '✗ Username already taken');
                    } else {
                        setHelp(usernameInput, usernameHelp, false, '', data.message || 'Error checking username');
                    }
                })
                .catch(e => {
                    console.error('Username check error:', e);
                    setHelp(usernameInput, usernameHelp, false, '', 'Error checking username availability');
                });
        }

        let usernameTimer;
        usernameInput.addEventListener('input', function() {
            clearTimeout(usernameTimer);
            // Show immediate feedback that we're about to check
            if (this.value.trim()) {
                usernameHelp.textContent = '⏳ Typing...';
                usernameHelp.className = 'text-sm text-gray-500';
                this.classList.remove('border-green-500', 'border-red-500', 'border-blue-500');
            } else {
                usernameHelp.textContent = '';
                this.classList.remove('border-green-500', 'border-red-500', 'border-blue-500');
            }
            usernameTimer = setTimeout(checkUsername, 300);
        });

        let roomTimer;
        roomInput.addEventListener('input', function() {
            clearTimeout(roomTimer);
            // Show immediate feedback that we're about to check
            if (this.value.trim()) {
                roomHelp.textContent = '⏳ Typing...';
                roomHelp.className = 'text-sm text-gray-500';
                this.classList.remove('border-green-500', 'border-red-500', 'border-blue-500');
            } else {
                roomHelp.textContent = '';
                this.classList.remove('border-green-500', 'border-red-500', 'border-blue-500');
            }
            roomTimer = setTimeout(checkRoom, 100); // Faster response for room numbers
        });
    </script>
</body>
</html>
