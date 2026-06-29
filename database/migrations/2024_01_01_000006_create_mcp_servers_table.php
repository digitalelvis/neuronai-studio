<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('transport')->default('stdio');
            $table->string('command')->nullable();
            $table->json('args')->nullable();
            $table->string('url')->nullable();
            $table->string('token_env')->nullable();
            $table->json('headers')->nullable();
            $table->json('env')->nullable();
            $table->string('only_tools')->nullable();
            $table->json('exclude_tools')->nullable();
            $table->unsignedInteger('timeout')->default(30);
            $table->boolean('async')->default(false);
            $table->boolean('enabled')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_servers');
    }
};
