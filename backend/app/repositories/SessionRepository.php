<?php
class SessionRepository extends Repository
{
    private const TTL_HOURS = 24;

    public function create(int $userId): string
    {
        $token     = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable('+' . self::TTL_HOURS . ' hours'))->format('Y-m-d H:i:s');
        $this->pdo->prepare(
            "INSERT INTO sesiuni (token, user_id, expires_at) VALUES (:token, :user_id, :expires_at)"
        )->execute(['token' => $token, 'user_id' => $userId, 'expires_at' => $expiresAt]);
        return $token;
    }

    public function validateToken(string $token): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT user_id FROM sesiuni WHERE token = :token AND expires_at > NOW()"
        );
        $stmt->execute(['token' => $token]);
        $userId = $stmt->fetchColumn();
        return $userId ? (int) $userId : null;
    }

    public function delete(string $token): void
    {
        $this->pdo->prepare("DELETE FROM sesiuni WHERE token = :token")->execute(['token' => $token]);
    }

    public function deleteByUserId(int $userId): void
    {
        $this->pdo->prepare("DELETE FROM sesiuni WHERE user_id = :user_id")->execute(['user_id' => $userId]);
    }
}
