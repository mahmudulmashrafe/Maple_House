<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Manager'])) {
    header('Location: ../login.php');
    exit();
}

// Get the error log file path
$error_log_path = ini_get('error_log');
if (empty($error_log_path)) {
    $error_log_path = '/Applications/XAMPP/xamppfiles/logs/php_error_log';
}

// Read last 200 lines of error log
$log_lines = [];
if (file_exists($error_log_path)) {
    $log_lines = array_slice(file($error_log_path), -200);
}

// Filter for meal plan related logs
$meal_logs = array_filter($log_lines, function($line) {
    return strpos($line, 'MEAL PLAN') !== false || 
           strpos($line, 'meal_plan.php') !== false ||
           strpos($line, 'UPDATE SQL') !== false ||
           strpos($line, 'INSERT SQL') !== false ||
           strpos($line, 'VERIFIED') !== false;
});
?>
<!DOCTYPE html>
<html>
<head>
    <title>Meal Plan Debug Logs</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
        }
        .log-entry {
            padding: 10px;
            margin: 5px 0;
            background: #2d2d2d;
            border-left: 3px solid #007acc;
            border-radius: 3px;
            overflow-x: auto;
        }
        .error {
            border-left-color: #f44747;
        }
        .success {
            border-left-color: #4ec9b0;
        }
        h1 {
            color: #4ec9b0;
        }
        .no-logs {
            color: #ce9178;
            font-style: italic;
        }
        .refresh-btn {
            background: #007acc;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .refresh-btn:hover {
            background: #005a9e;
        }
        .log-path {
            color: #ce9178;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <h1>🔍 Meal Plan Debug Logs</h1>
    <div class="log-path">Log file: <?= htmlspecialchars($error_log_path) ?></div>
    <button class="refresh-btn" onclick="location.reload()">🔄 Refresh Logs</button>
    
    <?php if (empty($meal_logs)): ?>
        <div class="no-logs">No meal plan logs found. Try adding or updating a meal first.</div>
    <?php else: ?>
        <div>
            <?php foreach ($meal_logs as $log): ?>
                <?php 
                $class = 'log-entry';
                if (strpos($log, 'ERROR') !== false || strpos($log, 'FAILED') !== false) {
                    $class .= ' error';
                } elseif (strpos($log, 'SUCCESS') !== false) {
                    $class .= ' success';
                }
                ?>
                <div class="<?= $class ?>"><?= htmlspecialchars($log) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <br>
    <a href="dashboard.php?page=meal_plan" style="color: #4ec9b0;">← Back to Meal Plan</a>
</body>
</html>
