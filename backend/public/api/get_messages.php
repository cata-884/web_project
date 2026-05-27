<?php
header('Content-Type: application/json');

$host = 'db';
$dbname = 'web_project_db';
$user = 'postgres';
$pass = 'password';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);

    // Extragem direct mesajele
    $stmt = $pdo->query("SELECT * FROM contact_requests ORDER BY created_at DESC");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>