<?php

namespace App\Services;

use Exception;
use PDO;
use Carbon\Carbon;

class EsslService
{
    protected $pdo;

    public function __construct()
    {
        $host = env('ESSL_DB_HOST');
        $port = env('ESSL_DB_PORT', '1433');
        $database = env('ESSL_DB_DATABASE');

        $dsn = "odbc:Driver={ODBC Driver 18 for SQL Server};Server={$host},{$port};Database={$database};";

        if (env('ESSL_DB_ENCRYPT') === 'yes' || env('ESSL_DB_ENCRYPT') === true) {
            $dsn .= "Encrypt=yes;";
        } else {
            $dsn .= "Encrypt=no;";
        }

        if (env('ESSL_DB_TRUST_SERVER_CERTIFICATE') === 'true' || env('ESSL_DB_TRUST_SERVER_CERTIFICATE') === true) {
            $dsn .= "TrustServerCertificate=yes;";
        }

        $user = env('ESSL_DB_USERNAME');
        $pass = env('ESSL_DB_PASSWORD');

        $dsn .= "Uid={$user};Pwd={$pass};";
        try {
            // Try connecting with Driver 18
            $this->pdo = new PDO(
                "odbc:essl",
                env('ESSL_DB_USERNAME'),
                env('ESSL_DB_PASSWORD'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]
            );
        } catch (Exception $e) {
             throw new Exception("Could not connect to ESSL database using ODBC Driver 18 or 17. Error: " . $e->getMessage());
            // If Driver 18 fails (e.g. not installed), try Driver 17
            $dsn17 = str_replace('ODBC Driver 18', 'ODBC Driver 17', $dsn);
            try {
                $this->pdo = new PDO($dsn17, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
            } catch (Exception $e2) {
                // If both fail, throw the original error
                throw new Exception("Could not connect to ESSL database using ODBC Driver 18 or 17. Error: " . $e2->getMessage());
            }
        }
    }

    /**
     * Check table exists
     */
    public function tableExists($tableName)
    {
        $stmt = $this->pdo->query("
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_NAME = '{$tableName}'
        ");

        return $stmt->fetch();
    }

    /**
     * Get employees
     */
    public function getEmployees()
    {
        $stmt = $this->pdo->query("
            SELECT *
            FROM Employees
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resolve monthly DeviceLogs table name for a date (DeviceLogs_{n}_{Y} or AttLog fallback).
     */
    public function resolveDeviceLogsTable(Carbon $date): ?string
    {
        $tableName = 'DeviceLogs_' . $date->format('n') . '_' . $date->format('Y');

        if ($this->tableExists($tableName)) {
            return $tableName;
        }

        if ($this->tableExists('AttLog')) {
            return 'AttLog';
        }

        return null;
    }

    /**
     * Get logs by date range
     */
    public function getLogs($fromDate, $toDate)
    {
        $dt = Carbon::parse($fromDate);
        $tableName = $this->resolveDeviceLogsTable($dt);

        if (!$tableName) {
            return [];
        }

        return $this->getLogsFromTable($tableName, $fromDate, $toDate);
    }

    /**
     * Raw DeviceLogId from ESSL (reference only; may repeat across months).
     */
    public function rawDeviceLogId(object $log): ?int
    {
        $id = $log->DeviceLogId ?? $log->Id ?? null;

        return $id !== null ? (int) $id : null;
    }

    /**
     * Get logs from a specific table
     */
    public function getLogsFromTable($tableName, $fromDate, $toDate)
    {
        $sql = "
            SELECT *
            FROM {$tableName}
            WHERE LogDate >= '{$fromDate}'
            AND LogDate <= '{$toDate}'
            ORDER BY LogDate ASC
        ";

        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Custom query
     */
    public function query($sql)
    {
        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}