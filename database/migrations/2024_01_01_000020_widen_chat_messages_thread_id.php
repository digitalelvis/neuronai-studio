<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = StudioTables::name('chat_messages');
        $driver = Schema::getConnection()->getDriverName();

        // Scoped keys like agent:{id}:{uuid} exceed uuid/char(36).
        match ($driver) {
            'mysql', 'mariadb' => DB::statement("ALTER TABLE `{$table}` MODIFY `thread_id` VARCHAR(64) NOT NULL"),
            'pgsql' => DB::statement("ALTER TABLE {$table} ALTER COLUMN thread_id TYPE VARCHAR(64)"),
            default => null, // sqlite ignores length; create migration already uses string(64)
        };
    }

    public function down(): void
    {
        $table = StudioTables::name('chat_messages');
        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'mysql', 'mariadb' => DB::statement("ALTER TABLE `{$table}` MODIFY `thread_id` CHAR(36) NOT NULL"),
            'pgsql' => DB::statement("ALTER TABLE {$table} ALTER COLUMN thread_id TYPE CHAR(36)"),
            default => null,
        };
    }
};
