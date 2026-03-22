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
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('project_column_id')
                ->nullable()
                ->after('project_id')
                ->constrained('project_columns')
                ->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0)->after('priority');

            $table->index(['project_column_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['project_column_id', 'sort_order']);
            $table->dropConstrainedForeignId('project_column_id');
            $table->dropColumn('sort_order');
        });
    }
};
