<?php
class ContactService
{
    public function __construct(private ContactRepository $contact) {}

    public function store(array $data): void
    {
        $name    = trim($data['name']    ?? '');
        $email   = trim($data['email']   ?? '');
        $message = trim($data['message'] ?? '');

        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$message)
            throw new ValidationException('Date invalide');

        $this->contact->create($name, $email, $data['phone'] ?? null, $message);
    }

    public function list(): array
    {
        return $this->contact->findAll();
    }
}
