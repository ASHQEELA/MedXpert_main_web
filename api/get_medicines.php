<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');
require_login();

$search = clean($_GET['search'] ?? '', 100);
$category = clean($_GET['category'] ?? 'all', 100);

$sql = "SELECT * FROM medicines WHERE status = 'available'";
$types = '';
$params = [];

if ($category !== '' && $category !== 'all') {
    $sql .= " AND category = ?";
    $types .= 's';
    $params[] = $category;
}

if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $sql .= " AND (name LIKE ? OR category LIKE ? OR manufacturer LIKE ?)";
    $types .= 'sss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY name ASC LIMIT 200";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$public = ['id', 'name', 'category', 'price', 'stock', 'description', 'manufacturer', 'dosage', 'image', 'prescription_required'];

$medicines = [];
while ($row = $result->fetch_assoc()) {
    $medicines[] = array_intersect_key($row, array_flip($public));
}

echo json_encode(['success' => true, 'medicines' => $medicines]);
