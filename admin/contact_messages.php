<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle AJAX requests for status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'mark_read') {
            $message_id = (int)$_POST['message_id'];
            $stmt = $db->prepare("UPDATE contact_messages SET status = 'read', read_at = NOW() WHERE id = ?");
            $stmt->execute([$message_id]);
            echo json_encode(['success' => true, 'message' => 'Message marked as read']);
        }
        elseif ($_POST['action'] === 'mark_replied') {
            $message_id = (int)$_POST['message_id'];
            $admin_notes = trim($_POST['admin_notes'] ?? '');
            $stmt = $db->prepare("UPDATE contact_messages SET status = 'replied', replied_at = NOW(), admin_notes = ? WHERE id = ?");
            $stmt->execute([$admin_notes, $message_id]);
            echo json_encode(['success' => true, 'message' => 'Message marked as replied']);
        }
        elseif ($_POST['action'] === 'delete_message') {
            $message_id = (int)$_POST['message_id'];
            $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?");
            $stmt->execute([$message_id]);
            echo json_encode(['success' => true, 'message' => 'Message deleted successfully']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM contact_messages $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_messages = $count_stmt->fetchColumn();

// Get messages with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$query = "SELECT * FROM contact_messages $where_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread,
    SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as `read`,
    SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied
    FROM contact_messages";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$total_pages = ceil($total_messages / $per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - Maple House Admin</title>
    <link rel="icon" type="image/jpeg" href="../images/favicon.jpg">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-bottom: 30px;
        }

        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .header p {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            text-align: center;
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .stat-card.total i { color: #3498db; }
        .stat-card.unread i { color: #e74c3c; }
        .stat-card.read i { color: #f39c12; }
        .stat-card.replied i { color: #27ae60; }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 500;
        }

        .controls {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-bottom: 30px;
        }

        .controls-row {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
        }

        .form-control {
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-secondary { background: #6c757d; color: white; }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .messages-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            overflow: hidden;
        }

        .message-item {
            padding: 25px;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s ease;
        }

        .message-item:last-child {
            border-bottom: none;
        }

        .message-item:hover {
            background: #f8f9fa;
        }

        .message-item.unread {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }

        .message-item.read {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
        }

        .message-item.replied {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .message-info h3 {
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 1.2rem;
        }

        .message-meta {
            display: flex;
            gap: 20px;
            color: #6c757d;
            font-size: 0.9rem;
            flex-wrap: wrap;
        }

        .message-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-unread { background: #fff3cd; color: #856404; }
        .status-read { background: #d1ecf1; color: #0c5460; }
        .status-replied { background: #d4edda; color: #155724; }

        .message-content {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #3498db;
        }

        .message-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a,
        .pagination span {
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            text-decoration: none;
            color: #6c757d;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .pagination .current {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .no-messages {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .no-messages i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 25px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 25px;
        }

        .close {
            color: #999;
            font-size: 24px;
            cursor: pointer;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .controls-row {
                flex-direction: column;
                align-items: stretch;
            }

            .message-header {
                flex-direction: column;
                align-items: stretch;
            }

            .message-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">

        <div id="alert" class="alert"></div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <i class="fas fa-envelope"></i>
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Messages</div>
            </div>
            <div class="stat-card unread">
                <i class="fas fa-envelope-open"></i>
                <div class="stat-number"><?= $stats['unread'] ?></div>
                <div class="stat-label">Unread</div>
            </div>
            <div class="stat-card read">
                <i class="fas fa-eye"></i>
                <div class="stat-number"><?= $stats['read'] ?></div>
                <div class="stat-label">Read</div>
            </div>
            <div class="stat-card replied">
                <i class="fas fa-reply"></i>
                <div class="stat-number"><?= $stats['replied'] ?></div>
                <div class="stat-label">Replied</div>
            </div>
        </div>

        <!-- Controls -->
        <div class="controls">
            <form method="GET" class="controls-row">
                <div class="form-group">
                    <label>Status Filter</label>
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Messages</option>
                        <option value="unread" <?= $status_filter === 'unread' ? 'selected' : '' ?>>Unread</option>
                        <option value="read" <?= $status_filter === 'read' ? 'selected' : '' ?>>Read</option>
                        <option value="replied" <?= $status_filter === 'replied' ? 'selected' : '' ?>>Replied</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search messages..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="form-group" style="align-self: flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                <?php if (!empty($search) || $status_filter !== 'all'): ?>
                <div class="form-group" style="align-self: flex-end;">
                    <a href="contact_messages.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Messages -->
        <div class="messages-container">
            <?php if (empty($messages)): ?>
            <div class="no-messages">
                <i class="fas fa-inbox"></i>
                <h3>No Messages Found</h3>
                <p>There are no contact messages matching your criteria.</p>
            </div>
            <?php else: ?>
            <?php foreach ($messages as $message): ?>
            <div class="message-item <?= $message['status'] ?>" id="message-<?= $message['id'] ?>">
                <div class="message-header">
                    <div class="message-info">
                        <h3><?= htmlspecialchars($message['subject']) ?></h3>
                        <div class="message-meta">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($message['name']) ?></span>
                            <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($message['email']) ?></span>
                            <span><i class="fas fa-clock"></i> <?= date('M j, Y g:i A', strtotime($message['created_at'])) ?></span>
                        </div>
                    </div>
                    <div>
                        <span class="status-badge status-<?= $message['status'] ?>"><?= ucfirst($message['status']) ?></span>
                    </div>
                </div>

                <div class="message-content">
                    <?= nl2br(htmlspecialchars($message['message'])) ?>
                </div>

                <?php if (!empty($message['admin_notes'])): ?>
                <div class="message-content" style="border-left-color: #27ae60;">
                    <strong>Admin Notes:</strong><br>
                    <?= nl2br(htmlspecialchars($message['admin_notes'])) ?>
                </div>
                <?php endif; ?>

                <div class="message-actions">
                    <?php if ($message['status'] === 'unread'): ?>
                    <button class="btn btn-warning" onclick="markAsRead(<?= $message['id'] ?>)">
                        <i class="fas fa-eye"></i> Mark as Read
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($message['status'] !== 'replied'): ?>
                    <button class="btn btn-success" onclick="markAsReplied(<?= $message['id'] ?>)">
                        <i class="fas fa-reply"></i> Mark as Replied
                    </button>
                    <?php endif; ?>
                    
                    <a href="mailto:<?= htmlspecialchars($message['email']) ?>?subject=Re: <?= urlencode($message['subject']) ?>" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Reply via Email
                    </a>
                    
                    <button class="btn btn-danger" onclick="deleteMessage(<?= $message['id'] ?>)">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <?php if ($i === $page): ?>
            <span class="current"><?= $i ?></span>
            <?php else: ?>
            <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
            <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>">
                Next <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Reply Modal -->
    <div id="replyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-reply"></i> Mark as Replied</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="replyForm">
                    <input type="hidden" id="replyMessageId">
                    <div class="form-group">
                        <label>Admin Notes (Optional)</label>
                        <textarea id="adminNotes" class="form-control" rows="4" placeholder="Add any notes about your response..."></textarea>
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">Mark as Replied</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showAlert(message, type) {
            const alert = document.getElementById('alert');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            alert.style.display = 'block';
            
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }

        function markAsRead(messageId) {
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('message_id', messageId);

            fetch('contact_messages.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again.', 'error');
            });
        }

        function markAsReplied(messageId) {
            document.getElementById('replyMessageId').value = messageId;
            document.getElementById('replyModal').style.display = 'block';
        }

        function deleteMessage(messageId) {
            if (!confirm('Are you sure you want to delete this message? This action cannot be undone.')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_message');
            formData.append('message_id', messageId);

            fetch('contact_messages.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again.', 'error');
            });
        }

        function closeModal() {
            document.getElementById('replyModal').style.display = 'none';
            document.getElementById('adminNotes').value = '';
        }

        document.getElementById('replyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const messageId = document.getElementById('replyMessageId').value;
            const adminNotes = document.getElementById('adminNotes').value;

            const formData = new FormData();
            formData.append('action', 'mark_replied');
            formData.append('message_id', messageId);
            formData.append('admin_notes', adminNotes);

            fetch('contact_messages.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again.', 'error');
            });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('replyModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
