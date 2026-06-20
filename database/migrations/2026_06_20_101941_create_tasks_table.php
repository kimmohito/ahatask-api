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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete()->after('title');
            $table->foreignId('project_id')->constrained()->cascadeOnDelete()->before('organization_id');

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('status')->default('todo');

            $table->string('priority')->default('medium');

            $table->foreignId('assignee_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
