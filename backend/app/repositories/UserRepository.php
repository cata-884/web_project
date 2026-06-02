<?php
class UserRepository extends Repository
{
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, password_hash, full_name, avatar_url, role, is_oauth, oauth_id, created_at
             FROM users WHERE username = :username"
        );
        $stmt->execute(['username' => $username]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, password_hash, full_name, avatar_url, role, is_oauth, oauth_id, created_at
             FROM users WHERE email = :email"
        );
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, full_name, avatar_url, role, is_oauth, created_at FROM users WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByOauthId(string $oauthId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE oauth_id = :oauth_id LIMIT 1');
        $stmt->execute(['oauth_id' => $oauthId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findForExport(): array
    {
        return $this->pdo->query(
            "SELECT id, username, email, full_name, role, is_oauth, created_at FROM users ORDER BY id"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findWithBanStatus(array $filters, int $limit, int $offset): array
    {
        [$whereClause, $params] = $this->buildBanFilters($filters);
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.username, u.email, u.full_name, u.avatar_url, u.role, u.created_at
             FROM users u $whereClause ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countWithBanStatus(array $filters): int
    {
        [$whereClause, $params] = $this->buildBanFilters($filters);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users u $whereClause");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function buildBanFilters(array $filters): array
    {
        $where  = [];
        $params = [];
        if (!empty($filters['role'])) {
            $where[] = "u.role = :role";
            $params['role'] = $filters['role'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(u.username ILIKE :search OR u.email ILIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (isset($filters['banned'])) {
            $sub = "SELECT 1 FROM user_bans b WHERE b.user_id = u.id AND b.is_active = TRUE AND (b.banned_until IS NULL OR b.banned_until > NOW())";
            $where[] = $filters['banned'] ? "EXISTS ($sub)" : "NOT EXISTS ($sub)";
        }
        $clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return [$clause, $params];
    }

    public function create(string $username, string $email, ?string $passwordHash, ?string $fullName): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (username, email, password_hash, full_name) VALUES (:username, :email, :pass, :name) RETURNING id"
        );
        $stmt->execute(['username' => $username, 'email' => $email, 'pass' => $passwordHash, 'name' => $fullName]);
        return (int) $stmt->fetchColumn();
    }

    public function createOauth(string $email, string $username, string $oauthId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, username, password_hash, is_oauth, oauth_id) VALUES (:email, :username, NULL, TRUE, :oauth_id) RETURNING id'
        );
        $stmt->execute(['email' => $email, 'username' => $username, 'oauth_id' => $oauthId]);
        return (int) $stmt->fetchColumn();
    }

    public function linkOauth(int $userId, string $oauthId): void
    {
        $this->pdo->prepare('UPDATE users SET is_oauth = TRUE, oauth_id = :oauth_id WHERE id = :id')
            ->execute(['oauth_id' => $oauthId, 'id' => $userId]);
    }

    public function updateProfile(int $id, array $data): bool
    {
        $allowed = ['full_name', 'username'];
        $sets    = [];
        $params  = ['id' => $id];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null) {
                $sets[] = "$col = :$col";
                $params[$col] = $data[$col];
            }
        }
        if (!$sets) return false;
        $stmt = $this->pdo->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = :id");
        return $stmt->execute($params);
    }

    public function updateAvatarUrl(int $userId, string $url): void
    {
        $this->pdo->prepare("UPDATE users SET avatar_url = :url WHERE id = :id")
                  ->execute(['url' => $url, 'id' => $userId]);
    }

    public function generateUniqueUsername(string $email): string
    {
        $base     = strtolower(preg_replace('/[^a-z0-9]/i', '', strstr($email, '@', true)));
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
}
