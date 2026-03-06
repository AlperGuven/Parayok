<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->string('jira_issue_id');
            $table->string('jira_issue_key', 50);
            $table->text('summary');
            $table->text('description')->nullable();
            $table->string('jira_url', 500);
            $table->enum('status', ['pending', 'voting', 'revealed', 'scored'])->default('pending');
            $table->decimal('final_score', 5, 1)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('added_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['room_id', 'jira_issue_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
