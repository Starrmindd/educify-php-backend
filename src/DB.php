<?php
namespace Educify;
use PDO;

class DB {
    private $pdo;
    public function __construct($basePath){
        $dbPath = $_ENV['DB_PATH'] ?? ($basePath.'/educify.db');
        $dsn = 'sqlite:'.$dbPath;
        $this->pdo = new PDO($dsn);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // initialize tables if not exist done in seed.php typically
    }
    public function getPDO(){ return $this->pdo; }
}
