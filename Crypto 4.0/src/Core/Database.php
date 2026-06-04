<?php

namespace Crypto4\Core;

use PDO;
use PDOException;

/**
 * Gestionnaire de connexion à la base de données SQLite
 */
class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $dbFile = defined('DB_FILE') ? DB_FILE : __DIR__ . '/../../crypto_cache.db';
                self::$instance = new PDO('sqlite:' . $dbFile);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                throw new \Exception("Erreur de connexion à la base de données: " . $e->getMessage());
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
