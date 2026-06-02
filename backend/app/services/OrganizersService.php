<?php
class OrganizersService
{
    public function __construct(private OrganizersRepository $organizers) {}

    public function apply(int $userId, string $userRole, array $data): array
    {
        if ($userRole !== 'user') throw new ForbiddenException('Doar utilizatorii normali pot aplica');

        $legalName = trim($data['legal_name'] ?? '');
        if (strlen($legalName) < 3 || strlen($legalName) > 200)
            throw new ValidationException('legal_name: 3-200 caractere');

        $cui = isset($data['cui']) ? trim($data['cui']) : null;
        if ($cui !== null && $cui !== '' && !preg_match('/^(RO)?\d{2,10}$/i', $cui))
            throw new ValidationException('CUI invalid (ex: RO12345678 sau 12345678)');

        $existing = $this->organizers->findApplicationByUserId($userId);
        if ($existing && in_array($existing['status'], ['pending', 'approved'], true))
            throw new ConflictException('Exista deja o cerere ' . $existing['status']);

        $id = $this->organizers->createApplication($userId, [
            'legal_name'        => $legalName,
            'cui'               => $cui,
            'id_card_url'       => $data['id_card_url'] ?? null,
            'authorization_url' => $data['authorization_url'] ?? null,
            'contract_url'      => $data['contract_url'] ?? null,
        ]);
        return $this->organizers->findById($id);
    }

    public function myApplication(int $userId): array
    {
        $app = $this->organizers->findApplicationByUserId($userId);
        if (!$app) throw new NotFoundException('Nu ai nicio cerere de promovare');
        return $app;
    }

    public function pending(int $limit, int $offset): array
    {
        return [
            'applications' => $this->organizers->findPendingApplications($limit, $offset),
            'total'        => $this->organizers->countPending(),
            'limit'        => $limit,
            'offset'       => $offset,
        ];
    }

    public function approve(int $id, int $adminId): array
    {
        $app = $this->organizers->findById($id);
        if (!$app) throw new NotFoundException('Cerere inexistenta');
        if ($app['status'] !== 'pending')
            throw new ValidationException('Cererea nu este in status pending (actual: ' . $app['status'] . ')');
        if (!$this->organizers->approveApplication($id, $adminId))
            throw new ApiException('Nu s-a putut aproba cererea', 500);
        return $this->organizers->findById($id);
    }

    public function reject(int $id, int $adminId, string $notes): array
    {
        $app = $this->organizers->findById($id);
        if (!$app) throw new NotFoundException('Cerere inexistenta');
        if ($app['status'] !== 'pending')
            throw new ValidationException('Cererea nu este in status pending (actual: ' . $app['status'] . ')');
        if (strlen($notes) < 3)
            throw new ValidationException('Motivul respingerii (notes) obligatoriu, minim 3 caractere');
        if (!$this->organizers->rejectApplication($id, $adminId, $notes))
            throw new ApiException('Nu s-a putut respinge cererea', 500);
        return $this->organizers->findById($id);
    }
}
