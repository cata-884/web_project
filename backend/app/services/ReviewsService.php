<?php
readonly class ReviewsService
{
    public function __construct(
        private ReviewsRepository  $reviews,
        private CampingsRepository $campings,
    ) {}

    public function listForCamping(int $campingId, int $limit, int $offset): array
    {
        return [
            'items' => $this->reviews->findByCampingId($campingId, $limit, $offset),
            'total' => $this->reviews->countByCampingId($campingId),
            'limit' => $limit,
            'offset'=> $offset,
        ];
    }

    public function create(int $userId, int $campingId, array $data): array
    {
        $rating = (int)($data['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) throw new ValidationException('Rating obligatoriu, intre 1 si 5');
        if (!$this->campings->findById($campingId)) throw new NotFoundException('Camping inexistent');
        if ($this->reviews->userAlreadyReviewed($userId, $campingId))
            throw new ConflictException('Ai recenzat deja acest camping');

        $data['rating'] = $rating;
        if (isset($data['content'])) {
            $data['content'] = htmlspecialchars(trim($data['content']), ENT_QUOTES, 'UTF-8');
        }

        $id = $this->reviews->create($userId, $campingId, $data);
        return $this->reviews->findById($id);
    }

    public function update(int $id, array $data, array $user): array
    {
        $this->assertCanModify($id, $user);
        if (!$data) throw new ValidationException('Body gol');
        if (isset($data['rating'])) {
            $data['rating'] = (int)$data['rating'];
            if ($data['rating'] < 1 || $data['rating'] > 5) throw new ValidationException('Rating intre 1 si 5');
        }
        if (isset($data['content'])) {
            $data['content'] = htmlspecialchars(trim($data['content']), ENT_QUOTES, 'UTF-8');
        }
        $this->reviews->update($id, $data);
        return $this->reviews->findById($id);
    }

    public function delete(int $id, array $user): void
    {
        $this->assertCanModify($id, $user);
        $this->reviews->delete($id);
    }

    private function assertCanModify(int $reviewId, array $user): void
    {
        $ownerId = $this->reviews->getOwnerId($reviewId);
        if ($ownerId === null) throw new NotFoundException('Recenzie inexistenta');
        if (($user['role'] ?? 'user') === 'admin') return;
        if ((int)$user['id'] === $ownerId) return;
        throw new ForbiddenException('Nu ai drepturi pe aceasta recenzie');
    }
}
