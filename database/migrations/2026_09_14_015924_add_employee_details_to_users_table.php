<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('ntt_id')->nullable()->unique()->after('email');
            $table->string('project_name')->nullable()->after('ntt_id');
            $table->string('division')->nullable()->after('project_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['ntt_id']);
            $table->dropColumn(['ntt_id', 'project_name', 'division']);
        });
    }
};
