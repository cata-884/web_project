<?php

class ContactController extends Controller
{
    public function store(): void
    {
        $body = $this->getJsonBody();
        $name    = trim($body['name'] ?? '');
        $email   = trim($body['email'] ?? '');
        $message = trim($body['message'] ?? '');

        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$message) {
            $this->json(['error' => 'Date invalide'], 400);
        }

        $stmt = DB::getConnection()->prepare(
            "INSERT INTO contact_messages (name, email, phone, message)
             VALUES (:name, :email, :phone, :message)"
        );
        $stmt->execute([
            'name'    => $name,
            'email'   => $email,
            'phone'   => $body['phone'] ?? null,
            'message' => $message,
        ]);

        $this->json(['ok' => true, 'message' => 'Mesaj trimis cu succes']);
    }

    public function index(): void
    {
        $this->requireAdmin();
        $rows = DB::getConnection()
            ->query("SELECT * FROM contact_messages ORDER BY created_at DESC")
            ->fetchAll();
        $this->json(['messages' => $rows]);
    }
}
