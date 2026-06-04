<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('essl_logs')) {
            return;
        }

        // Remove global unique on device_log_id (IDs repeat across monthly ESSL tables).
        $this->dropIndexIfExists('essl_logs', 'essl_logs_device_log_id_unique');
        $this->dropIndexIfExists('essl_logs', 'device_log_id');

        Schema::table('essl_logs', function (Blueprint $table) {
            if (! $this->indexExists('essl_logs', 'essl_logs_device_log_id_index')) {
                $table->index('device_log_id', 'essl_logs_device_log_id_index');
            }
        });

        $this->dropIndexIfExists('essl_logs', 'essl_logs_user_log_date_unique');

        Schema::table('essl_logs', function (Blueprint $table) {
            if (! $this->indexExists('essl_logs', 'essl_logs_user_log_date_direction_unique')) {
                $table->unique(
                    ['user_id', 'log_date', 'direction'],
                    'essl_logs_user_log_date_direction_unique'
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('essl_logs')) {
            return;
        }

        $this->dropIndexIfExists('essl_logs', 'essl_logs_user_log_date_direction_unique');
        $this->dropIndexIfExists('essl_logs', 'essl_logs_device_log_id_index');

        Schema::table('essl_logs', function (Blueprint $table) {
            $table->unique('device_log_id', 'essl_logs_device_log_id_unique');
            $table->unique(['user_id', 'log_date'], 'essl_logs_user_log_date_unique');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return count($indexes) > 0;
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->dropIndex($indexName);
            });
        }
    }
};
