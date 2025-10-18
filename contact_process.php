<?php
session_start();
require_once 'config/database.php';

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_POST) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    // Basic validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
            exit();
        } else {
            header('Location: index.php?contact=error&msg=Please fill in all fields');
            exit();
        }
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
            exit();
        } else {
            header('Location: index.php?contact=error&msg=Please enter a valid email address');
            exit();
        }
    }

    try {
        // Create database connection
        $database = new Database();
        $db = $database->getConnection();
        
        // Create table if it doesn't exist
        $create_table_sql = "
        CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            replied_at TIMESTAMP NULL,
            admin_notes TEXT,
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_email (email)
        )";
        $db->exec($create_table_sql);
        
        // Insert the contact message
        $stmt = $db->prepare("
            INSERT INTO contact_messages (name, email, subject, message, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt->execute([$name, $email, $subject, $message, $ip_address, $user_agent]);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Thank you for your message! We will get back to you soon.']);
            exit();
        } else {
            header('Location: index.php?contact=success&msg=Thank you for your message. We will get back to you soon.');
            exit();
        }
        
    } catch (Exception $e) {
        error_log("Contact form error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Sorry, there was an error sending your message. Please try again.']);
            exit();
        } else {
            header('Location: index.php?contact=error&msg=Sorry, there was an error sending your message. Please try again.');
            exit();
        }
    }
} else {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit();
    } else {
        header('Location: index.php');
        exit();
    }
}
