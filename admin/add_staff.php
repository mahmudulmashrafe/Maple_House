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

// Define CRUD operations for staff
// This section handles adding, updating, and deleting staff members

// ADD a new staff member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    // Collect data from the form
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $username = $_POST['username']; // New username field
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $position = $_POST['position'];
    $password = $_POST['password'];

    // Start a transaction for atomicity (both user and staff must be added)
    $db->beginTransaction();

    try {
        // Hash the password for security
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // First, add the new user with a 'Staff' role.
        // Correct role_id is 5 for Staff, not 4.
        $user_query = "INSERT INTO users (first_name, last_name, username, email, password, phone, role_id) VALUES (:first_name, :last_name, :username, :email, :password, :phone, 5)";
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

        // Now, add the staff member using the new user_id
        $staff_query = "INSERT INTO staff (user_id, position) VALUES (:user_id, :position)";
        $staff_stmt = $db->prepare($staff_query);

        // Bind parameters
        $staff_stmt->bindParam(':user_id', $user_id);
        $staff_stmt->bindParam(':position', $position);
        $staff_stmt->execute();

        // Commit the transaction
        $db->commit();
        $_SESSION['message'] = 'Staff member added successfully!';
        $_SESSION['message_type'] = 'success';
    } catch (PDOException $e) {
        // Rollback the transaction on error
        $db->rollBack();
        $_SESSION['message'] = 'Error adding staff member: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    // Redirect to the same page to show the updated list and prevent form resubmission
    header('Location: add_staff.php');
    exit();
}

// UPDATE an existing staff member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $staff_id = $_POST['staff_id'];
    $user_id = $_POST['user_id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $username = $_POST['username']; // New username field
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $position = $_POST['position'];

    $db->beginTransaction();

    try {
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

        // Update staff information
        $staff_query = "UPDATE staff SET position = :position WHERE id = :staff_id";
        $staff_stmt = $db->prepare($staff_query);
        $staff_stmt->bindParam(':position', $position);
        $staff_stmt->bindParam(':staff_id', $staff_id);
        $staff_stmt->execute();

        $db->commit();
        $_SESSION['message'] = 'Staff member updated successfully!';
        $_SESSION['message_type'] = 'success';
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['message'] = 'Error updating staff member: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    header('Location: add_staff.php');
    exit();
}

// DELETE a staff member
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['staff_id'])) {
    $staff_id = $_GET['staff_id'];
    
    $db->beginTransaction();

    try {
        // Delete staff record. The foreign key with ON DELETE CASCADE will handle deleting the user record.
        $staff_query = "DELETE FROM staff WHERE id = :staff_id";
        $staff_stmt = $db->prepare($staff_query);
        $staff_stmt->bindParam(':staff_id', $staff_id);
        $staff_stmt->execute();

        $db->commit();
        $_SESSION['message'] = 'Staff member deleted successfully!';
        $_SESSION['message_type'] = 'success';
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['message'] = 'Error deleting staff member: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    // Redirect to the same page to show the updated list
    header('Location: add_staff.php');
    exit();
}

// Fetch all staff members from the database to display in the table
$staff = [];
$query = "SELECT s.id AS staff_id, s.position, u.id AS user_id, u.first_name, u.last_name, u.username, u.email, u.phone FROM staff s JOIN users u ON s.user_id = u.id";
$stmt = $db->prepare($query);
$stmt->execute();
$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff</title>
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
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Manage Staff</h1>

        <?php displayMessage(); ?>

        <!-- Add/Edit Staff Form -->
        <div class="mb-8 p-6 bg-gray-50 border rounded-lg">
            <h2 class="text-2xl font-semibold mb-4 text-gray-700" id="form-title">Add New Staff Member</h2>
            <form id="staff-form" method="POST" action="add_staff.php" onsubmit="return validatePassword()" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="action" id="form-action" value="add">
                <input type="hidden" name="staff_id" id="staff-id">
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
                <div class="md:col-span-2">
                    <label for="position" class="block text-gray-700 font-medium mb-1">Position</label>
                    <input type="text" id="position" name="position" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <div class="md:col-span-2 flex justify-end space-x-2">
                    <button type="submit" id="submit-button" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-md hover:bg-blue-700 transition duration-300">Add Staff</button>
                    <button type="button" id="cancel-button" class="bg-gray-400 text-white font-bold py-2 px-4 rounded-md hover:bg-gray-500 transition duration-300 hidden">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Staff List -->
        <h2 class="text-2xl font-semibold mb-4 text-gray-700">Existing Staff</h2>
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($staff) > 0): ?>
                        <?php foreach ($staff as $member): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($member['staff_id']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($member['first_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($member['last_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($member['username']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($member['email']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($member['phone']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($member['position']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="editStaff(<?php echo htmlspecialchars(json_encode($member)); ?>)" class="text-indigo-600 hover:text-indigo-900 mr-4">Edit</button>
                                    <a href="add_staff.php?action=delete&staff_id=<?php echo htmlspecialchars($member['staff_id']); ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this staff member?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">No staff members found.</td>
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

        function editStaff(member) {
            // Populate the form fields with staff member data
            document.getElementById('form-title').textContent = 'Edit Staff Member';
            document.getElementById('form-action').value = 'update';
            document.getElementById('staff-id').value = member.staff_id;
            document.getElementById('user-id').value = member.user_id;
            document.getElementById('first_name').value = member.first_name;
            document.getElementById('last_name').value = member.last_name;
            document.getElementById('username').value = member.username; // Populate username
            document.getElementById('email').value = member.email;
            document.getElementById('phone').value = member.phone;
            document.getElementById('position').value = member.position;
            
            // Clear password fields for security
            document.getElementById('password').value = '';
            document.getElementById('confirm_password').value = '';

            // Update button text and show cancel button
            document.getElementById('submit-button').textContent = 'Update Staff';
            document.getElementById('cancel-button').classList.remove('hidden');

            // Scroll to the form
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        document.getElementById('cancel-button').addEventListener('click', function() {
            // Reset the form to the "Add" state
            document.getElementById('form-title').textContent = 'Add New Staff Member';
            document.getElementById('form-action').value = 'add';
            document.getElementById('staff-id').value = '';
            document.getElementById('user-id').value = '';
            document.getElementById('staff-form').reset();

            // Reset button text and hide cancel button
            document.getElementById('submit-button').textContent = 'Add Staff';
            this.classList.add('hidden');
        });
    </script>
</body>
</html>
