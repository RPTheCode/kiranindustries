<?php

namespace App\Database\Migrations\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait SafeMigration
{
    protected function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    protected function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        if (! $this->tableExists($table)) {
            return false;
        }

        $indexes = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->unique();

        return $indexes->contains($indexName);
    }

    protected function addIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        if (! $this->tableExists($table) || $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function ($blueprint) use ($columns, $indexName) {
            $blueprint->index($columns, $indexName);
        });
    }

    protected function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->tableExists($table) || ! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function ($blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }

    protected function addColumnIfMissing(string $table, string $column, callable $callback): void
    {
        if (! $this->tableExists($table) || $this->columnExists($table, $column)) {
            return;
        }

        Schema::table($table, function ($blueprint) use ($callback) {
            $callback($blueprint);
        });
    }

    protected function dropColumnIfExists(string $table, string $column): void
    {
        if (! $this->tableExists($table) || ! $this->columnExists($table, $column)) {
            return;
        }

        Schema::table($table, function ($blueprint) use ($column) {
            $blueprint->dropColumn($column);
        });
    }
}
