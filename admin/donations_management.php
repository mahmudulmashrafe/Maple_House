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

// Handle approve/ignore actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'approve_donation':
                $stmt = $db->prepare("UPDATE donations SET is_verified = 1, verified_by = ? WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $_POST['donation_id']]);
                echo json_encode(['success' => true, 'message' => 'Donation approved successfully']);
                break;
                
            case 'ignore_donation':
                $stmt = $db->prepare("UPDATE donations SET is_verified = 0 WHERE id = ?");
                $stmt->execute([$_POST['donation_id']]);
                echo json_encode(['success' => true, 'message' => 'Donation ignored successfully']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Get donation statistics
$stats_query = "SELECT 
                    COUNT(*) as total_donations,
                    COUNT(CASE WHEN is_anonymous = 1 THEN 1 END) as anonymous_donations,
                    COUNT(CASE WHEN is_verified = 0 THEN 1 END) as pending_donations,
                    SUM(COALESCE(amount, 0)) as total_amount,
                    SUM(CASE WHEN is_verified = 1 THEN COALESCE(amount, 0) ELSE 0 END) as verified_amount,
                    SUM(CASE WHEN is_verified = 0 THEN COALESCE(amount, 0) ELSE 0 END) as pending_amount
                FROM donations";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get all donations
$all_donations_query = "SELECT * FROM donations ORDER BY created_at DESC";
$all_donations_stmt = $db->prepare($all_donations_query);
$all_donations_stmt->execute();
$all_donations = $all_donations_stmt->fetchAll(PDO::FETCH_ASSOC);
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
            height: 100vh;
            overflow: hidden;
        }
        
        .page-container {
            height: calc(100vh - 40px);
            display: flex;
            flex-direction: column;
        }
        
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .page-title {
            margin: 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .amount-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
            margin-top: -10px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 8px 10px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
            border: 2px solid #e2e8f0;
            color: #2c3e50;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card.total {
            border-color: #667eea;
        }
        
        .stat-card.anonymous {
            border-color: #f5576c;
        }
        
        .stat-card.pending {
            border-color: #fcb69f;
        }
        
        .stat-card.total-amount {
            border-color: #a8edea;
        }
        
        .stat-card.verified-amount {
            border-color: #27ae60;
        }
        
        .stat-card.pending-amount {
            border-color: #66a6ff;
        }
        
        .stat-icon {
            display: none;
        }
        
        .stat-number {
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 2px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            letter-spacing: -0.5px;
        }
        
        .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            opacity: 0.95;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            letter-spacing: 0.3px;
        }
        
        .donations-table-section {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .filters-section {
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .filters-row {
            display: flex;
            flex-wrap: nowrap;
            gap: 8px;
            align-items: end;
            overflow-x: auto;
            justify-content: space-between;
        }
        
        .filters-left {
            display: flex;
            gap: 8px;
            align-items: end;
            flex-wrap: nowrap;
            overflow-x: auto;
        }
        
        .filters-right {
            margin-left: auto;
            flex-shrink: 0;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 120px;
            max-width: 140px;
            flex-shrink: 0;
        }
        
        .filter-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 2px;
            font-size: 0.75rem;
        }
        
        .filter-select,
        .filter-input {
            padding: 4px 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.7rem;
            background: white;
            width: 100%;
        }
        
        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        .filter-buttons {
            display: flex;
            gap: 5px;
            flex-direction: column;
        }
        
        .btn-filter {
            padding: 5px 8px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.7rem;
            transition: background 0.2s;
            white-space: nowrap;
        }
        
        .btn-filter:hover {
            background: #2980b9;
        }
        
        .btn-clear {
            padding: 5px 8px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.7rem;
            transition: background 0.2s;
            white-space: nowrap;
        }
        
        .btn-clear:hover {
            background: #5a6268;
        }
        
        .table-header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .donations-table {
            overflow-x: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .table-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr 1.5fr 1fr 1fr 1.5fr;
            gap: 15px;
            padding: 15px 20px;
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table-body {
            flex: 1;
            overflow-y: auto;
        }
        
        .table-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr 1.5fr 1fr 1fr 1.5fr;
            gap: 15px;
            padding: 15px 20px;
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.2s;
        }
        
        .table-row:hover {
            background: #f8f9fa;
        }
        
        .donor-name {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .donor-email {
            font-size: 0.8rem;
            color: #666;
            margin-top: 2px;
        }
        
        .amount {
            font-weight: 600;
            color: #27ae60;
        }
        
        .transaction-id {
            font-size: 0.75rem;
            color: #666;
            margin-top: 2px;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
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
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-approve {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background 0.2s;
        }
        
        .btn-approve:hover {
            background: #218838;
        }
        
        .btn-ignore {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background 0.2s;
        }
        
        .btn-ignore:hover {
            background: #c82333;
        }
        
        .btn-approved {
            background: #6c757d;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: not-allowed;
        }
        
        @media (max-width: 768px) {
            .stats-grid,
            .amount-stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table-header,
            .table-row {
                grid-template-columns: 1fr;
                gap: 5px;
            }
            
            .table-header {
                display: none;
            }
            
            .table-row {
                padding: 15px;
                border: 1px solid #e9ecef;
                margin-bottom: 10px;
                border-radius: 8px;
            }
        }
        
        /* Popup Notification Styles */
        .success-popup, .error-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        }
        
        .success-popup.show, .error-popup.show {
            opacity: 1;
            transform: translateX(0);
        }
        
        .success-popup .popup-content {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            min-width: 250px;
        }
        
        .error-popup .popup-content {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            min-width: 250px;
        }
        
        .popup-content i {
            font-size: 1.2rem;
        }
        
        /* Custom Confirmation Popup */
        .confirm-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10001;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .confirm-popup.show {
            opacity: 1;
        }
        
        .confirm-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transform: scale(0.8);
            transition: transform 0.3s ease;
        }
        
        .confirm-popup.show .confirm-content {
            transform: scale(1);
        }
        
        .confirm-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #ffc107;
        }
        
        .confirm-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .confirm-message {
            color: #6c757d;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        
        .confirm-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .confirm-btn {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }
        
        .confirm-btn.yes {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .confirm-btn.yes:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .confirm-btn.no {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }
        
        .confirm-btn.no:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- First Row: Donation Counts -->
    <div class="amount-stats-grid">
        <div class="stat-card total-amount">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-label" style="color: black;font-weight: bold;font-size: 22px;">Total Amount</div>
            <div class="stat-number">৳<?php echo number_format($stats['total_amount']); ?></div>
            
        </div>
        
        <div class="stat-card verified-amount">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-label" style="color: black;font-weight: bold;font-size: 22px;">Verified Amount</div>
            <div class="stat-number">৳<?php echo number_format($stats['verified_amount']); ?></div>
        </div>
        
        <div class="stat-card pending-amount">
            <div class="stat-icon">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="stat-label" style="color: black;font-weight: bold;font-size: 22px;">Pending Amount</div>
            <div class="stat-number">৳<?php echo number_format($stats['pending_amount']); ?></div>
        </div>
    </div>

    <!-- All Donations Table -->
    <div class="donations-table-section">
        <div class="table-header-section">
            <i class="fas fa-list"></i>
            <h3>All Donations (<span id="donationCount"><?php echo count($all_donations); ?></span> total)</h3>
        </div>
        
        <!-- Filters Section -->
        <div class="filters-section">
            <div class="filters-row">
                <div class="filters-left">
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select id="statusFilter" class="filter-select">
                            <option value="">All Status</option>
                            <option value="verified">Verified</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Payment Method</label>
                        <select id="paymentFilter" class="filter-select">
                            <option value="">All Methods</option>
                            <option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option>
                            <option value="rocket">Rocket</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Purpose</label>
                        <select id="purposeFilter" class="filter-select">
                            <option value="">All Purposes</option>
                            <option value="general">General Donation</option>
                            <option value="food">Food & Nutrition</option>
                            <option value="medical">Medical Care</option>
                            <option value="education">Education</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="emergency">Emergency Fund</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Donor Type</label>
                        <select id="donorFilter" class="filter-select">
                            <option value="">All Donors</option>
                            <option value="named">Named Donors</option>
                            <option value="anonymous">Anonymous</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Amount Range</label>
                        <select id="amountFilter" class="filter-select">
                            <option value="">All Amounts</option>
                            <option value="0-1000">৳0 - ৳1,000</option>
                            <option value="1000-5000">৳1,000 - ৳5,000</option>
                            <option value="5000-10000">৳5,000 - ৳10,000</option>
                            <option value="10000-50000">৳10,000 - ৳50,000</option>
                            <option value="50000+">৳50,000+</option>
                        </select>
                    </div>
                </div>
                
                <div class="filters-right">
                    <div class="filter-buttons">
                        <button class="btn-filter" onclick="applyFilters()">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        <button class="btn-clear" onclick="clearFilters()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="donations-table">
            <div class="table-header">
                <div class="col-donor">Donor</div>
                <div class="col-amount">Amount</div>
                <div class="col-purpose">Purpose</div>
                <div class="col-method">Payment</div>
                <div class="col-date">Date</div>
                <div class="col-status">Status</div>
                <div class="col-actions">Actions</div>
            </div>
            <div class="table-body">
                <?php foreach ($all_donations as $donation): ?>
                    <div class="table-row">
                        <div class="col-donor">
                            <div class="donor-name">
                                <?php echo $donation['is_anonymous'] ? 'Anonymous Donor' : htmlspecialchars($donation['donor_name']); ?>
                            </div>
                            <?php if (!$donation['is_anonymous'] && $donation['donor_email']): ?>
                                <div class="donor-email"><?php echo htmlspecialchars($donation['donor_email']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-amount">
                            <span class="amount">৳<?php echo number_format($donation['amount']); ?></span>
                        </div>
                        <div class="col-purpose">
                            <?php echo htmlspecialchars($donation['purpose']); ?>
                        </div>
                        <div class="col-method">
                            <?php echo htmlspecialchars($donation['payment_method']); ?>
                            <?php if ($donation['transaction_id']): ?>
                                <div class="transaction-id">TXN: <?php echo htmlspecialchars($donation['transaction_id']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-date">
                            <?php echo date('M j, Y', strtotime($donation['donation_date'])); ?>
                        </div>
                        <div class="col-status">
                            <span class="status-badge <?php echo $donation['is_verified'] ? 'status-verified' : 'status-pending'; ?>">
                                <?php echo $donation['is_verified'] ? 'Verified' : 'Pending'; ?>
                            </span>
                        </div>
                        <div class="col-actions">
                            <div class="action-buttons">
                                <?php if ($donation['is_verified']): ?>
                                    <button class="btn-approved" disabled>
                                        <i class="fas fa-check"></i> Approved
                                    </button>
                                <?php else: ?>
                                    <button class="btn-approve" onclick="approveDonation(<?php echo $donation['id']; ?>)">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn-ignore" onclick="ignoreDonation(<?php echo $donation['id']; ?>)">
                                        <i class="fas fa-times"></i> Ignore
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        function showCustomConfirm(title, message, onConfirm, onCancel) {
            // Create confirmation popup
            const confirmPopup = document.createElement('div');
            confirmPopup.className = 'confirm-popup';
            confirmPopup.innerHTML = `
                <div class="confirm-content">
                    <div class="confirm-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div class="confirm-title">${title}</div>
                    <div class="confirm-message">${message}</div>
                    <div class="confirm-buttons">
                        <button class="confirm-btn yes" onclick="handleConfirmYes()">
                            <i class="fas fa-check"></i> Yes, Confirm
                        </button>
                        <button class="confirm-btn no" onclick="handleConfirmNo()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            `;
            
            // Add to body
            document.body.appendChild(confirmPopup);
            
            // Show with animation
            setTimeout(() => {
                confirmPopup.classList.add('show');
            }, 10);
            
            // Handle button clicks
            window.handleConfirmYes = function() {
                confirmPopup.classList.remove('show');
                setTimeout(() => {
                    document.body.removeChild(confirmPopup);
                    if (onConfirm) onConfirm();
                }, 300);
            };
            
            window.handleConfirmNo = function() {
                confirmPopup.classList.remove('show');
                setTimeout(() => {
                    document.body.removeChild(confirmPopup);
                    if (onCancel) onCancel();
                }, 300);
            };
            
            // Close on background click
            confirmPopup.addEventListener('click', function(e) {
                if (e.target === confirmPopup) {
                    window.handleConfirmNo();
                }
            });
        }
        
        function showSuccessMessage(message) {
            console.log('showSuccessMessage called with:', message);
            
            // Create popup element
            const popup = document.createElement('div');
            popup.className = 'success-popup';
            popup.innerHTML = `
                <div class="popup-content">
                    <i class="fas fa-check-circle"></i>
                    <span>${message}</span>
                </div>
            `;
            
            // Add to body
            document.body.appendChild(popup);
            console.log('Popup added to body');
            
            // Show popup with animation
            setTimeout(() => {
                popup.classList.add('show');
                console.log('Show class added');
            }, 10);
            
            // Remove popup after 3 seconds
            setTimeout(() => {
                popup.classList.remove('show');
                setTimeout(() => {
                    if (popup.parentNode) {
                        document.body.removeChild(popup);
                        console.log('Popup removed');
                    }
                }, 300);
            }, 3000);
        }
        
        function showErrorMessage(message) {
            console.log('showErrorMessage called with:', message);
            
            // Create popup element
            const popup = document.createElement('div');
            popup.className = 'error-popup';
            popup.innerHTML = `
                <div class="popup-content">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>${message}</span>
                </div>
            `;
            
            // Add to body
            document.body.appendChild(popup);
            console.log('Error popup added to body');
            
            // Show popup with animation
            setTimeout(() => {
                popup.classList.add('show');
                console.log('Error show class added');
            }, 10);
            
            // Remove popup after 3 seconds
            setTimeout(() => {
                popup.classList.remove('show');
                setTimeout(() => {
                    if (popup.parentNode) {
                        document.body.removeChild(popup);
                        console.log('Error popup removed');
                    }
                }, 300);
            }, 3000);
        }
        
        function approveDonation(donationId) {
            showCustomConfirm(
                'Approve Donation',
                'Are you sure you want to approve this donation? This action will mark the donation as verified.',
                function() {
                    // User confirmed - proceed with approval
                    const formData = new FormData();
                    formData.append('action', 'approve_donation');
                    formData.append('donation_id', donationId);
                    
                    fetch('donations_management.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.success) {
                            showSuccessMessage('Donation approved successfully!');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showErrorMessage('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showErrorMessage('An error occurred while approving the donation');
                    });
                },
                function() {
                    // User cancelled - do nothing
                    console.log('Approval cancelled');
                }
            );
        }

        function ignoreDonation(donationId) {
            showCustomConfirm(
                'Ignore Donation',
                'Are you sure you want to ignore this donation? This action will mark the donation as not verified.',
                function() {
                    // User confirmed - proceed with ignoring
                    const formData = new FormData();
                    formData.append('action', 'ignore_donation');
                    formData.append('donation_id', donationId);
                    
                    fetch('donations_management.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.success) {
                            showSuccessMessage('Donation ignored successfully!');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showErrorMessage('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showErrorMessage('An error occurred while ignoring the donation');
                    });
                },
                function() {
                    // User cancelled - do nothing
                    console.log('Ignore cancelled');
                }
            );
        }


        // Filter functions
        function applyFilters() {
            const statusFilter = document.getElementById('statusFilter').value;
            const paymentFilter = document.getElementById('paymentFilter').value;
            const purposeFilter = document.getElementById('purposeFilter').value;
            const donorFilter = document.getElementById('donorFilter').value;
            const amountFilter = document.getElementById('amountFilter').value;
            
            const rows = document.querySelectorAll('.table-row');
            let visibleCount = 0;
            
            rows.forEach(row => {
                let showRow = true;
                
                // Status filter
                if (statusFilter) {
                    const statusBadge = row.querySelector('.status-badge');
                    const isVerified = statusBadge.classList.contains('status-verified');
                    if (statusFilter === 'verified' && !isVerified) showRow = false;
                    if (statusFilter === 'pending' && isVerified) showRow = false;
                }
                
                // Payment method filter
                if (paymentFilter && showRow) {
                    const paymentText = row.querySelector('.col-method').textContent.toLowerCase();
                    if (!paymentText.includes(paymentFilter.toLowerCase().replace('_', ' '))) {
                        showRow = false;
                    }
                }
                
                // Purpose filter
                if (purposeFilter && showRow) {
                    const purposeText = row.querySelector('.col-purpose').textContent.toLowerCase();
                    if (!purposeText.includes(purposeFilter.toLowerCase())) {
                        showRow = false;
                    }
                }
                
                // Donor type filter
                if (donorFilter && showRow) {
                    const donorText = row.querySelector('.donor-name').textContent;
                    const isAnonymous = donorText.includes('Anonymous');
                    if (donorFilter === 'anonymous' && !isAnonymous) showRow = false;
                    if (donorFilter === 'named' && isAnonymous) showRow = false;
                }
                
                // Amount filter
                if (amountFilter && showRow) {
                    const amountText = row.querySelector('.amount').textContent.replace(/[৳,]/g, '');
                    const amount = parseInt(amountText);
                    
                    switch (amountFilter) {
                        case '0-1000':
                            if (amount > 1000) showRow = false;
                            break;
                        case '1000-5000':
                            if (amount < 1000 || amount > 5000) showRow = false;
                            break;
                        case '5000-10000':
                            if (amount < 5000 || amount > 10000) showRow = false;
                            break;
                        case '10000-50000':
                            if (amount < 10000 || amount > 50000) showRow = false;
                            break;
                        case '50000+':
                            if (amount < 50000) showRow = false;
                            break;
                    }
                }
                
                // Show/hide row
                if (showRow) {
                    row.style.display = 'grid';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update count
            document.getElementById('donationCount').textContent = visibleCount;
        }
        
        function clearFilters() {
            // Reset all filter dropdowns
            document.getElementById('statusFilter').value = '';
            document.getElementById('paymentFilter').value = '';
            document.getElementById('purposeFilter').value = '';
            document.getElementById('donorFilter').value = '';
            document.getElementById('amountFilter').value = '';
            
            // Show all rows
            const rows = document.querySelectorAll('.table-row');
            rows.forEach(row => {
                row.style.display = 'grid';
            });
            
            // Reset count
            document.getElementById('donationCount').textContent = <?php echo count($all_donations); ?>;
        }
        
        // Auto-apply filters when dropdown changes
        document.addEventListener('DOMContentLoaded', function() {
            const filters = ['statusFilter', 'paymentFilter', 'purposeFilter', 'donorFilter', 'amountFilter'];
            filters.forEach(filterId => {
                document.getElementById(filterId).addEventListener('change', applyFilters);
            });
        });
    </script>
    </div>
</body>
</html>
