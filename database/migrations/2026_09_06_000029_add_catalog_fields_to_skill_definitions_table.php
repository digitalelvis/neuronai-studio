<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(StudioTables::name('skill_definitions'), function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('slug');
            $table->string('category')->nullable()->after('display_name');
            $table->string('cover_image')->nullable()->after('category');
            $table->string('source')->nullable()->after('cover_image');
            $table->string('source_url', 1000)->nullable()->after('source');
            $table->json('source_meta')->nullable()->after('source_url');

            $table->index('category', 'ns_skill_def_category_idx');
        });
    }

    public function down(): void
    {
        Schema::table(StudioTables::name('skill_definitions'), function (Blueprint $table) {
            $table->dropIndex('ns_skill_def_category_idx');
            $table->dropColumn([
                'display_name',
                'category',
                'cover_image',
                'source',
                'source_url',
                'source_meta',
            ]);
        });
    }
};
