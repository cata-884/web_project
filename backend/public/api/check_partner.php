<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Preluam ID-ul utilizatorului din link (ex: check_partner.php?user_id=4)
$userId = $_GET['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID lipsa.']);
    exit;
}

$host = 'db'; // sau 'localhost', exact cum ai in submit_partner.php
$dbname = 'web_project_db'; // Pune numele corect al bazei tale
$user = 'postgres';
$pass = 'password'; // Parola ta de pgAdmin

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Cautam daca exista vreo cerere pentru acest utilizator
    $stmt = $pdo->prepare("SELECT id, status FROM organizer_verifications WHERE user_id = :user_id LIMIT 1");
    $stmt->execute([':user_id' => $userId]);
    $cerere = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cerere) {
        // Daca am gasit un rand, inseamna ca a aplicat deja
        echo json_encode(['success' => true, 'has_applied' => true, 'status' => $cerere['status']]);
    } else {
        // Nu a aplicat inca
        echo json_encode(['success' => true, 'has_applied' => false]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Eroare BD: ' . $e->getMessage()]);
}
?>