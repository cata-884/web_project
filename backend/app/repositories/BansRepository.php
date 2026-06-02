<?php
class BansRepository extends Repository
{
    public function create(int $userId, string $reason, ?int $days, int $bannedBy): int
    {
        $bannedUntil = $days ? (new DateTimeImmutable("+{$days} days"))->format('Y-m-d H:i:s') : null;
        $stmt = $this->pdo->prepare(
            "INSERT INTO user_bans (user_id, reason, banned_until, banned_by, is_active)
             VALUES (:user_id, :reason, :banned_until, :banned_by, TRUE) RETURNING id"
        );
        $stmt->execute([
            'user_id'      => $userId,
            'reason'       => $reason,
            'banned_until' => $bannedUntil,
            'banned_by'    => $bannedBy,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function findActiveByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, user_id, reason, banned_until, banned_by, is_active, created_at
             FROM user_bans
             WHERE user_id = :user_id AND is_active = TRUE AND (banned_until IS NULL OR banned_until > NOW())
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function liftBans(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE user_bans SET is_active = FALSE WHERE user_id = :user_id AND is_active = TRUE"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount();
    }

    public function findHistoryByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT b.id, b.user_id, b.reason, b.banned_until, b.banned_by,
                    b.is_active, b.created_at, u.username AS banned_by_username
             FROM user_bans b LEFT JOIN users u ON u.id = b.banned_by
             WHERE b.user_id = :user_id ORDER BY b.created_at DESC"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}
