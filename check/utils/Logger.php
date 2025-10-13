<?php

namespace Check\Utils;

use Check\Config\CheckConfig;

class Logger
{
    private string $logFile;
    private string $basePath;

    public function __construct(CheckConfig|string $logFile)
    {
        if ($logFile instanceof CheckConfig) {
            $this->logFile = $logFile->getLogFile();
        } else {
            $this->logFile = $logFile;
        }
        $this->basePath = $this->getProjectRoot();
        $this->initializeLog();
    }

    private function getProjectRoot(): string
    {
        // Handles DDEV and local execution where CWD is the project root.
        return getcwd();
    }

    private function initializeLog(): void
    {
        // Ensure the logs directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logMessage = "Log started at " . date('Y-m-d H:i:s') . "\n";
        $logMessage .= "Project root detected as: " . $this->basePath . "\n\n";
        file_put_contents($this->logFile, $logMessage);
        echo "Logging to: {$this->logFile}\n";
    }

    public function log(string $message): void
    {
        file_put_contents($this->logFile, $message . "\n", FILE_APPEND);
    }

    public function info(string $message): void
    {
        $this->log("INFO: " . $message);
    }

    public function warning(string $message): void
    {
        $this->log("WARNING: " . $message);
    }

    public function error(string $message): void
    {
        $this->log("ERROR: " . $message);
    }

    public function success(string $message): void
    {
        $this->log("SUCCESS: " . $message);
    }

    public function section(string $title): void
    {
        $this->log("\n=== " . strtoupper($title) . " ===");
    }

    private function formatPath(string $path): string
    {
        // Convert container path to local path
        if (strpos($path, '/var/www/html/') === 0) {
            return str_replace('/var/www/html', $this->basePath, $path);
        }
        return $path;
    }

    public function logIssue(array $details): void
    {
        $level = strtoupper($details['level'] ?? 'WARNING');
        $message = $details['message'];
        $filePath = $details['file'] ?? null;
        $lineNumber = $details['line'] ?? 1;
        $suggestion = $details['suggestion'] ?? null;

        $logEntry = "{$level}: {$message}";

        if ($filePath) {
            $localPath = $this->formatPath($filePath);
            $logEntry .= "\n  - File: {$localPath}";
            if ($lineNumber) {
                $logEntry .= ":{$lineNumber}";
            }
            // Create a clickable link for VS Code
            $logEntry .= "\n  - Open in IDE: vscode://file/{$localPath}:{$lineNumber}";
        }

        if ($suggestion) {
            $logEntry .= "\n  - Suggestion: {$suggestion}";
        }

        $this->log($logEntry);
    }
}