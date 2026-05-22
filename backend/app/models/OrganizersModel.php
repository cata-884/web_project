<?php

/**
 * OrganizersModel — operatii pe tabela organizer_verifications.
 *
 * Gestioneaza cererile de promovare de la user simplu la organizer:
 * creare, aprobare (cu tranzactie atomica), respingere, listare.
 */
class OrganizersModel extends Model
{
    protected string $table = 'organizer_verifications';

    /**
     * Returneaza o cerere dupa ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ov.*, u.username, u.email
             FROM organizer_verifications ov
             JOIN users u ON u.id = ov.user_id
             WHERE ov.id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Creeaza o cerere noua de promovare.
     * @return int — ID-ul cererii create
     */
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

    /**
     * Returneaza cererea unui user (cea mai recenta).
     */
    public function findApplicationByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM organizer_verifications
             WHERE user_id = :user_id
             ORDER BY submitted_at DESC
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Returneaza lista cererilor pending (paginata), cu date user.
     */
    public function findPendingApplications(int $limit = 20, int $offset = 0): array
    {
        $limit  = min(100, max(1, $limit));
        $offset = max(0, $offset);

        $stmt = $this->pdo->prepare(
            "SELECT ov.*, u.username, u.email
             FROM organizer_verifications ov
             JOIN users u ON u.id = ov.user_id
             WHERE ov.status = 'pending'
             ORDER BY ov.submitted_at ASC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Numara cererile pending (pentru paginare).
     */
    public function countPending(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM organizer_verifications WHERE status = 'pending'"
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * Aproba o cerere: marcheaza ca 'approved' + promoveaza userul la 'organizer'.
     * Foloseste tranzactie atomica.
     *
     * @return bool — true daca s-a aprobat, false daca cererea nu e pending
     */
    public function approveApplication(int $appId, int $reviewerId): bool
    {
        $this->pdo->beginTransaction();
        try {
            // Marcheaza aplicatia ca approved
            $stmt = $this->pdo->prepare(
                "UPDATE organizer_verifications
                 SET status = 'approved', reviewed_by = :reviewer, reviewed_at = NOW()
                 WHERE id = :id AND status = 'pending'
                 RETURNING user_id"
            );
            $stmt->execute(['id' => $appId, 'reviewer' => $reviewerId]);
            $userId = $stmt->fetchColumn();

            if (!$userId) {
                $this->pdo->rollBack();
                return false;
            }

            // Promoveaza userul la organizer
            $stmt = $this->pdo->prepare(
                "UPDATE users SET role = 'organizer' WHERE id = :id"
            );
            $stmt->execute(['id' => $userId]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Respinge o cerere cu motiv (admin_notes).
     *
     * @return bool — true daca s-a respins, false daca cererea nu e pending
     */
    public function rejectApplication(int $appId, int $reviewerId, string $notes): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE organizer_verifications
             SET status = 'rejected',
                 reviewed_by = :reviewer,
                 reviewed_at = NOW(),
                 admin_notes = :notes
             WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute([
            'id'       => $appId,
            'reviewer' => $reviewerId,
            'notes'    => $notes,
        ]);
        return $stmt->rowCount() > 0;
    }
}
