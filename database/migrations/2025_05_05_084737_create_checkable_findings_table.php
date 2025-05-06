<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public string $table = 'checkable_findings';

    public function up(): void
    {
        Schema::create($this->table, function (Blueprint $table) {
            $table->id();

            $table->morphs('checkable');

            $table->string('level')->index();
            $table->string('check_name')->index();
            $table->string('url', 2048);

            $table->text('message');
            $table->json('details')->nullable();
            $table->json('configuration')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table);
    }
};
