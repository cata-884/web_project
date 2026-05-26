<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Preluăm ID-ul utilizatorului din link (ex: check_partner.php?user_id=4)
$userId = $_GET['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID lipsa.']);
    exit;
}

$host = 'db'; // sau 'localhost', exact cum ai în submit_partner.php
$dbname = 'web_project_db'; // Pune numele corect al bazei tale
$user = 'postgres';
$pass = 'password'; // Parola ta de pgAdmin

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Căutăm dacă există vreo cerere pentru acest utilizator
    $stmt = $pdo->prepare("SELECT id, status FROM organizer_verifications WHERE user_id = :user_id LIMIT 1");
    $stmt->execute([':user_id' => $userId]);
    $cerere = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cerere) {
        // Dacă am găsit un rând, înseamnă că a aplicat deja
        echo json_encode(['success' => true, 'has_applied' => true, 'status' => $cerere['status']]);
    } else {
        // Nu a aplicat încă
        echo json_encode(['success' => true, 'has_applied' => false]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Eroare BD: ' . $e->getMessage()]);
}
?>