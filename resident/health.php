<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a resident
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get resident information
$resident_query = "SELECT u.*, r.*, pp.plan_name, pp.monthly_fee, pp.laundry_limit, pp.cleaning_limit
                   FROM users u 
                   JOIN residents r ON u.id = r.user_id 
                   JOIN payment_plans pp ON r.plan_id = pp.id
                   WHERE u.id = :user_id";
$resident_stmt = $db->prepare($resident_query);
$resident_stmt->bindParam(':user_id', $_SESSION['user_id']);
$resident_stmt->execute();
$resident = $resident_stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    header("Location: ../login.php");
    exit();
}

// Get health records from database
$records_query = "SELECT hr.*, 
                  CONCAT(du.first_name, ' ', du.last_name) as doctor_name
                  FROM health_records hr
                  JOIN doctors d ON hr.doctor_id = d.id
                  JOIN users du ON d.user_id = du.id
                  WHERE hr.resident_id = :resident_id
                  ORDER BY hr.checkup_date DESC";
$records_stmt = $db->prepare($records_query);
$records_stmt->bindParam(':resident_id', $resident['id']);
$records_stmt->execute();
$health_records = $records_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate health metrics
if (count($health_records) > 0) {
    $latest_record = $health_records[0];
    $height = 165; // Default height in cm, can be added to database later
    $bmi = $latest_record['weight'] ? round($latest_record['weight'] / (($height / 100) ** 2), 1) : 0;
} else {
    $latest_record = null;
    $bmi = 0;
}

$health_score = $resident['health_score'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Records - Maple House</title>
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

        .health-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Health Overview Cards */
        .health-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .health-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #667eea;
        }

        .health-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .health-card i {
            color: #667eea;
        }

        .health-metric {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .health-metric:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .metric-label {
            color: #6c757d;
            font-weight: 500;
        }

        .metric-value {
            color: #2c3e50;
            font-weight: 600;
        }

        .metric-value.good {
            color: #28a745;
        }

        .metric-value.warning {
            color: #ffc107;
        }

        .metric-value.danger {
            color: #dc3545;
        }

        /* Health Records Table */
        .records-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h2 {
            margin: 0;
            font-size: 1.3rem;
        }

        .records-table {
            width: 100%;
            border-collapse: collapse;
        }

        .records-table th,
        .records-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .records-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .records-table tr:hover {
            background: #f8f9fa;
        }

        .date-badge {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #495057;
        }

        .doctor-name {
            color: #667eea;
            font-weight: 500;
        }

        .vital-good {
            color: #28a745;
            font-weight: 500;
        }

        .vital-warning {
            color: #ffc107;
            font-weight: 500;
        }

        .vital-danger {
            color: #dc3545;
            font-weight: 500;
        }

        .notes-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Health Score Circle */
        .health-score-container {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .health-score-circle {
            position: relative;
            width: 100px;
            height: 100px;
            margin-bottom: 10px;
        }

        .progress-circle {
            width: 100px;
            height: 100px;
            transform: rotate(-90deg);
        }

        .progress-circle-bg {
            fill: none;
            stroke: #e9ecef;
            stroke-width: 8;
        }

        .progress-circle-fill {
            fill: none;
            stroke: #28a745;
            stroke-width: 8;
            stroke-linecap: round;
            stroke-dasharray: 251.2;
            stroke-dashoffset: 251.2;
            transition: stroke-dashoffset 1s ease;
        }

        .score-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.2rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .score-label {
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .health-overview {
                grid-template-columns: 1fr;
            }

            .records-table {
                font-size: 0.9rem;
            }

            .records-table th,
            .records-table td {
                padding: 10px 8px;
            }

            .notes-cell {
                max-width: 150px;
            }
        }

        /* BMI Status Colors */
        .bmi-underweight { color: #17a2b8; }
        .bmi-normal { color: #28a745; }
        .bmi-overweight { color: #ffc107; }
        .bmi-obese { color: #dc3545; }
    </style>
</head>
<body>
    <div class="health-container">
        <!-- Health Overview Cards -->
        <div class="health-overview">
            <!-- Latest Vitals Card -->
            <div class="health-card">
                <h3><i class="fas fa-heartbeat"></i> Latest Vitals</h3>
                <?php if ($latest_record): ?>
                    <div class="health-metric">
                        <span class="metric-label">Blood Pressure</span>
                        <span class="metric-value good"><?php echo $latest_record['blood_pressure'] ?? 'N/A'; ?></span>
                    </div>
                    <div class="health-metric">
                        <span class="metric-label">Heart Rate</span>
                        <span class="metric-value good"><?php echo $latest_record['heart_rate'] ? $latest_record['heart_rate'] . ' bpm' : 'N/A'; ?></span>
                    </div>
                    <div class="health-metric">
                        <span class="metric-label">Temperature</span>
                        <span class="metric-value good"><?php echo $latest_record['temperature'] ? $latest_record['temperature'] . '°F' : 'N/A'; ?></span>
                    </div>
                    <div class="health-metric">
                        <span class="metric-label">Last Checkup</span>
                        <span class="metric-value"><?php echo date('M j, Y', strtotime($latest_record['checkup_date'])); ?></span>
                    </div>
                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 20px;">No health records available</p>
                <?php endif; ?>
            </div>

            <!-- Body Metrics Card -->
            <div class="health-card">
                <h3><i class="fas fa-weight"></i> Body Metrics</h3>
                <?php if ($latest_record): ?>
                    <div class="health-metric">
                        <span class="metric-label">Weight</span>
                        <span class="metric-value"><?php echo $latest_record['weight'] ? $latest_record['weight'] . ' kg' : 'N/A'; ?></span>
                    </div>
                    <div class="health-metric">
                        <span class="metric-label">Height</span>
                        <span class="metric-value">165 cm</span>
                    </div>
                    <div class="health-metric">
                        <span class="metric-label">BMI</span>
                        <span class="metric-value bmi-normal"><?php echo $bmi; ?></span>
                    </div>
                    <div class="health-metric">
                        <span class="metric-label">Status</span>
                        <span class="metric-value bmi-normal"><?php echo $bmi < 18.5 ? 'Underweight' : ($bmi < 25 ? 'Normal' : ($bmi < 30 ? 'Overweight' : 'Obese')); ?></span>
                    </div>
                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 20px;">No body metrics available</p>
                <?php endif; ?>
            </div>

            <!-- Health Score Card -->
            <div class="health-card">
                <h3><i class="fas fa-chart-line"></i> Health Score</h3>
                <div class="health-score-container">
                    <div class="health-score-circle">
                        <svg class="progress-circle">
                            <circle class="progress-circle-bg" cx="50" cy="50" r="40"></circle>
                            <circle class="progress-circle-fill" cx="50" cy="50" r="40" data-score="<?php echo $health_score; ?>"></circle>
                        </svg>
                        <div class="score-text"><?php echo $health_score; ?>%</div>
                    </div>
                    <div class="score-label">Overall Wellness</div>
                </div>
            </div>
        </div>

        <!-- Health Records Table -->
        <div class="records-section">
            <div class="section-header">
                <i class="fas fa-file-medical"></i>
                <h2>Health Records History</h2>
            </div>
            <table class="records-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Doctor</th>
                        <th>Blood Pressure</th>
                        <th>Heart Rate</th>
                        <th>Weight</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($health_records) > 0): ?>
                        <?php foreach ($health_records as $record): ?>
                        <tr>
                            <td>
                                <span class="date-badge">
                                    <?php echo date('M j, Y', strtotime($record['checkup_date'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="doctor-name"><?php echo htmlspecialchars($record['doctor_name']); ?></span>
                            </td>
                            <td>
                                <span class="vital-good"><?php echo $record['blood_pressure'] ?? 'N/A'; ?></span>
                            </td>
                            <td>
                                <span class="vital-good"><?php echo $record['heart_rate'] ? $record['heart_rate'] . ' bpm' : 'N/A'; ?></span>
                            </td>
                            <td>
                                <?php echo $record['weight'] ? $record['weight'] . ' kg' : 'N/A'; ?>
                            </td>
                            <td class="notes-cell" title="<?php echo htmlspecialchars($record['notes'] ?? ''); ?>">
                                <?php echo htmlspecialchars($record['notes'] ?? 'No notes'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-file-medical" style="font-size: 3rem; color: #dee2e6; margin-bottom: 15px; display: block;"></i>
                                <p>No health records found. Your doctor will add records after checkups.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Initialize health score circle animation
        document.addEventListener('DOMContentLoaded', function() {
            const progressCircle = document.querySelector('.progress-circle-fill');
            if (progressCircle) {
                const score = parseInt(progressCircle.dataset.score) || 0;
                const circumference = 2 * Math.PI * 40; // radius = 40
                const offset = circumference - (score / 100) * circumference;
                
                setTimeout(() => {
                    progressCircle.style.strokeDashoffset = offset;
                    
                    // Color based on score
                    if (score >= 80) {
                        progressCircle.style.stroke = '#28a745';
                    } else if (score >= 60) {
                        progressCircle.style.stroke = '#ffc107';
                    } else {
                        progressCircle.style.stroke = '#dc3545';
                    }
                }, 500);
            }
        });
    </script>
</body>
</html>
