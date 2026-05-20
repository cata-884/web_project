<?php
use PHPUnit\Framework\TestCase;

class UserModelTest extends TestCase
{
    private $pdo;
    private $userModel;

    protected function setUp(): void
    {
        // Aici ar trebui să folosești o bază de date de test (poate un SQLite în memorie sau un container separat)
        $this->pdo = DB::getConnection();
        $this->userModel = new UserModel($this->pdo);
    }

    public function testCanFindByUsername(): void
    {
        // 1. Pregătim datele (Aranjăm)
        $username = 'test_user_' . uniqid();

        // 2. Executăm acțiunea (Acționăm)
        // (Presupunem că ai o metodă de register sau inserezi direct pentru test)
        $user = $this->userModel->findByUsername($username);

        // 3. Verificăm rezultatul (Asertăm) - exact ca în Java
        $this->assertNull($user, "Ar trebui să fie null pentru un user inexistent");
    }
}