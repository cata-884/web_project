<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$host = 'db';
$dbname = 'web_project_db';
$user = 'postgres';
$pass = 'password';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Citim datele trimise din JS
    $data = json_decode(file_get_contents("php://input"), true);

    $lastName = trim($data['last_name'] ?? '');
    $firstName = trim($data['first_name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = trim($data['email'] ?? '');
    $message = trim($data['message'] ?? '');

    // Validare
    if (empty($lastName) || empty($firstName) || empty($email) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Te rugăm să completezi toate câmpurile obligatorii.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Adresa de email nu este validă.']);
        exit;
    }

    // AICI ESTE REPARAȚIA: Combinăm Numele și Prenumele într-o singură variabilă
    $fullName = $lastName . ' ' . $firstName;

    // Inserare în baza de date folosind $fullName
    $stmt = $pdo->prepare("INSERT INTO contact_requests (name, email, phone, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$fullName, $email, $phone, $message]);

    echo json_encode(['success' => true, 'message' => 'Mesajul tău a fost trimis cu succes!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Eroare la server: ' . $e->getMessage()]);
}
?>