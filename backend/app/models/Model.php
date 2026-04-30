<?php
abstract class Model
{
    protected PDO $pdo;
    protected string $table = '';

    public function __construct()
    {
        $this->pdo = DB::getConnection();
    }
}
