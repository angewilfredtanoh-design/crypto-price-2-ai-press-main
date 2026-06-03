<?php
/**
 * src/Core/Database.php
 * Classe de gestion de la base de données SQLite
 */

namespace Crypto\Core;

use PDO;
use PDOException;

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;
    
    private function __construct() {
        if (!defined('DB_FILE')) {
            require_once dirname(__DIR__, 2) . '/config.php';
        }
        
        try {
            $this->pdo = new PDO("sqlite:" . DB_FILE);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new \Exception("Connexion DB échouée: " . $e->getMessage());
        }
    }
    
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection(): PDO {
        return $this->pdo;
    }
    
    public function query(string $sql, array $params = []): \PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetchOne(string $sql, array $params = []): ?array {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }
    
    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function insert(string $table, array $data): int {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        
        return (int) $this->pdo->lastInsertId();
    }
    
    public function update(string $table, array $data, string $where, array $whereParams = []): int {
        $setParts = [];
        foreach ($data as $column => $value) {
            $setParts[] = "{$column} = ?";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge(array_values($data), $whereParams));
        
        return $stmt->rowCount();
    }
    
    public function delete(string $table, string $where, array $params = []): int {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
