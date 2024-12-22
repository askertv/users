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

    public function notifyUsers(int $messageId)
    {
        $resultSet = $this->pdo->query("SELECT title, description FROM messages WHERE id = $messageId AND status = 'ready_to_sent'");
        $messageData = $resultSet->fetch();
        if (empty($messageData)) {
            return false;
        }

        $title = $messageData['title'];
        $description = $messageData['description'];

        // Выборка по всем пользователям, кроме тех, по которым уже успешно отослали рассылку
        $sql = "
            SELECT
                t.number
            FROM
                {$this->tableName} t
            LEFT JOIN
                messages_pool p ON p.number = t.number AND p.message_id = $messageId AND status = 'sent'
            WHERE
                p.number IS NULL";

        $resultSet = $this->pdo->query($sql);

        $notificationResult = true;

        // Фиктивная функция отправки рассылки
        $notifyFunction = function(string $title, string $message, int $number) {
            return true;
        };

        while ($row = $resultSet->fetch()) {
            $number = (int)$row['number'];

            if ($notifyFunction($title, $description, $number)) {
                $sqlInsert = "
                    INSERT INTO
                        messages_pool
                    SET
                        message_id = $messageId,
                        number = $number,
                        status = 'sent'";

                $this->pdo->exec($sqlInsert);

                if ($this->pdo->errorCode() !== '00000') {
                    print_r($this->pdo->errorInfo());

                    $notificationResult = false;
                }
            } else {
                $notificationResult = false;
            }
        }

        if ($notificationResult) {
            $sql = "
                UPDATE
                    messages
                SET
                    status = 'completed'
                WHERE
                    id = $messageId";

            $this->pdo->exec($sql);

            if ($this->pdo->errorCode() !== '00000') {
                print_r($this->pdo->errorInfo());
            }
        }
    }

    public function tryCreateTableMessages(): bool
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS messages
            (
                id INTEGER NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                status ENUM('draft', 'ready_to_sent', 'active', 'inactive', 'completed'),
                PRIMARY KEY (id)
            )
            ENGINE INNODB
            ";

        $this->pdo->exec($sql);

        if ($this->pdo->errorCode() !== '00000') {
            print_r($this->pdo->errorInfo());

            return false;
        }

        $sql = "
            CREATE TABLE IF NOT EXISTS messages_pool
            (
                message_id INTEGER NOT NULL,
                number BIGINT NOT NULL,
                status ENUM('sent', 'error'),
                errors TEXT,
                created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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

    public function createMessage(string $title, string $message, string $status = 'draft'): int
    {
        $sql = "
            INSERT INTO
                messages
            SET
                title = '$title',
                description = '$message',
                status = '$status'";

        $this->pdo->exec($sql);

        if ($this->pdo->errorCode() !== '00000') {
            print_r($this->pdo->errorInfo());

            return 0;
        }

        return $this->pdo->lastInsertId();
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
