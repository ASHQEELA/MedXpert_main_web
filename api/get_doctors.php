<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');
require_login();

$search = clean($_GET['search'] ?? '', 100);

$sql = "SELECT * FROM doctors WHERE status = 'active'";
$types = '';
$params = [];

if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $sql .= " AND (name LIKE ? OR specialization LIKE ? OR hospital LIKE ? OR location LIKE ?)";
    $types = 'ssss';
    $params = [$like, $like, $like, $like];
}

$sql .= " ORDER BY rating DESC, name ASC LIMIT 200";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Send only the fields the page needs
$public = ['id', 'name', 'specialization', 'experience', 'qualification', 'hospital', 'location', 'fee', 'rating', 'phone', 'available_days', 'available_time', 'bio', 'image', 'verified'];

$doctors = [];
while ($row = $result->fetch_assoc()) {
    $doctors[] = array_intersect_key($row, array_flip($public));
}

echo json_encode(['success' => true, 'doctors' => $doctors]);
