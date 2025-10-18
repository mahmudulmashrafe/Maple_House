<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and has admin/manager role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Manager'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle AJAX requests
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    header("Content-Type: application/json");
    
    $donation_id = intval($_POST["donation_id"]);
    $action = $_POST["action"];
    
    try {
        if ($action === "approve") {
            $update_query = "UPDATE donations SET is_verified = 1, verified_by = :verified_by WHERE id = :donation_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(":verified_by", $_SESSION["user_id"]);
            $update_stmt->bindParam(":donation_id", $donation_id);
            $update_stmt->execute();
            echo json_encode(["success" => true, "message" => "Donation approved successfully"]);
        } elseif ($action === "reject") {
            $update_query = "DELETE FROM donations WHERE id = :donation_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(":donation_id", $donation_id);
            $update_stmt->execute();
            echo json_encode(["success" => true, "message" => "Donation rejected successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "Invalid action"]);
        }
    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => "Action failed. Please try again."]);
    }
    exit();
}
// Handle donation verification
if ($_POST && isset($_POST['verify_donation'])) {
    $donation_id = intval($_POST['donation_id']);
    $action = $_POST['action']; // 'verify' or 'reject'
    
    try {
        if ($action === 'verify') {
            $update_query = "UPDATE donations SET is_verified = 1, verified_by = :verified_by WHERE id = :donation_id";
            $success_message = "Donation verified successfully!";
        } else {
            $update_query = "DELETE FROM donations WHERE id = :donation_id";
            $success_message = "Donation rejected and removed successfully!";
        }
        
        $update_stmt = $db->prepare($update_query);
        if ($action === 'verify') {
            $update_stmt->bindParam(':verified_by', $_SESSION['user_id']);
        }
        $update_stmt->bindParam(':donation_id', $donation_id);
        $update_stmt->execute();
        
    } catch (Exception $e) {
        $error_message = 'Action failed. Please try again.';
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$purpose_filter = $_GET['purpose'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if ($status_filter === 'verified') {
    $where_conditions[] = "d.is_verified = 1";
} elseif ($status_filter === 'pending') {
    $where_conditions[] = "d.is_verified = 0";
}

if ($date_from) {
    $where_conditions[] = "d.donation_date >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "d.donation_date <= :date_to";
    $params[':date_to'] = $date_to;
}

if ($purpose_filter) {
    $where_conditions[] = "d.purpose = :purpose";
    $params[':purpose'] = $purpose_filter;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get donations with pagination
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$donations_query = "SELECT d.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as verified_by_name
                    FROM donations d
                    LEFT JOIN users u ON d.verified_by = u.id
                    $where_clause
                    ORDER BY d.created_at DESC
                    LIMIT :limit OFFSET :offset";

$donations_stmt = $db->prepare($donations_query);
foreach ($params as $key => $value) {
    $donations_stmt->bindValue($key, $value);
}
$donations_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$donations_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$donations_stmt->execute();
$donations = $donations_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM donations d $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_donations = $count_stmt->fetchColumn();
$total_pages = ceil($total_donations / $per_page);

// Get donation statistics
$stats_query = "SELECT 
                    COUNT(*) as total_donations,
                    SUM(amount) as total_amount,
                    SUM(CASE WHEN is_verified = 1 THEN amount ELSE 0 END) as verified_amount,
                    COUNT(CASE WHEN is_verified = 0 THEN 1 END) as pending_count,
                    COUNT(CASE WHEN is_anonymous = 1 THEN 1 END) as anonymous_count
                FROM donations";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get unique purposes for filter
$purposes_query = "SELECT DISTINCT purpose FROM donations ORDER BY purpose";
$purposes_stmt = $db->prepare($purposes_query);
$purposes_stmt->execute();
$purposes = $purposes_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donations Management - Maple House</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .header h1 {
            margin: 0;
            color: #2c5aa0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #3498db;
        }
        
        .stat-card.success i { color: #27ae60; }
        .stat-card.warning i { color: #f39c12; }
        .stat-card.info i { color: #3498db; }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        .donations-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-verified {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .pagination a {
            padding: 8px 12px;
            text-decoration: none;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #3498db;
        }
        
        .pagination a:hover,
        .pagination a.active {
            background: #3498db;
            color: white;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.3);
            backdrop-filter: blur(2px);
        }
        
        .modal-content {
            background: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
    </style>
</head>
<body>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card info">
            <i class="fas fa-heart"></i>
            <div class="stat-number"><?php echo $stats['total_donations']; ?></div>
            <div class="stat-label">Total Donations</div>
        </div>
        
        <div class="stat-card success">
            <i class="fas fa-money-bill-wave"></i>
            <div class="stat-number">৳<?php echo number_format($stats['total_amount']); ?></div>
            <div class="stat-label">Total Amount</div>
        </div>
        
        <div class="stat-card success">
            <i class="fas fa-check-circle"></i>
            <div class="stat-number">৳<?php echo number_format($stats['verified_amount']); ?></div>
            <div class="stat-label">Verified Amount</div>
        </div>
        
        <div class="stat-card warning">
            <i class="fas fa-clock"></i>
            <div class="stat-number"><?php echo $stats['pending_count']; ?></div>
            <div class="stat-label">Pending Verification</div>
        </div>
        
        <div class="stat-card info">
            <i class="fas fa-user-secret"></i>
            <div class="stat-number"><?php echo $stats['anonymous_count']; ?></div>
            <div class="stat-label">Anonymous Donations</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters">
        <form method="GET" action="">
            <div class="filters-grid">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Donations</option>
                        <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Purpose</label>
                    <select name="purpose">
                        <option value="">All Purposes</option>
                        <?php foreach ($purposes as $purpose): ?>
                            <option value="<?php echo htmlspecialchars($purpose); ?>" <?php echo $purpose_filter === $purpose ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($purpose); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="form-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Donations Table -->
    <div class="donations-table">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Donor</th>
                    <th>Amount</th>
                    <th>Purpose</th>
                    <th>Payment Method</th>
                    <th>Transaction ID</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($donations)): ?>
                    <?php foreach ($donations as $donation): ?>
                        <tr>
                            <td>#<?php echo $donation['id']; ?></td>
                            <td>
                                <?php if ($donation['is_anonymous']): ?>
                                    <i class="fas fa-user-secret"></i> Anonymous
                                <?php else: ?>
                                    <strong><?php echo htmlspecialchars($donation['donor_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($donation['donor_email']); ?></small>
                                    <?php if ($donation['donor_phone']): ?>
                                        <br><small><?php echo htmlspecialchars($donation['donor_phone']); ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><strong>৳<?php echo number_format($donation['amount']); ?></strong></td>
                            <td><?php echo htmlspecialchars($donation['purpose']); ?></td>
                            <td><?php echo htmlspecialchars($donation['payment_method']); ?></td>
                            <td><code><?php echo htmlspecialchars($donation['transaction_id']); ?></code></td>
                            <td><?php echo date('M j, Y', strtotime($donation['donation_date'])); ?></td>
                            <td>
                                <?php if ($donation['is_verified']): ?>
                                    <span class="status-badge status-verified">
                                        <i class="fas fa-check"></i> Verified
                                    </span>
                                    <?php if ($donation['verified_by_name']): ?>
                                        <br><small>by <?php echo htmlspecialchars($donation['verified_by_name']); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="status-badge status-pending">
                                        <i class="fas fa-clock"></i> Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$donation['is_verified']): ?>
                                    <button onclick="verifyDonation(<?php echo $donation['id']; ?>, 'verify')" 
                                            class="btn btn-success btn-sm" title="Verify Donation">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button onclick="verifyDonation(<?php echo $donation['id']; ?>, 'reject')" 
                                            class="btn btn-danger btn-sm" title="Reject Donation">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">No actions</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px;">
                            <i class="fas fa-heart" style="font-size: 3rem; color: #ddd; margin-bottom: 10px;"></i>
                            <p>No donations found matching your criteria.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                   class="<?php echo $i === $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Verification Modal -->
    <div id="verificationModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3 id="modalTitle">Confirm Action</h3>
            <p id="modalMessage">Are you sure you want to perform this action?</p>
            <form method="POST" id="verificationForm">
                <input type="hidden" name="donation_id" id="donationId">
                <input type="hidden" name="action" id="actionType">
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="closeModal()" class="btn" style="background: #6c757d; color: white; margin-right: 10px;">Cancel</button>
                    <button type="submit" name="verify_donation" class="btn btn-primary" id="confirmBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function verifyDonation(donationId, action) {
            const modal = document.getElementById('verificationModal');
            const title = document.getElementById('modalTitle');
            const message = document.getElementById('modalMessage');
            const confirmBtn = document.getElementById('confirmBtn');
            
            document.getElementById('donationId').value = donationId;
            document.getElementById('actionType').value = action;
            
            if (action === 'verify') {
                title.textContent = 'Verify Donation';
                message.textContent = 'Are you sure you want to verify this donation? This action will mark it as verified and approved.';
                confirmBtn.className = 'btn btn-success';
                confirmBtn.innerHTML = '<i class="fas fa-check"></i> Verify';
            } else {
                title.textContent = 'Reject Donation';
                message.textContent = 'Are you sure you want to reject this donation? This action will permanently remove it from the system.';
                confirmBtn.className = 'btn btn-danger';
                confirmBtn.innerHTML = '<i class="fas fa-times"></i> Reject';
            }
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('verificationModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('verificationModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
        
        // Close modal with escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
        
        // Close button
        document.querySelector('.close').onclick = closeModal;
    </script>
</body>
</html>
