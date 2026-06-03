<?php
class OrganizersRepository extends Repository
{
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ov.*, u.username, u.email FROM organizer_verifications ov
             JOIN users u ON u.id = ov.user_id WHERE ov.id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findApplicationByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM organizer_verifications WHERE user_id = :user_id ORDER BY submitted_at DESC LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function createApplication(int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO organizer_verifications
                (user_id, legal_name, cui, id_card_url, authorization_url, contract_url)
             VALUES (:user_id, :legal_name, :cui, :id_card_url, :authorization_url, :contract_url)
             RETURNING id"
        );
        $stmt->execute([
            'user_id'           => $userId,
            'legal_name'        => $data['legal_name'],
            'cui'               => $data['cui'] ?? null,
            'id_card_url'       => $data['id_card_url'] ?? null,
            'authorization_url' => $data['authorization_url'] ?? null,
            'contract_url'      => $data['contract_url'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }
}
