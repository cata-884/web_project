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

    // procesare upload
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Te rog selectează o imagine validă.']);
        exit;
    }

    $uploadDir = '../uploads/avatars/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $file = $_FILES['avatar'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

    // Verificam daca e chiar o imagine (optional, dar recomandat)
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array(strtolower($ext), $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Format nepermis. Doar imagini!']);
        exit;
    }

    // Generam un nume unic pentru poza ca sa nu se suprascrie
    $avatarName = 'user_' . $user_id . '_' . time() . '.' . $ext;
    $destination = $uploadDir . $avatarName;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $avatarUrl = 'uploads/avatars/' . $avatarName;

        // Actualizam baza de date
        $stmtUpdate = $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
        $stmtUpdate->execute([$avatarUrl, $user_id]);

        // Raspundem cu noul URL ca sa il afisam pe frontend
        echo json_encode([
            'success' => true,
            'message' => 'Avatar actualizat!',
            'avatar_url' => '/cat/public/' . $avatarUrl
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Eroare la salvarea fișierului pe server.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>