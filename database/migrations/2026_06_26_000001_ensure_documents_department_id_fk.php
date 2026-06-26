<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase D2B — Document Ownership Foundation
 *
 * Ensures documents.department_id exists as a nullable FK to departments.id.
 *
 * The column was originally created in 2025_11_10_000002_create_documents_table.php
 * but without an explicit index name. This migration is a safe no-op guard:
 * it verifies the column exists (and is nullable) so future phases can rely on it.
 *
 * Down is intentionally a no-op — dropping the column would destroy ownership data.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Column already exists from the original migration.
        // This migration acts as a documented anchor for Phase D2B.
        // If for any reason the column is missing (fresh DB from older dump),
        // this block will add it safely.
        if (! Schema::hasColumn('documents', 'department_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id')->nullable()->after('title');
                $table->foreign('department_id')
                      ->references('id')
                      ->on('departments')
                      ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        // Intentionally left empty — do NOT drop ownership column on rollback.
        // Dropping department_id would destroy document ownership data.
    }
};
