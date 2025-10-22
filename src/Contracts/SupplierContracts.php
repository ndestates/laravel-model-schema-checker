<?php

namespace NDEstates\LaravelModelSchemaChecker\Contracts;

/**
 * API Client Interface - Defines contract for external API suppliers
 */
interface ApiClientInterface
{
    public function getData(string $endpoint): array;
    public function postData(string $endpoint, array $data): array;
}

/**
 * Database Supplier Interface - Defines contract for database suppliers
 */
interface DatabaseSupplierInterface
{
    public function connect(): bool;
    public function getTableColumns(string $tableName): array;
    public function tableExists(string $tableName): bool;
    public function query(string $sql): array;
}

/**
 * File System Supplier Interface - Defines contract for file system suppliers
 */
interface FileSystemSupplierInterface
{
    public function readFile(string $path): string;
    public function writeFile(string $path, string $content): bool;
    public function scanDirectory(string $path): array;
    public function fileExists(string $path): bool;
    public function createDirectory(string $path): bool;
}