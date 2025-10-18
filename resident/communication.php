<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header('Location: ../login.php');
    exit();
}

// Mock messages data
$messages = [
    ['id' => 1, 'from' => 'Admin', 'subject' => 'Welcome to Maple House', 'date' => '2024-01-20', 'read' => true, 'content' => 'Welcome to our facility! We hope you have a comfortable stay.'],
    ['id' => 2, 'from' => 'Dr. Sarah Johnson', 'subject' => 'Health Checkup Reminder', 'date' => '2024-01-18', 'read' => false, 'content' => 'Your monthly health checkup is scheduled for tomorrow at 10 AM.'],
    ['id' => 3, 'from' => 'Kitchen Staff', 'subject' => 'Special Menu This Week', 'date' => '2024-01-15', 'read' => true, 'content' => 'We have special dishes planned for this week. Check the meal schedule!'],
];

// Mock family contacts
$family_contacts = [
    ['name' => 'John Smith', 'relationship' => 'Son', 'phone' => '+1-555-0123', 'email' => 'john@email.com'],
    ['name' => 'Mary Smith', 'relationship' => 'Daughter', 'phone' => '+1-555-0124', 'email' => 'mary@email.com'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communication - Maple House</title>
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

        .communication-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .communication-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            text-align: center;
        }

        .communication-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .communication-header p {
            color: #6c757d;
        }

        .communication-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
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
            font-size: 1.3rem;
        }

        .section-body {
            padding: 25px;
        }

        /* Messages Section */
        .message-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }

        .message-item:hover {
            background: #f8f9fa;
        }

        .message-item:last-child {
            border-bottom: none;
        }

        .message-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 15px;
        }

        .message-content {
            flex: 1;
        }

        .message-from {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 2px;
        }

        .message-subject {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 2px;
        }

        .message-date {
            font-size: 0.8rem;
            color: #adb5bd;
        }

        .message-unread {
            background: #fff3cd;
            border-left: 3px solid #ffc107;
        }

        /* Family Contacts Section */
        .contact-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .contact-item:last-child {
            border-bottom: none;
        }

        .contact-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #28a745, #20c997);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 15px;
            font-size: 1.2rem;
        }

        .contact-info {
            flex: 1;
        }

        .contact-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 2px;
        }

        .contact-relationship {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .contact-details {
            display: flex;
            gap: 15px;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .contact-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: #5a67d8;
        }

        .action-btn.call {
            background: #28a745;
        }

        .action-btn.call:hover {
            background: #218838;
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
        }

        .quick-actions h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .quick-action {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            text-decoration: none;
            color: white;
        }

        .quick-action i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }

        .quick-action span {
            font-weight: 600;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .communication-grid {
                grid-template-columns: 1fr;
            }

            .contact-details {
                flex-direction: column;
                gap: 5px;
            }

            .contact-actions {
                flex-direction: column;
            }

            .actions-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #dee2e6;
        }
    </style>
</head>
<body>
    <div class="communication-container">
        <!-- Communication Header -->
        <div class="communication-header">
            <h1><i class="fas fa-comments"></i> Communication Center</h1>
            <p>Stay connected with staff and family members</p>
        </div>

        <!-- Main Communication Grid -->
        <div class="communication-grid">
            <!-- Messages Section -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-envelope"></i>
                    <h2>Recent Messages</h2>
                </div>
                <div class="section-body">
                    <?php if (!empty($messages)): ?>
                        <?php foreach ($messages as $message): ?>
                        <div class="message-item <?php echo !$message['read'] ? 'message-unread' : ''; ?>">
                            <div class="message-icon">
                                <i class="fas <?php echo !$message['read'] ? 'fa-envelope' : 'fa-envelope-open'; ?>"></i>
                            </div>
                            <div class="message-content">
                                <div class="message-from"><?php echo htmlspecialchars($message['from']); ?></div>
                                <div class="message-subject"><?php echo htmlspecialchars($message['subject']); ?></div>
                                <div class="message-date"><?php echo date('M j, Y', strtotime($message['date'])); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No messages yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Family Contacts Section -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-users"></i>
                    <h2>Family Contacts</h2>
                </div>
                <div class="section-body">
                    <?php if (!empty($family_contacts)): ?>
                        <?php foreach ($family_contacts as $contact): ?>
                        <div class="contact-item">
                            <div class="contact-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="contact-info">
                                <div class="contact-name"><?php echo htmlspecialchars($contact['name']); ?></div>
                                <div class="contact-relationship"><?php echo htmlspecialchars($contact['relationship']); ?></div>
                                <div class="contact-details">
                                    <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($contact['phone']); ?></span>
                                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($contact['email']); ?></span>
                                </div>
                            </div>
                            <div class="contact-actions">
                                <button class="action-btn call" onclick="initiateCall('<?php echo $contact['phone']; ?>')">
                                    <i class="fas fa-video"></i> Call
                                </button>
                                <button class="action-btn" onclick="sendMessage('<?php echo $contact['name']; ?>')">
                                    <i class="fas fa-envelope"></i> Message
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-friends"></i>
                            <p>No family contacts added</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            <div class="actions-grid">
                <a href="#" class="quick-action" onclick="composeMessage()">
                    <i class="fas fa-edit"></i>
                    <span>Compose Message</span>
                </a>
                <a href="#" class="quick-action" onclick="scheduleCall()">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Schedule Family Call</span>
                </a>
                <a href="#" class="quick-action" onclick="viewAllMessages()">
                    <i class="fas fa-inbox"></i>
                    <span>All Messages</span>
                </a>
                <a href="#" class="quick-action" onclick="emergencyContact()">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Emergency Contact</span>
                </a>
            </div>
        </div>
    </div>

    <script>
        function initiateCall(phone) {
            alert('Initiating video call to ' + phone + '\n\nThis feature would connect you with your family member via video call.');
        }

        function sendMessage(name) {
            alert('Opening message composer for ' + name + '\n\nThis would open a messaging interface to send a message to your family member.');
        }

        function composeMessage() {
            alert('Opening message composer...\n\nThis would open a form to compose a new message to staff or family.');
        }

        function scheduleCall() {
            alert('Opening call scheduler...\n\nThis would allow you to schedule a video call with family members.');
        }

        function viewAllMessages() {
            alert('Opening message inbox...\n\nThis would show all your messages in a detailed view.');
        }

        function emergencyContact() {
            alert('Emergency Contact Feature\n\nThis would immediately connect you with emergency services or designated emergency contacts.');
        }
    </script>
</body>
</html>
