<?php
header('Content-Type: application/json');

$host = 'db';
$dbname = 'web_project_db';
$user = 'postgres';
$pass = 'password';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Eroare conexiune BD.']);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/documents/';

function uploadFile($fileInputName, $uploadDir) {
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES[$fileInputName]['tmp_name'];
        $fileName = uniqid() . '_' . basename($_FILES[$fileInputName]['name']);
        $destination = $uploadDir . $fileName;

     if (move_uploaded_file($tmpName, $destination)) {
            // Asta se va scrie în baza de date ca să afișezi poza ușor pe frontend
            return '/cat/public/uploads/documents/' . $fileName;
        }
    }
    return null;
}

$idDocumentPath = uploadFile('id_document', $uploadDir);
$regDocumentPath = uploadFile('registration_document', $uploadDir);

if (!$idDocumentPath || !$regDocumentPath) {
    echo json_encode(['success' => false, 'message' => 'Eroare la incarcarea fisierelor.']);
    exit;
}
$userId = $_POST['user_id'] ?? 1;
$lastName = $_POST['last_name'] ?? '';
$lastName = $_POST['last_name'] ?? '';
$firstName = $_POST['first_name'] ?? '';
$businessType = $_POST['business_type'] ?? '';
$companyName = $_POST['company_name'] ?? '';
$regNumber = $_POST['registration_number'] ?? '';
$street = $_POST['address_street'] ?? '';
$streetNumber = $_POST['address_number'] ?? '';
$city = $_POST['address_city'] ?? '';
$zip = $_POST['address_zip'] ?? '';
$phone = $_POST['contact_phone'] ?? '';
$email = $_POST['contact_email'] ?? '';

try {
   $sql = "INSERT INTO organizer_verifications (
                user_id, last_name, first_name, id_document_path,
                business_type, company_name, registration_number,
                address_street, address_number, address_city, address_zip,
                registration_document_path, contact_phone, contact_email
            ) VALUES (
                :user_id, :last_name, :first_name, :id_document_path,
                :business_type, :company_name, :registration_number,
                :address_street, :address_number, :address_city, :address_zip,
                :registration_document_path, :contact_phone, :contact_email
            )";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':user_id' => $userId,
        ':last_name' => $lastName,
        ':first_name' => $firstName,
        ':id_document_path' => $idDocumentPath,
        ':business_type' => $businessType,
        ':company_name' => $companyName,
        ':registration_number' => $regNumber,
        ':address_street' => $street,
        ':address_number' => $streetNumber,
        ':address_city' => $city,
        ':address_zip' => $zip,
        ':registration_document_path' => $regDocumentPath,
        ':contact_phone' => $phone,
        ':contact_email' => $email
    ]);

    echo json_encode(['success' => true, 'message' => 'Application submitted.']);

} catch (PDOException $e) {
    // Adăugăm $e->getMessage() ca să vedem exact mesajul trimis de pgAdmin
    echo json_encode(['success' => false, 'message' => 'Eroare SQL: ' . $e->getMessage()]);
}