<?php

namespace Check\Utils;

use Illuminate\Support\Facades\File;

class FileHelper
{
    public static function createDatedFolder(string $basePath = null): string
    {
        $basePath = $basePath ?? getcwd();
        $timestamp = date('Y-m-d_H-i-s');
        $folderName = "output_{$timestamp}";
        $folderPath = $basePath . '/' . $folderName;
        
        // Security check: ensure the path is within allowed directories
        $realBasePath = realpath($basePath);
        $realFolderPath = realpath(dirname($folderPath)) . '/' . basename($folderPath);
        
        if (strpos($realFolderPath, $realBasePath) !== 0) {
            throw new \InvalidArgumentException("Invalid path: potential directory traversal attack");
        }
        
        if (!is_dir($folderPath)) {
            mkdir($folderPath, 0755, true);
        }
        
        return $folderPath;
    }
    
    public static function safeWriteFile(string $filePath, string $content): bool
    {
        // Ensure directory exists
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Write file with proper permissions
        $result = file_put_contents($filePath, $content);
        
        if ($result !== false) {
            chmod($filePath, 0644);
            return true;
        }
        
        return false;
    }
    
    public static function backupFile(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }
        
        $backupPath = $filePath . '.backup.' . date('Y-m-d-H-i-s');
        
        if (copy($filePath, $backupPath)) {
            return $backupPath;
        }
        
        return null;
    }
}