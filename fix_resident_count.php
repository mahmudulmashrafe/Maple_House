<?php
// Quick script to check and fix resident count
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>🔧 Resident Count Fix</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .info { background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .success { background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f2f2f2; }
</style>";

try {
    // Check current resident count
    echo "<div class='info'><h3>Step 1: Current Resident Status</h3>";
    $current_count = $db->query("SELECT COUNT(*) FROM residents WHERE is_active = 1")->fetchColumn();
    echo "<p><strong>Current active residents:</strong> $current_count</p>";
    
    $all_residents = $db->query("SELECT id, user_id, room_number, is_active FROM residents ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table><tr><th>ID</th><th>User ID</th><th>Room</th><th>Active</th></tr>";
    foreach ($all_residents as $resident) {
        $status = $resident['is_active'] ? 'Yes' : 'No';
        echo "<tr><td>{$resident['id']}</td><td>{$resident['user_id']}</td><td>{$resident['room_number']}</td><td>$status</td></tr>";
    }
    echo "</table></div>";
    
    // Fix to exactly 6 residents
    echo "<div class='info'><h3>Step 2: Fixing to Exactly 6 Residents</h3>";
    
    // Deactivate all first
    $db->exec("UPDATE residents SET is_active = 0");
    echo "<p>✅ Deactivated all residents</p>";
    
    // Create/activate exactly 6 residents
    $residents_data = [
        [1, 1, 'R001', 1],
        [2, 2, 'R002', 2], 
        [3, 3, 'R003', 1],
        [4, 4, 'R004', 2],
        [5, 5, 'R005', 1],
        [6, 6, 'R006', 2]
    ];
    
    foreach ($residents_data as $data) {
        $stmt = $db->prepare("INSERT INTO residents (id, user_id, room_number, plan_id, admission_date, is_active) 
                             VALUES (?, ?, ?, ?, CURDATE(), 1) 
                             ON DUPLICATE KEY UPDATE 
                             user_id = VALUES(user_id), 
                             room_number = VALUES(room_number), 
                             plan_id = VALUES(plan_id), 
                             is_active = 1");
        $stmt->execute($data);
    }
    echo "<p>✅ Ensured exactly 6 active residents (IDs 1-6)</p></div>";
    
    // Verify the fix
    echo "<div class='success'><h3>Step 3: Verification</h3>";
    $new_count = $db->query("SELECT COUNT(*) FROM residents WHERE is_active = 1")->fetchColumn();
    echo "<p><strong>New active resident count:</strong> $new_count</p>";
    
    $updated_residents = $db->query("SELECT id, user_id, room_number, is_active FROM residents WHERE is_active = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table><tr><th>ID</th><th>User ID</th><th>Room</th><th>Active</th></tr>";
    foreach ($updated_residents as $resident) {
        echo "<tr><td>{$resident['id']}</td><td>{$resident['user_id']}</td><td>{$resident['room_number']}</td><td>Yes</td></tr>";
    }
    echo "</table>";
    
    if ($new_count == 6) {
        echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS: Exactly 6 residents are now active!</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ ERROR: Still have $new_count residents instead of 6</p>";
    }
    echo "</div>";
    
    // Check meal preferences
    echo "<div class='info'><h3>Step 4: Current Meal Preferences</h3>";
    $today = date('Y-m-d');
    $prefs = $db->query("SELECT meal_type, COUNT(*) as count FROM meal_preferences WHERE meal_date = '$today' GROUP BY meal_type")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($prefs) > 0) {
        echo "<table><tr><th>Meal Type</th><th>Residents with Preferences</th></tr>";
        foreach ($prefs as $pref) {
            echo "<tr><td>{$pref['meal_type']}</td><td>{$pref['count']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No meal preferences set for today yet.</p>";
    }
    echo "</div>";
    
    echo "<div class='success'>";
    echo "<h3>🎯 Next Steps:</h3>";
    echo "<ol>";
    echo "<li><strong>Test Chef Daily Menu:</strong> <a href='chef/daily.php' target='_blank'>Visit Chef Daily Menu</a></li>";
    echo "<li><strong>Should now show:</strong> 'X set preferences, Y no preferences, Total: 6 residents'</li>";
    echo "<li><strong>Add Preferences:</strong> <a href='resident/dashboard.php?page=meal_preferences' target='_blank'>Set Meal Preferences</a></li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'><h3>Error:</h3><p>" . $e->getMessage() . "</p></div>";
}
?>
