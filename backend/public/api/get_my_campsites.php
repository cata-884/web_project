<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$host = 'db';
$dbname = 'web_project_db';
$user = 'postgres';
$pass = 'password';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // logica de autentificare cu token
    $headers = apache_request_headers();
    if (!isset($headers['Authorization']) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (empty($headers['Authorization']) || !preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        echo json_encode(['success' => false, 'message' => 'Eroare: Nu ești logat! (Token lipsă)']);
        exit;
    }

    $token = $matches[1];

    // Cautam tokenul in baza de date
$stmtUser = $pdo->prepare("SELECT user_id FROM sesiuni WHERE token = ? LIMIT 1");
    $stmtUser->execute([$token]);
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        echo json_encode(['success' => false, 'message' => 'Eroare: Token invalid sau expirat.']);
        exit;
    }

    // Am gasit userul real!
    $user_id = $userRow['user_id'];

    // Interogare SQL: Luam campingurile utilizatorului curent si doar poza de coperta
  // Interogare SQL: Luam campingurile utilizatorului curent, descrierea si poza de coperta
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.description, c.address, c.region, c.type, c.approval_status, m.url as cover_url
        FROM campings c
        LEFT JOIN camping_media m ON c.id = m.camping_id AND m.sort_order = 1
        WHERE c.created_by = :user_id
        ORDER BY c.created_at DESC
    ");

    $stmt->execute([':user_id' => $user_id]);
    $campsites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $campsites]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Eroare BD: ' . $e->getMessage()]);
}
?>