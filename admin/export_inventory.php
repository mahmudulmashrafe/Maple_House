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

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="inventory_report_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Get inventory data
$query = "SELECT i.name, i.description, c.name as category, u.name as unit, u.abbreviation,
          i.current_stock, i.minimum_stock, i.maximum_stock, i.unit_cost,
          (i.current_stock * i.unit_cost) as total_value,
          i.supplier_name, i.supplier_contact, i.location, i.expiry_date,
          CASE WHEN i.current_stock <= i.minimum_stock THEN 'Low Stock' ELSE 'Normal' END as status,
          i.created_at, i.updated_at
          FROM inventory_items i
          LEFT JOIN inventory_categories c ON i.category_id = c.id
          LEFT JOIN inventory_units u ON i.unit_id = u.id
          WHERE i.is_active = TRUE
          ORDER BY c.name, i.name";

$stmt = $db->prepare($query);
$stmt->execute();
$inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output CSV
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Headers
fputcsv($output, [
    'Item Name',
    'Description', 
    'Category',
    'Unit',
    'Current Stock',
    'Minimum Stock',
    'Maximum Stock',
    'Unit Cost (৳)',
    'Total Value (৳)',
    'Status',
    'Supplier',
    'Supplier Contact',
    'Location',
    'Expiry Date',
    'Created',
    'Last Updated'
]);

// CSV Data
foreach ($inventory_items as $item) {
    fputcsv($output, [
        $item['name'],
        $item['description'],
        $item['category'],
        $item['unit'] . ' (' . $item['abbreviation'] . ')',
        number_format($item['current_stock'], 2),
        number_format($item['minimum_stock'], 2),
        number_format($item['maximum_stock'], 2),
        number_format($item['unit_cost'], 2),
        number_format($item['total_value'], 2),
        $item['status'],
        $item['supplier_name'],
        $item['supplier_contact'],
        $item['location'],
        $item['expiry_date'],
        $item['created_at'],
        $item['updated_at']
    ]);
}

fclose($output);
exit();
?>
