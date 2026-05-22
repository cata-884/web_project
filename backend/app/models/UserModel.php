<?php
class UserModel extends Model
{
    protected string $table = 'users';

    public function findByUsername(string $username): ?array //array or null
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, password_hash, full_name, avatar_url,
                    role, oauth_provider, oauth_id, created_at
             FROM users WHERE username = :username"
        );
        $stmt->execute(['username' => $username]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, password_hash, full_name, avatar_url,
                    role, oauth_provider, oauth_id, created_at
             FROM users WHERE email = :email"
        );
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, full_name, avatar_url, role,
                    oauth_provider, created_at
             FROM users WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function updateProfile(int $id, array $data): bool
    {
        $allowed = ['full_name', 'username'];
        $sets    = [];
        $params  = ['id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null) {
                $sets[]      = "$col = :$col";
                $params[$col] = $data[$col];
            }
        }
        if (!$sets) return false;

        $stmt = $this->pdo->prepare(
            "UPDATE users SET " . implode(', ', $sets) . " WHERE id = :id"
        );
        return $stmt->execute($params);
    }

    public function create(string $username, string $email, ?string $passwordHash, ?string $fullName): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (username, email, password_hash, full_name)
             VALUES (:username, :email, :pass, :name)
             RETURNING id"
        );
        $stmt->execute([
            'username' => $username,
            'email'    => $email,
            'pass'     => $passwordHash,
            'name'     => $fullName,
        ]);
        return (int) $stmt->fetchColumn();
    }
}