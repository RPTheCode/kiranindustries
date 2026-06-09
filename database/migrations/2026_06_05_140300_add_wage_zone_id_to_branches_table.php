<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'wage_zone_id')) {
                $table->foreignId('wage_zone_id')->nullable()->after('state')->constrained('wage_zones')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'wage_zone_id')) {
                $table->dropConstrainedForeignId('wage_zone_id');
            }
        });
    }
};
