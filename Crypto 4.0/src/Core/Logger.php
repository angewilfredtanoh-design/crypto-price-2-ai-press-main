<?php
/**
 * src/Core/Logger.php
 * Système de logging centralisé pour Crypto 4.0
 */

namespace Crypto4\Core;

class Logger {
    private const LOG_LEVELS = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];
    
    private static ?Logger $instance = null;
    private int $minLevel;
    private string $logDir;
    
    private function __construct() {
        if (!defined('LOG_DIR')) {
            $configPath = dirname(__DIR__, 2) . '/config.php';
            if (file_exists($configPath)) {
                require_once $configPath;
            }
        }
        
        $this->logDir = defined('LOG_DIR') ? LOG_DIR : dirname(__DIR__, 2) . '/logs';
        $this->minLevel = self::LOG_LEVELS['DEBUG'] ?? 0;
        
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
    
    public static function getInstance(): Logger {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function debug(string $message, string $context = ''): void {
        self::getInstance()->writeLog('DEBUG', $message, $context);
    }
    
    public static function info(string $message, string $context = ''): void {
        self::getInstance()->writeLog('INFO', $message, $context);
    }
    
    public static function warning(string $message, string $context = ''): void {
        self::getInstance()->writeLog('WARNING', $message, $context);
    }
    
    public static function error(string $message, string $context = ''): void {
        self::getInstance()->writeLog('ERROR', $message, $context);
    }
    
    public static function critical(string $message, string $context = ''): void {
        self::getInstance()->writeLog('CRITICAL', $message, $context);
    }
    
    private function writeLog(string $level, string $message, string $context = ''): void {
        $levelValue = self::LOG_LEVELS[$level] ?? 1;
        
        if ($levelValue < $this->minLevel) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? " [{$context}]" : '';
        $logLine = "[{$timestamp}] [{$level}]{$contextStr} {$message}" . PHP_EOL;
        
        $logFile = $this->logDir . '/' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    public function setMinLevel(string $level): void {
        if (isset(self::LOG_LEVELS[$level])) {
            $this->minLevel = self::LOG_LEVELS[$level];
        }
    }
}
