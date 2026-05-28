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

    //autentificare cu token bearer
    $headers = apache_request_headers();
    if (!isset($headers['Authorization']) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (empty($headers['Authorization']) || !preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        echo json_encode(['success' => false, 'message' => 'Eroare: Acces interzis. Token lipsă.']);
        exit;
    }

    $token = $matches[1];
    $stmtUser = $pdo->prepare("SELECT user_id FROM sesiuni WHERE token = ? LIMIT 1");
    $stmtUser->execute([$token]);
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        echo json_encode(['success' => false, 'message' => 'Eroare: Token invalid sau expirat.']);
        exit;
    }
    $user_id = $userRow['user_id'];

    //validare id camping
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'Eroare: ID camping lipsă sau invalid.']);
        exit;
    }
    $camping_id = (int)$_GET['id'];

    //extragere date principale
    // Verificam si daca locatia apartine utilizatorului logat
    $stmtCamping = $pdo->prepare("
        SELECT id, name, slug, description, type, address, region, latitude, longitude,
               price_per_night, capacity, rating_avg, rating_count, is_published,
               approval_status, admin_feedback, created_at
        FROM campings
        WHERE id = :id AND created_by = :user_id
    ");
    $stmtCamping->execute([':id' => $camping_id, ':user_id' => $user_id]);
    $camping = $stmtCamping->fetch(PDO::FETCH_ASSOC);

    if (!$camping) {
        echo json_encode(['success' => false, 'message' => 'Eroare: Locația nu a fost găsită sau nu ai drepturi de acces.']);
        exit;
    }

    //extragere galerie foto
    $stmtMedia = $pdo->prepare("SELECT url, type FROM camping_media WHERE camping_id = ? ORDER BY sort_order ASC");
    $stmtMedia->execute([$camping_id]);
    $camping['media'] = $stmtMedia->fetchAll(PDO::FETCH_ASSOC);

    //extragere facilitati
    $stmtFac = $pdo->prepare("SELECT facility_name FROM camping_facilities WHERE camping_id = ?");
    $stmtFac->execute([$camping_id]);
    $camping['facilities'] = $stmtFac->fetchAll(PDO::FETCH_COLUMN); // Luam doar lista de string-uri

    //extragere mediu inconjurator
    $stmtEnv = $pdo->prepare("SELECT environment_name FROM camping_environments WHERE camping_id = ?");
    $stmtEnv->execute([$camping_id]);
    $camping['environments'] = $stmtEnv->fetchAll(PDO::FETCH_COLUMN);

    //extragere recenzii (ultimele 10)
    $stmtRev = $pdo->prepare("
        SELECT r.rating, r.title, r.content, r.created_at, u.username as author_name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.camping_id = ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmtRev->execute([$camping_id]);
    $camping['reviews'] = $stmtRev->fetchAll(PDO::FETCH_ASSOC);

    // Trimitem JSON-ul complet catre Frontend
    echo json_encode(['success' => true, 'data' => $camping]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Eroare BD: ' . $e->getMessage()]);
}
?>