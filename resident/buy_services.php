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
$resident_query = "SELECT u.*, r.*, pp.plan_name
                   FROM users u 
                   JOIN residents r ON u.id = r.user_id 
                   JOIN payment_plans pp ON r.plan_id = pp.id
                   WHERE u.id = :user_id";
$resident_stmt = $db->prepare($resident_query);
$resident_stmt->bindParam(':user_id', $_SESSION['user_id']);
$resident_stmt->execute();
$resident = $resident_stmt->fetch(PDO::FETCH_ASSOC);

// Service packages with prices
$service_packages = [
    [
        'name' => 'Laundry Pack',
        'icon' => 'fa-tshirt',
        'color' => '#667eea',
        'options' => [
            ['quantity' => 5, 'price' => 450, 'savings' => 50],
            ['quantity' => 10, 'price' => 850, 'savings' => 150],
            ['quantity' => 20, 'price' => 1600, 'savings' => 400]
        ],
        'unit_price' => 100
    ],
    [
        'name' => 'Room Cleaning Pack',
        'icon' => 'fa-broom',
        'color' => '#43e97b',
        'options' => [
            ['quantity' => 5, 'price' => 1400, 'savings' => 100],
            ['quantity' => 10, 'price' => 2700, 'savings' => 300],
            ['quantity' => 20, 'price' => 5200, 'savings' => 800]
        ],
        'unit_price' => 300
    ],
    [
        'name' => 'Grocery Shopping Pack',
        'icon' => 'fa-shopping-cart',
        'color' => '#f093fb',
        'options' => [
            ['quantity' => 5, 'price' => 950, 'savings' => 50],
            ['quantity' => 10, 'price' => 1850, 'savings' => 150],
            ['quantity' => 15, 'price' => 2700, 'savings' => 300]
        ],
        'unit_price' => 200
    ],
    [
        'name' => 'Emergency Care Pack',
        'icon' => 'fa-ambulance',
        'color' => '#dc3545',
        'options' => [
            ['quantity' => 2, 'price' => 2800, 'savings' => 200],
            ['quantity' => 5, 'price' => 6750, 'savings' => 750],
            ['quantity' => 10, 'price' => 13000, 'savings' => 2000]
        ],
        'unit_price' => 1500
    ],
    [
        'name' => 'Doctor Appointment Pack',
        'icon' => 'fa-stethoscope',
        'color' => '#fa709a',
        'options' => [
            ['quantity' => 5, 'price' => 450, 'savings' => 50],
            ['quantity' => 10, 'price' => 850, 'savings' => 150],
            ['quantity' => 20, 'price' => 1600, 'savings' => 400]
        ],
        'unit_price' => 100
    ],
    [
        'name' => 'Transportation Pack',
        'icon' => 'fa-car',
        'color' => '#ffc107',
        'options' => [
            ['quantity' => 5, 'price' => 450, 'savings' => 50],
            ['quantity' => 10, 'price' => 850, 'savings' => 150],
            ['quantity' => 20, 'price' => 1600, 'savings' => 400]
        ],
        'unit_price' => 100
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy Service Packs - Maple House</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: #f8f9fa;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 15px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 2rem;
        }

        .page-header p {
            margin: 0;
            opacity: 0.9;
        }

        .packages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .package-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .package-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .package-header {
            padding: 25px;
            color: white;
            text-align: center;
        }

        .package-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .package-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .unit-price {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .package-options {
            padding: 25px;
        }

        .option-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .option-card:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .option-card.popular {
            border-color: #28a745;
            background: #d4edda;
        }

        .popular-badge {
            position: absolute;
            top: -10px;
            right: 10px;
            background: #28a745;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .option-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .option-quantity {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .option-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }

        .option-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: #666;
        }

        .savings {
            color: #28a745;
            font-weight: 600;
        }

        .buy-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .buy-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Payment Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }

        .close {
            color: white;
            font-size: 2rem;
            font-weight: 300;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            opacity: 0.8;
        }

        .modal-body {
            padding: 25px;
            overflow-y: auto;
            flex: 1;
        }

        .payment-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .summary-row.total {
            border-top: 2px solid #dee2e6;
            padding-top: 10px;
            margin-top: 10px;
            font-size: 1.2rem;
            font-weight: 700;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .payment-option {
            background: white;
            border: 2px solid #e9ecef;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-option:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
        }

        .payment-option.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .payment-option i {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .packages-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Packages Grid -->
        <div class="packages-grid">
            <?php foreach ($service_packages as $package): ?>
            <div class="package-card">
                <div class="package-header" style="background: linear-gradient(135deg, <?php echo $package['color']; ?> 0%, <?php echo $package['color']; ?>dd 100%);">
                    <div class="package-icon">
                        <i class="fas <?php echo $package['icon']; ?>"></i>
                    </div>
                    <div class="package-name"><?php echo $package['name']; ?></div>
                    <div class="unit-price">৳<?php echo number_format($package['unit_price']); ?> per service</div>
                </div>
                <div class="package-options">
                    <?php foreach ($package['options'] as $index => $option): ?>
                    <div class="option-card <?php echo $index === 1 ? 'popular' : ''; ?>" onclick="openPaymentModal('<?php echo $package['name']; ?>', <?php echo $option['quantity']; ?>, <?php echo $option['price']; ?>, <?php echo $option['savings']; ?>)">
                        <?php if ($index === 1): ?>
                            <span class="popular-badge">POPULAR</span>
                        <?php endif; ?>
                        <div class="option-header">
                            <div class="option-quantity"><?php echo $option['quantity']; ?> Services</div>
                            <div class="option-price">৳<?php echo number_format($option['price']); ?></div>
                        </div>
                        <div class="option-details">
                            <span>৳<?php echo number_format($option['price'] / $option['quantity']); ?> per service</span>
                            <span class="savings">Save ৳<?php echo number_format($option['savings']); ?></span>
                        </div>
                        <button class="buy-btn">
                            <i class="fas fa-shopping-cart"></i> Buy Now
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Complete Purchase</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="payment-summary">
                    <div class="summary-row">
                        <span>Package:</span>
                        <strong id="packageName"></strong>
                    </div>
                    <div class="summary-row">
                        <span>Quantity:</span>
                        <strong id="packageQuantity"></strong>
                    </div>
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span id="subtotal"></span>
                    </div>
                    <div class="summary-row">
                        <span style="color: #28a745;">Savings:</span>
                        <span style="color: #28a745; font-weight: 600;">৳<span id="savings"></span></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span style="color: #667eea;">৳<span id="totalPrice"></span></span>
                    </div>
                </div>

                <h3 style="margin-bottom: 15px; color: #2c3e50;">Select Payment Method</h3>
                <div class="payment-methods">
                    <div class="payment-option" onclick="selectPayment('bkash')">
                        <i class="fas fa-mobile-alt" style="color: #e2136e;"></i>
                        <div style="font-weight: 600;">bKash</div>
                    </div>
                    <div class="payment-option" onclick="selectPayment('rocket')">
                        <i class="fas fa-rocket" style="color: #8b3a9c;"></i>
                        <div style="font-weight: 600;">Rocket</div>
                    </div>
                    <div class="payment-option" onclick="selectPayment('nagad')">
                        <i class="fas fa-money-bill-wave" style="color: #f47920;"></i>
                        <div style="font-weight: 600;">Nagad</div>
                    </div>
                    <div class="payment-option" onclick="selectPayment('visa')">
                        <i class="fab fa-cc-visa" style="color: #1a1f71;"></i>
                        <div style="font-weight: 600;">Visa Card</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentPackage = {};

        function openPaymentModal(packageName, quantity, price, savings) {
            currentPackage = { packageName, quantity, price, savings };
            
            document.getElementById('packageName').textContent = packageName;
            document.getElementById('packageQuantity').textContent = quantity + ' services';
            document.getElementById('subtotal').textContent = '৳' + (price + savings).toLocaleString();
            document.getElementById('savings').textContent = savings.toLocaleString();
            document.getElementById('totalPrice').textContent = price.toLocaleString();
            
            document.getElementById('paymentModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('paymentModal').style.display = 'none';
            document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('selected'));
        }

        function selectPayment(method) {
            // Update UI
            document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            
            // Simulate realistic payment flow
            setTimeout(() => {
                // Show processing overlay
                const processingDiv = document.createElement('div');
                processingDiv.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.8);
                    z-index: 10001;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                `;
                processingDiv.innerHTML = `
                    <div style="background: white; padding: 40px; border-radius: 15px; text-align: center; max-width: 400px;">
                        <div style="width: 80px; height: 80px; margin: 0 auto 20px; border: 5px solid #f3f3f3; border-top: 5px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                        <h3 style="color: #2c3e50; margin-bottom: 10px;">Processing Payment</h3>
                        <p style="color: #666; margin-bottom: 5px;">Connecting to ${method.toUpperCase()} gateway...</p>
                        <p style="color: #999; font-size: 0.9rem;">Amount: ৳${currentPackage.price.toLocaleString()}</p>
                        <style>
                            @keyframes spin {
                                0% { transform: rotate(0deg); }
                                100% { transform: rotate(360deg); }
                            }
                        </style>
                    </div>
                `;
                document.body.appendChild(processingDiv);
                
                // Simulate payment processing and save to database
                setTimeout(async () => {
                    try {
                        // Call API to save purchase
                        const response = await fetch('../api/purchase_service.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                service_name: currentPackage.packageName,
                                quantity: currentPackage.quantity,
                                price: currentPackage.price,
                                payment_method: method
                            })
                        });
                        
                        const result = await response.json();
                        
                        document.body.removeChild(processingDiv);
                        closeModal();
                        
                        if (result.success) {
                            showSuccessNotification(
                                'Payment Successful!',
                                `${currentPackage.quantity} services of "${currentPackage.packageName}" added to your account. You saved ৳${currentPackage.savings}!`
                            );
                            setTimeout(() => location.reload(), 2500);
                        } else {
                            showErrorNotification('Purchase Failed', result.message);
                        }
                    } catch (error) {
                        document.body.removeChild(processingDiv);
                        closeModal();
                        showErrorNotification('Error', 'Failed to process purchase. Please try again.');
                    }
                }, 2000);
            }, 300);
        }
        
        function showSuccessNotification(title, message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                padding: 20px 25px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                z-index: 10000;
                min-width: 350px;
                border-left: 5px solid #28a745;
                animation: slideIn 0.3s ease;
            `;
            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; background: #d4edda; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-check" style="color: #28a745; font-size: 1.5rem;"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 700; color: #2c3e50; margin-bottom: 5px;">${title}</div>
                        <div style="color: #666; font-size: 0.9rem;">${message}</div>
                    </div>
                </div>
            `;
            document.body.appendChild(notification);
            
            // Add animation styles
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideIn {
                    from {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                @keyframes slideOut {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        
        function showErrorNotification(title, message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                padding: 20px 25px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                z-index: 10000;
                min-width: 350px;
                border-left: 5px solid #dc3545;
                animation: slideIn 0.3s ease;
            `;
            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; background: #f8d7da; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-times" style="color: #dc3545; font-size: 1.5rem;"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 700; color: #2c3e50; margin-bottom: 5px;">${title}</div>
                        <div style="color: #666; font-size: 0.9rem;">${message}</div>
                    </div>
                </div>
            `;
            document.body.appendChild(notification);
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('paymentModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
