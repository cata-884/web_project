<?php
class ContactRepository extends Repository
{
    public function create(string $name, string $email, ?string $phone, string $message): void
    {
        $this->pdo->prepare(
            "INSERT INTO contact_messages (name, email, phone, message) VALUES (:name, :email, :phone, :message)"
        )->execute(['name' => $name, 'email' => $email, 'phone' => $phone, 'message' => $message]);
    }

    public function findAll(): array
    {
        return $this->pdo->query(
            "SELECT * FROM contact_messages ORDER BY created_at DESC"
        )->fetchAll();
    }
}
