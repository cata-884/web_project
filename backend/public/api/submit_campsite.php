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

    // noua logica de autentificare cu token
    $headers = apache_request_headers();
    if (!isset($headers['Authorization']) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
    }

    // Verificam daca token-ul a fost trimis de JavaScript
    if (empty($headers['Authorization']) || !preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        // Am schimbat mesajul de eroare ca sa stim sigur ca ruleaza codul nou!
        echo json_encode(['success' => false, 'message' => 'Eroare: Nu ești logat! (Token lipsă din Header).']);
        exit;
    }

    $token = $matches[1];

// Cautam tokenul in baza de date
    $stmtUser = $pdo->prepare("SELECT user_id FROM sesiuni WHERE token = ? LIMIT 1");
    $stmtUser->execute([$token]); // <--- TREBUIE NEAPARAT SA EEXECUTI!
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        echo json_encode(['success' => false, 'message' => 'Eroare: Token invalid sau expirat în baza de date.']);
        exit;
    }

    // Am gasit userul real!
    $user_id = $userRow['user_id'];

    // Pornim tranzactia SQL
    $pdo->beginTransaction();

    // ... De aici in jos ramane codul tau cu $address, $slug, INSERT INTO etc.
    //Pregatim formatarea adreselor si a slug-ului
    $address = $_POST['street'] . ' nr. ' . $_POST['number'] . ', ' . $_POST['city'] . ', ' . $_POST['zip'];
    $region = $_POST['city']; // Momentan punem orasul ca regiune

    // Generam un slug simplu
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['name'])));
    $slug .= '-' . time();

 //Inseram in tabelul principal "campings"
    $stmt = $pdo->prepare("
        INSERT INTO campings
        (created_by, name, slug, description, type, address, region, latitude, longitude, capacity, price_per_night, approval_status, is_published, created_at)
        VALUES (?, ?, ?, ?, 'tent', ?, ?, ?, ?, ?, ?, 0, false, NOW())
        RETURNING id
    ");

    $stmt->execute([
        $user_id,
        $_POST['name'],
        $slug,
        $_POST['full_desc'],
        $address,
        $region,
        $_POST['lat'],
        $_POST['lng'],

        // Noile campuri: daca lipsesc din formular, trimitem null in DB
        !empty($_POST['capacity']) ? (int)$_POST['capacity'] : null,
        !empty($_POST['price_per_night']) ? (float)$_POST['price_per_night'] : null
    ]);

    // Luam ID-ul campingului abia creat
    $camping_id = $stmt->fetchColumn();

    //Inseram in "camping_environments"
    if (!empty($_POST['environments'])) {
        $stmtEnv = $pdo->prepare("INSERT INTO camping_environments (camping_id, environment_name) VALUES (?, ?)");
        foreach ($_POST['environments'] as $env) {
            $stmtEnv->execute([$camping_id, $env]);
        }
    }

    //Inseram in "camping_facilities"
    if (!empty($_POST['facilities'])) {
        $stmtFac = $pdo->prepare("INSERT INTO camping_facilities (camping_id, facility_name) VALUES (?, ?)");
        foreach ($_POST['facilities'] as $fac) {
            $stmtFac->execute([$camping_id, $fac]);
        }
    }

    //Salvarea pozelor si inserarea in "camping_media"
    $uploadDir = '../uploads/campings/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true); // Cream folderul daca nu exista
    }

    // A. Salvare Cover Photo
    if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['cover_photo']['name'], PATHINFO_EXTENSION);
        $coverName = uniqid('cover_') . '.' . $ext;

        if (move_uploaded_file($_FILES['cover_photo']['tmp_name'], $uploadDir . $coverName)) {
            $stmtMedia = $pdo->prepare("INSERT INTO camping_media (camping_id, type, url, sort_order) VALUES (?, 'image', ?, 1)");
            $stmtMedia->execute([$camping_id, 'uploads/campings/' . $coverName]);
        }
    }

    // B. Salvare Photo Gallery
    if (isset($_FILES['gallery_photos'])) {
        $stmtMedia = $pdo->prepare("INSERT INTO camping_media (camping_id, type, url, sort_order) VALUES (?, 'image', ?, ?)");
        $sort_order = 2; // Cover-ul are 1, galeria incepe de la 2

        foreach ($_FILES['gallery_photos']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['gallery_photos']['error'][$key] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['gallery_photos']['name'][$key], PATHINFO_EXTENSION);
                $galleryName = uniqid('gal_') . '.' . $ext;

                if (move_uploaded_file($tmp_name, $uploadDir . $galleryName)) {
                    $stmtMedia->execute([$camping_id, 'uploads/campings/' . $galleryName, $sort_order]);
                    $sort_order++;
                }
            }
        }
    }

    // Salvam totul definitiv in baza de date
    $pdo->commit();
    echo json_encode(['success' => true, 'camping_id' => $camping_id]);

} catch (Exception $e) {
    // Daca apare orice eroare pe parcurs, anulam absolut tot
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>