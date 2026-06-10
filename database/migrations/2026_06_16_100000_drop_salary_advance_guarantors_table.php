<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('salary_advance_guarantors');
    }

    public function down(): void
    {
        // Guarantors feature removed — no restore.
    }
};
