<?php

class DatabaseManager
{
    private $pdo;

    private $tableName;

    private static $instance;

    private function __construct()
    {
        $config = require_once '.env';

        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 3306;
        $user = $config['user'];
        $password = $config['password'];
        $dbName = $config['dbName'];
        $this->tableName = $config['tableName'];
        
        $dsn = "mysql:dbname=$dbName;host=$host;port=$port";

        $this->pdo = new PDO($dsn, $user, $password, [PDO::MYSQL_ATTR_LOCAL_INFILE => 1]);
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new static;
        }

        return self::$instance;
    }

    public function importData(string $fileName): bool
    {
        $resultCreateTable = $this->tryCreateTable($this->tableName);
        $resultCleanTable = $this->cleanTableData($this->tableName);
        $resultLoadData = $this->loadDataFromFile($fileName, $this->tableName);

        return $resultCreateTable && $resultCleanTable && $resultLoadData;
    }

    private function tryCreateTable(string $tableName): bool
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS $tableName
            (
                number BIGINT NOT NULL,
                name VARCHAR(255) NOT NULL
            )
            ENGINE INNODB
            ";

        $this->pdo->exec($sql);

        if ($this->pdo->errorCode() !== '00000') {
            print_r($this->pdo->errorInfo());

            return false;
        }

        return true;
    }

    private function cleanTableData(string $tableName): bool
    {
        $this->pdo->exec("TRUNCATE TABLE $tableName");

        if ($this->pdo->errorCode() !== '00000') {
            print_r($this->pdo->errorInfo());

            return false;
        }

        return true;
    }

    private function loadDataFromFile(string $fileName, string $tableName): bool
    {
        $sql = "
            LOAD DATA LOCAL INFILE '$fileName'
            INTO TABLE $tableName
            FIELDS TERMINATED BY ','
            OPTIONALLY ENCLOSED BY ''
            (number, name)
            ";

        $this->pdo->query($sql);
        
        if ($this->pdo->errorCode() !== '00000') {
            print_r($this->pdo->errorInfo());

            return false;
        }

        return true;
    }
}
