<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');
require_login();

$search = clean($_GET['search'] ?? '', 100);
$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$blood_type = pick($_GET['blood_type'] ?? 'all', $bloodTypes, 'all');

$sql = "SELECT * FROM blood_donors WHERE available = 1";
$types = '';
$params = [];

if ($blood_type !== 'all') {
    $sql .= " AND blood_type = ?";
    $types .= 's';
    $params[] = $blood_type;
}

if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $sql .= " AND (name LIKE ? OR location LIKE ?)";
    $types .= 'ss';
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

// Donor email, emergency contact, and medical conditions stay private
$public = ['id', 'name', 'blood_type', 'age', 'phone', 'location', 'last_donation', 'image'];

$donors = [];
while ($row = $result->fetch_assoc()) {
    $donors[] = array_intersect_key($row, array_flip($public));
}

echo json_encode(['success' => true, 'donors' => $donors]);
