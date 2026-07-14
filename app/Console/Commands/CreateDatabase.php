<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use PDOException;

class CreateDatabase extends Command
{
    protected $signature = 'db:create {name?} {--charset=utf8mb4} {--collation=utf8mb4_unicode_ci}';
    protected $description = 'Create the configured MySQL database if it does not exist';

    public function handle(): int
    {
        $dbName = $this->argument('name') ?: env('DB_DATABASE');
        $host = env('DB_HOST', '127.0.0.1');
        $port = (int) env('DB_PORT', 3306);
        $user = env('DB_USERNAME', 'root');
        $pass = env('DB_PASSWORD', '');
        $charset = (string) $this->option('charset');
        $collation = (string) $this->option('collation');

        if (empty($dbName)) {
            $this->error('Database name is empty. Set DB_DATABASE in your .env or pass a name argument.');
            return self::FAILURE;
        }

        $dsn = "mysql:host={$host};port={$port}";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            $this->error('Could not connect to MySQL server: ' . $e->getMessage());
            return self::FAILURE;
        }

        try {
            $sql = sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s', str_replace('`','``',$dbName), $charset, $collation);
            $pdo->exec($sql);
            $this->info("Database '{$dbName}' is ready.");
        } catch (PDOException $e) {
            $this->error('Failed to create database: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
