<?php
require_once __DIR__ . '/../../config/bootstrap.php';

header('Content-Type: application/json');

if (isLoggedIn()) {
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => (int)$_SESSION['user_id'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['role']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
}
