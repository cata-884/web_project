<?php
class UserModel extends Model
{
    protected string $table = 'users';

    public function findByUsername(string $username): ?array
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

    public function findByOauthId(string $provider, string $oauthId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM users WHERE oauth_provider = :provider AND oauth_id = :oauth_id LIMIT 1'
        );
        $stmt->execute(['provider' => $provider, 'oauth_id' => $oauthId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function linkOauth(int $userId, string $provider, string $oauthId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET oauth_provider = :provider, oauth_id = :oauth_id WHERE id = :id'
        );
        $stmt->execute(['provider' => $provider, 'oauth_id' => $oauthId, 'id' => $userId]);
    }

    public function createOauthUser(string $email, string $username, string $provider, string $oauthId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, username, password_hash, oauth_provider, oauth_id)
             VALUES (:email, :username, NULL, :provider, :oauth_id)
             RETURNING id'
        );
        $stmt->execute([
            'email'    => $email,
            'username' => $username,
            'provider' => $provider,
            'oauth_id' => $oauthId,
        ]);
        return (int) $stmt->fetchColumn();
    }

    private function generateUniqueUsername(string $email): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]/i', '', strstr($email, '@', true)));
        $username = $base ?: 'user';
        $i = 0;
        do {
            if ($i > 0) $username = $base . $i;
            $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE username = :u');
            $stmt->execute(['u' => $username]);
            $i++;
        } while ($stmt->fetch());
        return $username;
    }

    public function findOrCreateOauthUser(string $provider, string $oauthId, string $email): int
    {
        $row = $this->findByOauthId($provider, $oauthId);
        if ($row) return (int) $row['id'];

        $existing = $this->findByEmail($email);
        if ($existing) {
            $this->linkOauth((int) $existing['id'], $provider, $oauthId);
            return (int) $existing['id'];
        }

        $username = $this->generateUniqueUsername($email);
        return $this->createOauthUser($email, $username, $provider, $oauthId);
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