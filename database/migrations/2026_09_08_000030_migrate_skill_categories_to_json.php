<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = StudioTables::name('skill_definitions');

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->json('categories')->nullable()->after('display_name');
        });

        $rows = DB::table($table)->select(['id', 'category'])->get();

        foreach ($rows as $row) {
            $categories = [];
            if (is_string($row->category) && trim($row->category) !== '') {
                $categories[] = trim($row->category);
            }

            DB::table($table)->where('id', $row->id)->update([
                'categories' => $categories === [] ? null : json_encode(array_values($categories)),
            ]);
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if (Schema::hasColumn($table, 'category')) {
                try {
                    $blueprint->dropIndex('ns_skill_def_category_idx');
                } catch (\Throwable) {
                    // Index name may differ per driver.
                }
                $blueprint->dropColumn('category');
            }
        });
    }

    public function down(): void
    {
        $table = StudioTables::name('skill_definitions');

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->string('category')->nullable()->after('display_name');
            $blueprint->index('category', 'ns_skill_def_category_idx');
        });

        $rows = DB::table($table)->select(['id', 'categories'])->get();

        foreach ($rows as $row) {
            $decoded = is_string($row->categories) ? json_decode($row->categories, true) : $row->categories;
            $first = is_array($decoded) && $decoded !== [] ? (string) reset($decoded) : null;

            DB::table($table)->where('id', $row->id)->update([
                'category' => $first,
            ]);
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropColumn('categories');
        });
    }
};
