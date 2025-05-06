<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Vvb13a\LaravelResponseChecker\Enums\FindingLevel;

return new class extends Migration {
    public string $table = 'checkable_summaries';

    public function up(): void
    {
        Schema::create($this->table, function (Blueprint $table) {
            $table->id();

            $table->morphs('checkable');
            $table->unique(['checkable_id', 'checkable_type'], 'checkable_model_unique');

            $table->string('status')->default(FindingLevel::SUCCESS->value)->index();
            $table->json('finding_counts')->nullable();
            $table->json('check_counts')->nullable();
            $table->unsignedInteger('check_totals')->default(0);
            $table->unsignedInteger('finding_totals')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table);
    }
};
