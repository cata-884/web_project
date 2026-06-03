<?php
readonly class SectionsService
{
    public function __construct(
        private SectionsRepository $sections,
        private CampingsRepository $campings,
    ) {}

    public function listForUser(int $userId): array
    {
        return $this->sections->findByUserId($userId);
    }

    public function create(int $userId, array $data): array
    {
        $name = trim($data['name'] ?? '');
        if (strlen($name) < 1 || strlen($name) > 100)
            throw new ValidationException('Numele sectiunii trebuie sa aiba 1-100 caractere');
        $id = $this->sections->create($userId, $name);
        return $this->sections->findById($id);
    }

    public function getCampings(int $id, array $user): array
    {
        $this->assertOwnership($id, $user);
        return $this->sections->getCampings($id);
    }

    public function addCamping(int $id, array $data, array $user): array
    {
        $this->assertOwnership($id, $user);
        $campingId = (int)($data['camping_id'] ?? 0);
        if ($campingId <= 0) throw new ValidationException('camping_id obligatoriu');
        if (!$this->campings->findById($campingId)) throw new NotFoundException('Camping inexistent');
        $this->sections->addCamping($id, $campingId);
        return $this->sections->getCampings($id);
    }

    public function removeCamping(int $id, int $campingId, array $user): void
    {
        $this->assertOwnership($id, $user);
        $this->sections->removeCamping($id, $campingId);
    }

    private function assertOwnership(int $sectionId, array $user): void
    {
        $section = $this->sections->findById($sectionId);
        if (!$section) throw new NotFoundException('Sectiune inexistenta');
        if (!$this->sections->isOwner($sectionId, (int)$user['id']))
            throw new ForbiddenException('Nu ai acces la aceasta sectiune');
    }
}
