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

    // --- AUTENTIFICARE CU TOKEN ---
    $headers = apache_request_headers();
    if (!isset($headers['Authorization']) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (empty($headers['Authorization']) || !preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        echo json_encode(['success' => false, 'message' => 'Eroare: Nu ești logat!']);
        exit;
    }

    $token = $matches[1];
    $stmtUser = $pdo->prepare("SELECT user_id FROM sesiuni WHERE token = ? LIMIT 1");
    $stmtUser->execute([$token]);
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        echo json_encode(['success' => false, 'message' => 'Eroare: Token invalid.']);
        exit;
    }
    $user_id = $userRow['user_id'];

    // --- PRELUARE DATE (Așteptăm JSON din JS) ---
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['campsite_id'])) {
        echo json_encode(['success' => false, 'message' => 'Eroare: ID-ul locației lipsește.']);
        exit;
    }

    $camping_id = (int)$data['campsite_id'];
    $is_published = !empty($data['is_published']) ? 'true' : 'false'; // Format sigur pt Postgres boolean

    // --- VERIFICARE PROPRIETATE ---
    $stmtCheck = $pdo->prepare("SELECT id FROM campings WHERE id = ? AND created_by = ?");
    $stmtCheck->execute([$camping_id, $user_id]);

    if (!$stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Eroare: Nu ai permisiunea să modifici această locație.']);
        exit;
    }

    // --- UPDATE STATUS ---
    $stmtUpdate = $pdo->prepare("UPDATE campings SET is_published = ? WHERE id = ?");
    $stmtUpdate->execute([$is_published, $camping_id]);

    // Returnăm starea finală ca să confirmăm pe frontend
    echo json_encode(['success' => true, 'is_published' => !empty($data['is_published'])]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>