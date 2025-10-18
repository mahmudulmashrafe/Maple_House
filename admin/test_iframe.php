<!DOCTYPE html>
<html>
<head>
    <title>Test Iframe Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f0f0f0;
        }
        .test-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="test-content">
        <h1>🎉 Test Iframe Page</h1>
        <p>If you can see this, the iframe loading is working!</p>
        <p>Current time: <?= date('Y-m-d H:i:s') ?></p>
        <p>Session user: <?= $_SESSION['user_id'] ?? 'Not logged in' ?></p>
        
        <div style="background: #e8f5e8; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <strong>✅ Success!</strong> The admin dashboard iframe system is working correctly.
        </div>
        
        <p><a href="contact_messages.php" style="color: #007cba; text-decoration: none;">→ Go to Contact Messages</a></p>
    </div>
</body>
</html>
