<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(StudioTables::name('plugin_packages'), function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('version');
            $table->string('content_hash', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('manifest');
            $table->json('mcp_templates')->nullable();
            $table->string('source')->default('catalog');
            $table->string('source_url')->nullable();
            $table->string('source_path')->nullable();
            $table->timestamps();

            $table->unique(['slug', 'version'], 'ns_plugin_pkg_slug_ver_uq');
            $table->index('content_hash', 'ns_plugin_pkg_hash_idx');
        });

        Schema::create(StudioTables::name('plugin_package_skills'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('plugin_package_id')
                ->constrained(StudioTables::name('plugin_packages'))
                ->cascadeOnDelete();
            $table->string('slug');
            $table->text('description');
            $table->longText('body')->nullable();
            $table->json('resources')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['plugin_package_id', 'slug'], 'ns_plugin_pkg_skill_uq');
        });

        Schema::table(StudioTables::name('plugin_installs'), function (Blueprint $table) {
            $table->foreignId('package_id')
                ->nullable()
                ->after('id')
                ->constrained(StudioTables::name('plugin_packages'))
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table(StudioTables::name('plugin_installs'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_id');
        });

        Schema::dropIfExists(StudioTables::name('plugin_package_skills'));
        Schema::dropIfExists(StudioTables::name('plugin_packages'));
    }
};
