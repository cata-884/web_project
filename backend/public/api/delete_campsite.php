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

    // autentificare cu token
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

    // preluare date
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['campsite_id'])) {
        echo json_encode(['success' => false, 'message' => 'Eroare: ID-ul locației lipsește.']);
        exit;
    }

    $camping_id = (int)$data['campsite_id'];

    // verificare proprietate
    $stmtCheck = $pdo->prepare("SELECT id FROM campings WHERE id = ? AND created_by = ?");
    $stmtCheck->execute([$camping_id, $user_id]);

    if (!$stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Eroare: Nu ai permisiunea să ștergi această locație.']);
        exit;
    }

    // stergerea efectiva (folosind o tranzactie)
    $pdo->beginTransaction();

    //(Optional dar recomandat) Stergem fisierele pozelor de pe server
    $stmtMedia = $pdo->prepare("SELECT url FROM camping_media WHERE camping_id = ?");
    $stmtMedia->execute([$camping_id]);
    $mediaFiles = $stmtMedia->fetchAll(PDO::FETCH_ASSOC);

    foreach ($mediaFiles as $media) {
        $filePath = '../' . $media['url']; // Calea fizica pe server
        if (file_exists($filePath) && is_file($filePath)) {
            unlink($filePath); // Sterge fisierul fizic
        }
    }

    //Stergem randurile dependente ("copiii") din baza de date
    $pdo->prepare("DELETE FROM camping_environments WHERE camping_id = ?")->execute([$camping_id]);
    $pdo->prepare("DELETE FROM camping_facilities WHERE camping_id = ?")->execute([$camping_id]);
    $pdo->prepare("DELETE FROM camping_media WHERE camping_id = ?")->execute([$camping_id]);

    //Stergem locatia principala ("parintele")
    $pdo->prepare("DELETE FROM campings WHERE id = ?")->execute([$camping_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Locația a fost ștearsă cu succes!']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>