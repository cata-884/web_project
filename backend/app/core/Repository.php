<?php
abstract class Repository
{
    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = DB::getConnection();
    }
}
