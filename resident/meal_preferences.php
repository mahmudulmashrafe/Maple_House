<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get resident ID
$resident_query = "SELECT id FROM residents WHERE user_id = :user_id";
$resident_stmt = $db->prepare($resident_query);
$resident_stmt->bindParam(':user_id', $_SESSION['user_id']);
$resident_stmt->execute();
$resident = $resident_stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    header("Location: ../login.php");
    exit();
}

$resident_id = $resident['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_preferences'])) {
    try {
        $db->beginTransaction();
        
        $preferences_data = $_POST['preferences'] ?? [];
        foreach ($preferences_data as $date => $meals) {
            foreach ($meals as $meal_type => $prefs) {
                $dietary_preference = $prefs['dietary_preference'] ?? null;
                $spice_level = $prefs['spice_level'] ?? null;
                $oil_preference = $prefs['oil_preference'] ?? null;
                
                if ($dietary_preference || $spice_level || $oil_preference) {
                    $stmt = $db->prepare("
                        INSERT INTO meal_preferences (resident_id, meal_date, meal_type, dietary_preference, spice_level, oil_preference, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                        dietary_preference = VALUES(dietary_preference),
                        spice_level = VALUES(spice_level),
                        oil_preference = VALUES(oil_preference),
                        updated_at = NOW()
                    ");
                    $stmt->execute([$resident_id, $date, $meal_type, $dietary_preference, $spice_level, $oil_preference]);
                }
            }
        }
        
        $db->commit();
        $success_message = "Meal preferences saved successfully!";
    } catch (Exception $e) {
        $db->rollback();
        $error_message = "Error saving preferences: " . $e->getMessage();
    }
}

// Get meals for today and tomorrow only with food items
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

try {
    $meals_query = "SELECT dm.*, 
                           -- Get meal items by category
                           GROUP_CONCAT(CASE WHEN mi.item_type = 'veg' THEN mi.item_name END SEPARATOR ', ') as veg_items,
                           GROUP_CONCAT(CASE WHEN mi.item_type = 'non_veg' THEN mi.item_name END SEPARATOR ', ') as nonveg_items,
                           GROUP_CONCAT(CASE WHEN mi.category = 'drinks' THEN mi.item_name END SEPARATOR ', ') as drink_items
                    FROM daily_meals dm
                    LEFT JOIN meal_items mi ON (
                        (dm.meal_type = 'Breakfast' AND (
                            mi.id IN (dm.breakfast_veg_item_1, dm.breakfast_veg_item_2, dm.breakfast_veg_item_3,
                                     dm.breakfast_nonveg_item_1, dm.breakfast_nonveg_item_2, dm.breakfast_nonveg_item_3,
                                     dm.breakfast_drinks_item_1, dm.breakfast_drinks_item_2)
                        )) OR
                        (dm.meal_type = 'Lunch' AND (
                            mi.id IN (dm.lunch_veg_item_1, dm.lunch_veg_item_2, dm.lunch_veg_item_3,
                                     dm.lunch_nonveg_item_1, dm.lunch_nonveg_item_2, dm.lunch_nonveg_item_3,
                                     dm.lunch_drinks_item_1, dm.lunch_drinks_item_2)
                        )) OR
                        (dm.meal_type = 'Dinner' AND (
                            mi.id IN (dm.dinner_veg_item_1, dm.dinner_veg_item_2, dm.dinner_veg_item_3,
                                     dm.dinner_nonveg_item_1, dm.dinner_nonveg_item_2, dm.dinner_nonveg_item_3,
                                     dm.dinner_drinks_item_1, dm.dinner_drinks_item_2)
                        ))
                    )
                    WHERE dm.meal_date IN (?, ?) 
                    GROUP BY dm.id, dm.meal_date, dm.meal_type
                    ORDER BY dm.meal_date, FIELD(dm.meal_type, 'Breakfast', 'Lunch', 'Dinner')";
    $meals_stmt = $db->prepare($meals_query);
    $meals_stmt->execute([$today, $tomorrow]);
    $upcoming_meals = $meals_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback: Create sample meals for today and tomorrow with sample food items
    $upcoming_meals = [];
    $sample_foods = [
        'Breakfast' => [
            'veg_items' => 'Vegetable Paratha, Mixed Vegetable Curry, Fruit Salad',
            'nonveg_items' => 'Chicken Sandwich, Egg Curry',
            'drink_items' => 'Tea, Coffee, Fresh Juice'
        ],
        'Lunch' => [
            'veg_items' => 'Dal Rice, Vegetable Biryani, Mixed Vegetables',
            'nonveg_items' => 'Chicken Curry, Fish Fry, Mutton Curry',
            'drink_items' => 'Lassi, Fresh Lime Water'
        ],
        'Dinner' => [
            'veg_items' => 'Roti, Dal, Vegetable Curry, Salad',
            'nonveg_items' => 'Chicken Roast, Fish Curry',
            'drink_items' => 'Milk, Herbal Tea'
        ]
    ];
    
    foreach ([$today, $tomorrow] as $date) {
        foreach (['Breakfast', 'Lunch', 'Dinner'] as $meal_type) {
            $upcoming_meals[] = [
                'meal_date' => $date,
                'meal_type' => $meal_type,
                'veg_items' => $sample_foods[$meal_type]['veg_items'],
                'nonveg_items' => $sample_foods[$meal_type]['nonveg_items'],
                'drink_items' => $sample_foods[$meal_type]['drink_items']
            ];
        }
    }
}

// Get existing preferences for today and tomorrow
try {
    $prefs_query = "SELECT * FROM meal_preferences 
                    WHERE resident_id = ? AND meal_date IN (?, ?)";
    $prefs_stmt = $db->prepare($prefs_query);
    $prefs_stmt->execute([$resident_id, $today, $tomorrow]);
    $existing_prefs = $prefs_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $existing_prefs = [];
}

// Organize existing preferences
$preferences = [];
foreach ($existing_prefs as $pref) {
    $preferences[$pref['meal_date']][$pref['meal_type']] = $pref;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Preferences - Maple House</title>
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

        .preferences-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .preferences-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            text-align: center;
        }

        .preferences-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .preferences-header p {
            color: #6c757d;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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

        .meals-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .meal-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .meal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .meal-date {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .meal-day {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .meal-body {
            padding: 20px;
        }

        .meal-type-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .meal-type-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .meal-type-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preference-group {
            margin-bottom: 15px;
        }

        .preference-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }

        .preference-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .preference-option {
            position: relative;
        }

        .preference-option input[type="radio"] {
            display: none;
        }

        .preference-option label {
            display: block;
            padding: 8px 16px;
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .preference-option input[type="radio"]:checked + label {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .preference-option label:hover {
            border-color: #667eea;
            background: #e3f2fd;
        }

        .special-notes {
            margin-top: 15px;
        }

        .special-notes textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            resize: vertical;
            min-height: 60px;
            font-family: inherit;
        }

        .save-button {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: block;
            margin: 0 auto;
        }

        .save-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .meal-icons {
            font-size: 1.2rem;
        }

        .breakfast-icon { color: #f39c12; }
        .lunch-icon { color: #e74c3c; }
        .dinner-icon { color: #9b59b6; }

        /* Food Items Styling */
        .food-items-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .food-category {
            margin-bottom: 12px;
        }

        .food-category:last-child {
            margin-bottom: 0;
        }

        .category-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .category-items {
            font-size: 0.85rem;
            line-height: 1.4;
            padding-left: 15px;
        }

        .veg-category .category-items {
            color: #27ae60;
        }

        .nonveg-category .category-items {
            color: #e74c3c;
        }

        .drink-category .category-items {
            color: #3498db;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .meals-grid {
                grid-template-columns: 1fr;
                max-width: 100%;
            }

            .preference-options {
                flex-direction: column;
            }

            .preference-option label {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="preferences-container">

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Preferences Form -->
        <form method="POST">
            <div class="meals-grid">
                <?php
                $grouped_meals = [];
                foreach ($upcoming_meals as $meal) {
                    $grouped_meals[$meal['meal_date']][] = $meal;
                }

                foreach ($grouped_meals as $date => $meals):
                ?>
                <div class="meal-card">
                    <div class="meal-header">
                        <?php 
                        $day_name = ($date === $today) ? 'Today' : 'Tomorrow';
                        $full_date = date('M j, Y', strtotime($date));
                        $day_of_week = date('l', strtotime($date));
                        ?>
                        <div class="meal-date"><?php echo $day_name; ?></div>
                        <div class="meal-day"><?php echo $day_of_week . ' - ' . $full_date; ?></div>
                    </div>
                    <div class="meal-body">
                        <?php foreach ($meals as $meal): 
                            $meal_type = $meal['meal_type'];
                            $existing = $preferences[$date][$meal_type] ?? null;
                        ?>
                        <div class="meal-type-section">
                            <div class="meal-type-title">
                                <?php if ($meal_type === 'Breakfast'): ?>
                                    <i class="fas fa-coffee breakfast-icon"></i>
                                <?php elseif ($meal_type === 'Lunch'): ?>
                                    <i class="fas fa-hamburger lunch-icon"></i>
                                <?php else: ?>
                                    <i class="fas fa-moon dinner-icon"></i>
                                <?php endif; ?>
                                <?php echo $meal_type; ?>
                            </div>

                            <!-- Food Items Display -->
                            <div class="food-items-section">
                                <?php if (!empty($meal['veg_items'])): ?>
                                    <div class="food-category veg-category">
                                        <div class="category-title">🥬 Vegetarian Items:</div>
                                        <div class="category-items"><?php echo $meal['veg_items']; ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($meal['nonveg_items'])): ?>
                                    <div class="food-category nonveg-category">
                                        <div class="category-title">🍖 Non-Vegetarian Items:</div>
                                        <div class="category-items"><?php echo $meal['nonveg_items']; ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($meal['drink_items'])): ?>
                                    <div class="food-category drink-category">
                                        <div class="category-title">🥤 Drinks:</div>
                                        <div class="category-items"><?php echo $meal['drink_items']; ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Dietary Preference -->
                            <div class="preference-group">
                                <span class="preference-label">Dietary Preference:</span>
                                <div class="preference-options">
                                    <div class="preference-option">
                                        <input type="radio" 
                                               id="veg_<?php echo $date.'_'.$meal_type; ?>" 
                                               name="preferences[<?php echo $date; ?>][<?php echo $meal_type; ?>][dietary_preference]" 
                                               value="Vegetarian"
                                               <?php echo ($existing && $existing['dietary_preference'] === 'Vegetarian') ? 'checked' : ''; ?>>
                                        <label for="veg_<?php echo $date.'_'.$meal_type; ?>">🥬 Vegetarian</label>
                                    </div>
                                    <div class="preference-option">
                                        <input type="radio" 
                                               id="nonveg_<?php echo $date.'_'.$meal_type; ?>" 
                                               name="preferences[<?php echo $date; ?>][<?php echo $meal_type; ?>][dietary_preference]" 
                                               value="Non-Vegetarian"
                                               <?php echo ($existing && $existing['dietary_preference'] === 'Non-Vegetarian') ? 'checked' : ''; ?>>
                                        <label for="nonveg_<?php echo $date.'_'.$meal_type; ?>">🍖 Non-Vegetarian</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Spice Level -->
                            <div class="preference-group">
                                <span class="preference-label">Spice Level:</span>
                                <div class="preference-options">
                                    <div class="preference-option">
                                        <input type="radio" 
                                               id="spicy_<?php echo $date.'_'.$meal_type; ?>" 
                                               name="preferences[<?php echo $date; ?>][<?php echo $meal_type; ?>][spice_level]" 
                                               value="Spicy"
                                               <?php echo ($existing && $existing['spice_level'] === 'Spicy') ? 'checked' : ''; ?>>
                                        <label for="spicy_<?php echo $date.'_'.$meal_type; ?>">🌶️ Spicy</label>
                                    </div>
                                    <div class="preference-option">
                                        <input type="radio" 
                                               id="medium_<?php echo $date.'_'.$meal_type; ?>" 
                                               name="preferences[<?php echo $date; ?>][<?php echo $meal_type; ?>][spice_level]" 
                                               value="Medium"
                                               <?php echo ($existing && $existing['spice_level'] === 'Medium') ? 'checked' : ''; ?>>
                                        <label for="medium_<?php echo $date.'_'.$meal_type; ?>">🌶️ Medium</label>
                                    </div>
                                    <div class="preference-option">
                                        <input type="radio" 
                                               id="non_spicy_<?php echo $date.'_'.$meal_type; ?>" 
                                               name="preferences[<?php echo $date; ?>][<?php echo $meal_type; ?>][spice_level]" 
                                               value="Non Spicy"
                                               <?php echo ($existing && $existing['spice_level'] === 'Non Spicy') ? 'checked' : ''; ?>>
                                        <label for="non_spicy_<?php echo $date.'_'.$meal_type; ?>">🥛 Non Spicy</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Oil Preference -->
                            <div class="preference-group">
                                <span class="preference-label">Oil Preference:</span>
                                <div class="preference-options">
                                    <div class="preference-option">
                                        <input type="radio" 
                                               id="lessoily_<?php echo $date.'_'.$meal_type; ?>" 
                                               name="preferences[<?php echo $date; ?>][<?php echo $meal_type; ?>][oil_preference]" 
                                               value="Less oily"
                                               <?php echo ($existing && $existing['oil_preference'] === 'Less oily') ? 'checked' : ''; ?>>
                                        <label for="lessoily_<?php echo $date.'_'.$meal_type; ?>">💧 Less oily</label>
                                    </div>
                                    <div class="preference-option">
                                        <input type="radio" 
                                               id="normal_<?php echo $date.'_'.$meal_type; ?>" 
                                               name="preferences[<?php echo $date; ?>][<?php echo $meal_type; ?>][oil_preference]" 
                                               value="Normal"
                                               <?php echo (!$existing || $existing['oil_preference'] === 'Normal') ? 'checked' : ''; ?>>
                                        <label for="normal_<?php echo $date.'_'.$meal_type; ?>">🥄 Normal</label>
                                    </div>
                                    <div class="preference-option">
                                        <input type="radio" 
                                               id="extraoily_<?php echo $date.'_'.$meal_type; ?>" 
                                               name="preferences[<?php echo $date; ?>][<?php echo $meal_type; ?>][oil_preference]" 
                                               value="Extra oily"
                                               <?php echo ($existing && $existing['oil_preference'] === 'Extra oily') ? 'checked' : ''; ?>>
                                        <label for="extraoily_<?php echo $date.'_'.$meal_type; ?>">🛢️ Extra oily</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Special Notes -->
                            <div class="special-notes">
                                <span class="preference-label">Special Notes:</span>
                                <textarea name="preferences[<?php echo $date; ?>][<?php echo $meal_type; ?>][special_notes]" 
                                          placeholder="Any special requests or dietary notes..."><?php echo $existing['special_notes'] ?? ''; ?></textarea>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" name="save_preferences" class="save-button">
                <i class="fas fa-save"></i> Save Preferences
            </button>
        </form>
    </div>
</body>
</html>
