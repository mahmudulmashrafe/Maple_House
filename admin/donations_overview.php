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

// Get donation statistics
$stats_query = "SELECT 
                    COUNT(*) as total_donations,
                    COUNT(CASE WHEN is_verified = 1 THEN 1 END) as verified_donations,
                    COUNT(CASE WHEN is_verified = 0 THEN 1 END) as pending_donations,
                    SUM(CASE WHEN is_verified = 1 THEN amount ELSE 0 END) as total_verified_amount
                FROM donations";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent donations
$recent_donations_query = "SELECT * FROM donations ORDER BY created_at DESC LIMIT 8";
$recent_donations_stmt = $db->prepare($recent_donations_query);
$recent_donations_stmt->execute();
$recent_donations = $recent_donations_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending donations
$pending_donations_query = "SELECT * FROM donations WHERE is_verified = 0 ORDER BY created_at DESC LIMIT 6";
$pending_donations_stmt = $db->prepare($pending_donations_query);
$pending_donations_stmt->execute();
$pending_donations = $pending_donations_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top donors (verified donations only)
$top_donors_query = "SELECT donor_name, donor_email, SUM(amount) as total_donated, COUNT(*) as donation_count
                     FROM donations 
                     WHERE is_verified = 1 AND is_anonymous = 0 AND donor_name IS NOT NULL
                     GROUP BY donor_name, donor_email
                     ORDER BY total_donated DESC
                     LIMIT 6";
$top_donors_stmt = $db->prepare($top_donors_query);
$top_donors_stmt->execute();
$top_donors = $top_donors_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get donation purposes breakdown
$purposes_query = "SELECT purpose, COUNT(*) as count, SUM(amount) as total_amount
                   FROM donations 
                   WHERE is_verified = 1
                   GROUP BY purpose
                   ORDER BY total_amount DESC
                   LIMIT 6";
$purposes_stmt = $db->prepare($purposes_query);
$purposes_stmt->execute();
$purposes = $purposes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all donations for the expandable section
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
    <title>Donations Overview - Maple House</title>
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
        
        .donations-buttons {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 40px;
        }
        
        @media (max-width: 768px) {
            .donations-buttons {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .donations-buttons {
                grid-template-columns: 1fr;
            }
        }
        
        .donation-card {
            background: white;
            border-radius: 15px;
            padding: 20px 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            text-decoration: none;
            color: inherit;
        }
        
        .donation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #3498db;
        }
        
        .donation-card.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .donation-card.verified {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        
        .donation-card.pending {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .donation-card.amount {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            color: #333;
        }
        
        .donation-card i {
            font-size: 2.5rem;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .donation-count {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .donation-label {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .donation-desc {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .recent-sections {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }
        
        @media (max-width: 768px) {
            .recent-sections {
                grid-template-columns: 1fr;
            }
        }
        
        .recent-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-header {
            padding: 20px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header.recent {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .section-header.pending {
            background: linear-gradient(135deg, #f093fb, #f5576c);
        }
        
        .section-header.donors {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
        }
        
        .section-header.purposes {
            background: linear-gradient(135deg, #ffecd2, #fcb69f);
            color: #333;
        }
        
        .section-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }
        
        .section-content {
            padding: 0;
        }
        
        .donation-row {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s ease;
        }
        
        .donation-row:last-child {
            border-bottom: none;
        }
        
        .donation-row:hover {
            background: #f8f9fa;
        }
        
        .donation-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .donation-details {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.4;
        }
        
        .donation-meta {
            font-size: 0.8rem;
            color: #888;
            margin-top: 5px;
        }
        
        .status-badge {
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
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
        
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #666;
        }
        
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        .view-all-btn {
            display: block;
            width: 100%;
            padding: 15px;
            background: #f8f9fa;
            color: #3498db;
            text-align: center;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            border-top: 1px solid #e9ecef;
            border: none;
            cursor: pointer;
        }
        
        .view-all-btn:hover {
            background: #3498db;
            color: white;
        }

        /* All Donations Section */
        .all-donations-section {
            background: white;
            border-radius: 15px;
            margin-top: 30px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-header.all-donations {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .donations-table {
            overflow-x: auto;
        }

        .table-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr 1.5fr 1fr 1fr;
            gap: 15px;
            padding: 15px 20px;
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 1px solid #e9ecef;
        }

        .table-body {
            max-height: 500px;
            overflow-y: auto;
        }

        .table-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr 1.5fr 1fr 1fr;
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

        @media (max-width: 768px) {
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
            
            .table-row > div {
                display: flex;
                justify-content: space-between;
                padding: 5px 0;
            }
            
            .table-row > div:before {
                content: attr(data-label);
                font-weight: 600;
                color: #666;
            }
        }
    </style>
</head>
<body>
    <!-- Donations Buttons -->
    <div class="donations-buttons">
        <a href="donations_management_fixed.php" class="donation-card total">
            <i class="fas fa-heart"></i>
            <div class="donation-count"><?php echo $stats['total_donations']; ?></div>
            <div class="donation-label">Total Donations</div>
            <div class="donation-desc">All donations received</div>
        </a>
        
        <a href="donations.php?filter=verified" class="donation-card verified">
            <i class="fas fa-check-circle"></i>
            <div class="donation-count"><?php echo $stats['verified_donations']; ?></div>
            <div class="donation-label">Verified</div>
            <div class="donation-desc">Approved donations</div>
        </a>
        
        <a href="donations.php?filter=pending" class="donation-card pending">
            <i class="fas fa-clock"></i>
            <div class="donation-count"><?php echo $stats['pending_donations']; ?></div>
            <div class="donation-label">Pending</div>
            <div class="donation-desc">Awaiting verification</div>
        </a>
        
        <a href="donations.php?view=reports" class="donation-card amount">
            <i class="fas fa-money-bill-wave"></i>
            <div class="donation-count">৳<?php echo number_format($stats['total_verified_amount']); ?></div>
            <div class="donation-label">Total Amount</div>
            <div class="donation-desc">Verified donations</div>
        </a>
    </div>

    <!-- Recent Sections -->
    <div class="recent-sections">
        <!-- Recent Donations -->
        <div class="recent-section">
            <div class="section-header recent">
                <i class="fas fa-heart"></i>
                <h3>Recent Donations</h3>
            </div>
            <div class="section-content">
                <?php if (!empty($recent_donations)): ?>
                    <?php foreach (array_slice($recent_donations, 0, 4) as $donation): ?>
                        <div class="donation-row">
                            <div class="donation-name">
                                <?php echo $donation['is_anonymous'] ? 'Anonymous Donor' : htmlspecialchars($donation['donor_name']); ?>
                            </div>
                            <div class="donation-details">
                                Amount: ৳<?php echo number_format($donation['amount']); ?> • 
                                Purpose: <?php echo htmlspecialchars($donation['purpose']); ?>
                                <span class="status-badge <?php echo $donation['is_verified'] ? 'status-verified' : 'status-pending'; ?>">
                                    <?php echo $donation['is_verified'] ? 'Verified' : 'Pending'; ?>
                                </span>
                            </div>
                            <div class="donation-meta">
                                <?php echo htmlspecialchars($donation['payment_method']); ?> • 
                                <?php echo date('M j, Y', strtotime($donation['donation_date'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <button onclick="toggleAllDonations()" class="view-all-btn" id="toggleAllBtn">
                        <i class="fas fa-arrow-down"></i> Show All Donations
                    </button>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-heart"></i>
                        <p>No donations found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Donations -->
        <div class="recent-section">
            <div class="section-header pending">
                <i class="fas fa-clock"></i>
                <h3>Pending Verification</h3>
            </div>
            <div class="section-content">
                <?php if (!empty($pending_donations)): ?>
                    <?php foreach (array_slice($pending_donations, 0, 4) as $donation): ?>
                        <div class="donation-row">
                            <div class="donation-name">
                                <?php echo $donation['is_anonymous'] ? 'Anonymous Donor' : htmlspecialchars($donation['donor_name']); ?>
                            </div>
                            <div class="donation-details">
                                Amount: ৳<?php echo number_format($donation['amount']); ?> • 
                                <?php echo htmlspecialchars($donation['purpose']); ?>
                            </div>
                            <div class="donation-meta">
                                TXN: <?php echo htmlspecialchars($donation['transaction_id']); ?> • 
                                <?php echo date('M j, Y', strtotime($donation['donation_date'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="donations.php?filter=pending" class="view-all-btn">
                        <i class="fas fa-arrow-right"></i> View All Pending
                    </a>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>All donations verified!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Donors -->
        <div class="recent-section">
            <div class="section-header donors">
                <i class="fas fa-users"></i>
                <h3>Top Donors</h3>
            </div>
            <div class="section-content">
                <?php if (!empty($top_donors)): ?>
                    <?php foreach (array_slice($top_donors, 0, 4) as $donor): ?>
                        <div class="donation-row">
                            <div class="donation-name"><?php echo htmlspecialchars($donor['donor_name']); ?></div>
                            <div class="donation-details">
                                Total Donated: ৳<?php echo number_format($donor['total_donated']); ?> • 
                                <?php echo $donor['donation_count']; ?> donation<?php echo $donor['donation_count'] > 1 ? 's' : ''; ?>
                            </div>
                            <div class="donation-meta">
                                <?php echo htmlspecialchars($donor['donor_email']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="donations.php?view=donors" class="view-all-btn">
                        <i class="fas fa-arrow-right"></i> View All Donors
                    </a>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No donor data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Donation Purposes -->
        <div class="recent-section">
            <div class="section-header purposes">
                <i class="fas fa-chart-pie"></i>
                <h3>Donation Purposes</h3>
            </div>
            <div class="section-content">
                <?php if (!empty($purposes)): ?>
                    <?php foreach (array_slice($purposes, 0, 4) as $purpose): ?>
                        <div class="donation-row">
                            <div class="donation-name"><?php echo htmlspecialchars($purpose['purpose']); ?></div>
                            <div class="donation-details">
                                Total: ৳<?php echo number_format($purpose['total_amount']); ?> • 
                                <?php echo $purpose['count']; ?> donation<?php echo $purpose['count'] > 1 ? 's' : ''; ?>
                            </div>
                            <div class="donation-meta">
                                Average: ৳<?php echo number_format($purpose['total_amount'] / $purpose['count']); ?> per donation
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="donations.php?view=purposes" class="view-all-btn">
                        <i class="fas fa-arrow-right"></i> View All Purposes
                    </a>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-pie"></i>
                        <p>No purpose data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- All Donations Section (Hidden by default) -->
    <div id="allDonationsSection" class="all-donations-section" style="display: none;">
        <div class="section-header all-donations">
            <i class="fas fa-list"></i>
            <h3>All Donations (<?php echo count($all_donations); ?> total)</h3>
        </div>
        <div class="donations-table">
            <div class="table-header">
                <div class="col-donor">Donor</div>
                <div class="col-amount">Amount</div>
                <div class="col-purpose">Purpose</div>
                <div class="col-method">Payment</div>
                <div class="col-date">Date</div>
                <div class="col-status">Status</div>
            </div>
            <div class="table-body">
                <?php foreach ($all_donations as $donation): ?>
                    <div class="table-row">
                        <div class="col-donor" data-label="Donor">
                            <div class="donor-name">
                                <?php echo $donation['is_anonymous'] ? 'Anonymous Donor' : htmlspecialchars($donation['donor_name']); ?>
                            </div>
                            <?php if (!$donation['is_anonymous'] && $donation['donor_email']): ?>
                                <div class="donor-email"><?php echo htmlspecialchars($donation['donor_email']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-amount" data-label="Amount">
                            <span class="amount">৳<?php echo number_format($donation['amount']); ?></span>
                        </div>
                        <div class="col-purpose" data-label="Purpose">
                            <?php echo htmlspecialchars($donation['purpose']); ?>
                        </div>
                        <div class="col-method" data-label="Payment">
                            <?php echo htmlspecialchars($donation['payment_method']); ?>
                            <?php if ($donation['transaction_id']): ?>
                                <div class="transaction-id">TXN: <?php echo htmlspecialchars($donation['transaction_id']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-date" data-label="Date">
                            <?php echo date('M j, Y', strtotime($donation['donation_date'])); ?>
                        </div>
                        <div class="col-status" data-label="Status">
                            <span class="status-badge <?php echo $donation['is_verified'] ? 'status-verified' : 'status-pending'; ?>">
                                <?php echo $donation['is_verified'] ? 'Verified' : 'Pending'; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleAllDonations() {
            const section = document.getElementById('allDonationsSection');
            const button = document.getElementById('toggleAllBtn');
            const icon = button.querySelector('i');
            
            if (section.style.display === 'none') {
                section.style.display = 'block';
                button.innerHTML = '<i class="fas fa-arrow-up"></i> Hide All Donations';
                section.scrollIntoView({ behavior: 'smooth' });
            } else {
                section.style.display = 'none';
                button.innerHTML = '<i class="fas fa-arrow-down"></i> Show All Donations';
            }
        }
    </script>
</body>
</html>
