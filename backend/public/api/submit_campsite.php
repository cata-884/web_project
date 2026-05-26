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

    // --- NOUA LOGICĂ DE AUTENTIFICARE CU TOKEN ---
    $headers = apache_request_headers();
    if (!isset($headers['Authorization']) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
    }

    // Verificăm dacă token-ul a fost trimis de JavaScript
    if (empty($headers['Authorization']) || !preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        // Am schimbat mesajul de eroare ca să știm sigur că rulează codul nou!
        echo json_encode(['success' => false, 'message' => 'Eroare: Nu ești logat! (Token lipsă din Header).']);
        exit;
    }

    $token = $matches[1];

// Căutăm tokenul în baza de date
    $stmtUser = $pdo->prepare("SELECT user_id FROM sesiuni WHERE token = ? LIMIT 1");
    $stmtUser->execute([$token]); // <--- TREBUIE NEAPĂRAT SĂ EEXECUȚI!
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        echo json_encode(['success' => false, 'message' => 'Eroare: Token invalid sau expirat în baza de date.']);
        exit;
    }

    // Am găsit userul real!
    $user_id = $userRow['user_id'];
    // ----------------------------------------------

    // Pornim tranzacția SQL
    $pdo->beginTransaction();

    // ... De aici în jos rămâne codul tău cu $address, $slug, INSERT INTO etc.
    // 2. Pregătim formatarea adreselor și a slug-ului
    $address = $_POST['street'] . ' nr. ' . $_POST['number'] . ', ' . $_POST['city'] . ', ' . $_POST['zip'];
    $region = $_POST['city']; // Momentan punem orașul ca regiune

    // Generăm un slug simplu
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['name'])));
    $slug .= '-' . time();

    // 3. Inserăm în tabelul principal "campings"
   // 3. Inserăm în tabelul principal "campings"
    $stmt = $pdo->prepare("
        INSERT INTO campings
        (created_by, name, slug, description, type, address, region, latitude, longitude, approval_status, is_published, created_at)
        VALUES (?, ?, ?, ?, 'tent', ?, ?, ?, ?, 0, false, NOW())
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
        $_POST['lng']
    ]);

    // Luăm ID-ul campingului abia creat
    $camping_id = $stmt->fetchColumn();

    // 4. Inserăm în "camping_environments"
    if (!empty($_POST['environments'])) {
        $stmtEnv = $pdo->prepare("INSERT INTO camping_environments (camping_id, environment_name) VALUES (?, ?)");
        foreach ($_POST['environments'] as $env) {
            $stmtEnv->execute([$camping_id, $env]);
        }
    }

    // 5. Inserăm în "camping_facilities"
    if (!empty($_POST['facilities'])) {
        $stmtFac = $pdo->prepare("INSERT INTO camping_facilities (camping_id, facility_name) VALUES (?, ?)");
        foreach ($_POST['facilities'] as $fac) {
            $stmtFac->execute([$camping_id, $fac]);
        }
    }

    // 6. Salvarea pozelor și inserarea în "camping_media"
    $uploadDir = '../uploads/campings/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true); // Creăm folderul dacă nu există
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
        $sort_order = 2; // Cover-ul are 1, galeria începe de la 2

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

    // Salvăm totul definitiv în baza de date
    $pdo->commit();
    echo json_encode(['success' => true, 'camping_id' => $camping_id]);

} catch (Exception $e) {
    // Dacă apare orice eroare pe parcurs, anulăm absolut tot
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>