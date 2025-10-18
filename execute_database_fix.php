<?php
// Execute database fixes directly through PHP code
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>🔧 Executing Database Fixes</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .step { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #007bff; }
    .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 5px 0; }
    .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 5px 0; }
    .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 5px 0; }
</style>";

try {
    $db->beginTransaction();
    
    // Step 1: Add dietary_preference column if it doesn't exist
    echo "<div class='step'><h3>Step 1: Adding dietary_preference column</h3>";
    try {
        $db->exec("ALTER TABLE `meal_preferences` ADD COLUMN `dietary_preference` enum('Vegetarian','Non-Vegetarian') DEFAULT 'Vegetarian' AFTER `meal_type`");
        echo "<div class='success'>✅ Added dietary_preference column</div>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<div class='info'>ℹ️ Column already exists</div>";
        } else {
            throw $e;
        }
    }
    echo "</div>";
    
    // Step 2: Fix resident count to exactly 6
    echo "<div class='step'><h3>Step 2: Setting exactly 6 active residents</h3>";
    
    // Deactivate all residents
    $db->exec("UPDATE `residents` SET `is_active` = 0");
    echo "<div class='success'>✅ Deactivated all residents</div>";
    
    // Create/update 6 residents
    $residents_sql = "INSERT INTO `residents` (`id`, `user_id`, `room_number`, `admission_date`, `plan_id`, `is_active`) VALUES
        (1, 1, 'R001', CURDATE(), 1, 1),
        (2, 2, 'R002', CURDATE(), 2, 1),
        (3, 3, 'R003', CURDATE(), 1, 1),
        (4, 4, 'R004', CURDATE(), 2, 1),
        (5, 5, 'R005', CURDATE(), 1, 1),
        (6, 6, 'R006', CURDATE(), 2, 1)
        ON DUPLICATE KEY UPDATE 
        `user_id` = VALUES(`user_id`),
        `room_number` = VALUES(`room_number`),
        `plan_id` = VALUES(`plan_id`),
        `is_active` = 1";
    
    $db->exec($residents_sql);
    echo "<div class='success'>✅ Created/updated 6 residents</div>";
    
    // Verify count
    $count = $db->query("SELECT COUNT(*) FROM residents WHERE is_active = 1")->fetchColumn();
    echo "<div class='info'>📊 Active residents: $count</div>";
    echo "</div>";
    
    // Step 3: Add meal items
    echo "<div class='step'><h3>Step 3: Adding meal items</h3>";
    $meal_items_sql = "INSERT IGNORE INTO `meal_items` (`id`, `item_name`, `category`, `item_type`, `ingredients`, `preparation_time`, `cost_per_serving`, `is_active`) VALUES
        (1, 'Vegetable Paratha', 'main_course', 'veg', 'Flour, Mixed Vegetables, Oil, Spices', 20, 15.00, 1),
        (2, 'Chicken Curry', 'main_course', 'non_veg', 'Chicken, Onions, Tomatoes, Spices', 45, 35.00, 1),
        (3, 'Dal Rice', 'main_course', 'veg', 'Lentils, Rice, Turmeric, Salt', 30, 12.00, 1),
        (4, 'Fish Fry', 'main_course', 'non_veg', 'Fish, Oil, Spices, Flour', 25, 40.00, 1),
        (5, 'Mixed Vegetable Curry', 'main_course', 'veg', 'Seasonal Vegetables, Spices, Oil', 35, 18.00, 1),
        (6, 'Chicken Biryani', 'main_course', 'non_veg', 'Chicken, Basmati Rice, Spices, Yogurt', 60, 50.00, 1),
        (7, 'Tea', 'drinks', 'veg', 'Tea Leaves, Milk, Sugar', 5, 5.00, 1),
        (8, 'Coffee', 'drinks', 'veg', 'Coffee Powder, Milk, Sugar', 5, 6.00, 1),
        (9, 'Fresh Juice', 'drinks', 'veg', 'Seasonal Fruits, Water', 10, 8.00, 1),
        (10, 'Lassi', 'drinks', 'veg', 'Yogurt, Sugar, Water', 5, 7.00, 1),
        (11, 'Roti', 'main_course', 'veg', 'Wheat Flour, Water, Salt', 15, 3.00, 1),
        (12, 'Mutton Curry', 'main_course', 'non_veg', 'Mutton, Onions, Spices, Oil', 90, 60.00, 1)";
    
    $db->exec($meal_items_sql);
    echo "<div class='success'>✅ Added 12 meal items</div>";
    echo "</div>";
    
    // Step 4: Add daily meals for today
    echo "<div class='step'><h3>Step 4: Adding today's meal plan</h3>";
    
    // Delete existing meals for today
    $db->exec("DELETE FROM daily_meals WHERE meal_date = CURDATE()");
    
    $daily_meals_sql = "INSERT INTO `daily_meals` (
        `meal_date`, `meal_type`, 
        `breakfast_chef_id`, `lunch_chef_id`, `dinner_chef_id`,
        `breakfast_veg_item_1`, `breakfast_veg_item_2`, `breakfast_nonveg_item_1`, `breakfast_drinks_item_1`, `breakfast_drinks_item_2`,
        `lunch_veg_item_1`, `lunch_veg_item_2`, `lunch_nonveg_item_1`, `lunch_nonveg_item_2`, `lunch_drinks_item_1`,
        `dinner_veg_item_1`, `dinner_veg_item_2`, `dinner_nonveg_item_1`, `dinner_drinks_item_1`,
        `cost_per_serving`, `created_at`
    ) VALUES
    (CURDATE(), 'Breakfast', 1, 1, 1, 1, 5, 2, 7, 8, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 25.00, NOW()),
    (CURDATE(), 'Lunch', 1, 1, 1, NULL, NULL, NULL, NULL, NULL, 3, 5, 2, 6, 10, NULL, NULL, NULL, NULL, 45.00, NOW()),
    (CURDATE(), 'Dinner', 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 11, 5, 4, 7, 35.00, NOW())";
    
    $db->exec($daily_meals_sql);
    echo "<div class='success'>✅ Added today's meal plan (3 meals)</div>";
    echo "</div>";
    
    // Step 5: Add sample meal preferences
    echo "<div class='step'><h3>Step 5: Adding sample meal preferences</h3>";
    
    // Delete existing preferences for today
    $db->exec("DELETE FROM meal_preferences WHERE meal_date = CURDATE()");
    
    $preferences_sql = "INSERT INTO `meal_preferences` (`resident_id`, `meal_date`, `meal_type`, `dietary_preference`, `spice_level`, `oil_preference`, `special_notes`) VALUES
        (1, CURDATE(), 'Breakfast', 'Vegetarian', 'Medium', 'Normal', 'Light breakfast preferred'),
        (1, CURDATE(), 'Lunch', 'Non-Vegetarian', 'Spicy', 'Less oily', 'Loves spicy food'),
        (1, CURDATE(), 'Dinner', 'Vegetarian', 'Non Spicy', 'Normal', 'Light dinner'),
        (2, CURDATE(), 'Breakfast', 'Non-Vegetarian', 'Spicy', 'Extra oily', 'Heavy breakfast'),
        (2, CURDATE(), 'Lunch', 'Non-Vegetarian', 'Medium', 'Normal', 'Regular lunch'),
        (3, CURDATE(), 'Dinner', 'Vegetarian', 'Non Spicy', 'Less oily', 'Health conscious')";
    
    $db->exec($preferences_sql);
    echo "<div class='success'>✅ Added sample preferences for 3 residents</div>";
    echo "</div>";
    
    $db->commit();
    
    // Final verification
    echo "<div class='step'><h3>✅ Final Verification</h3>";
    
    $resident_count = $db->query("SELECT COUNT(*) FROM residents WHERE is_active = 1")->fetchColumn();
    $meal_items_count = $db->query("SELECT COUNT(*) FROM meal_items WHERE is_active = 1")->fetchColumn();
    $daily_meals_count = $db->query("SELECT COUNT(*) FROM daily_meals WHERE meal_date = CURDATE()")->fetchColumn();
    $preferences_count = $db->query("SELECT COUNT(*) FROM meal_preferences WHERE meal_date = CURDATE()")->fetchColumn();
    
    echo "<div class='success'>📊 Active Residents: $resident_count</div>";
    echo "<div class='success'>🍽️ Meal Items: $meal_items_count</div>";
    echo "<div class='success'>📅 Today's Meals: $daily_meals_count</div>";
    echo "<div class='success'>⚙️ Meal Preferences: $preferences_count</div>";
    echo "</div>";
    
    echo "<div class='step' style='border-left-color: #28a745;'>";
    echo "<h3>🎉 Database Setup Complete!</h3>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ul>";
    echo "<li><a href='chef/daily.php' target='_blank'>🧑‍🍳 Test Chef Daily Menu</a> - Should show 6 residents</li>";
    echo "<li><a href='resident/dashboard.php?page=meal_preferences' target='_blank'>🍽️ Test Meal Preferences</a> - Should show dietary options</li>";
    echo "<li><a href='test_meal_system.php' target='_blank'>🧪 Run System Test</a> - Verify everything works</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    $db->rollback();
    echo "<div class='error'><h3>❌ Error occurred:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}
?>
