<?php
readonly class OrganizersService
{
    public function __construct(
        private OrganizersRepository $organizers,
        private UserRepository       $users,
    ) {}

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
        $this->users->updateRole($userId, 'organizer');
        return $this->organizers->findById($id);
    }

    public function myApplication(int $userId): array
    {
        $app = $this->organizers->findApplicationByUserId($userId);
        if (!$app) throw new NotFoundException('Nu ai nicio cerere de promovare');
        return $app;
    }
}
