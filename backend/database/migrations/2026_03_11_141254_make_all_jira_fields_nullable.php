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
        Schema::table('users', function (Blueprint $table) {
            $table->text('jira_access_token')->nullable()->change();
            $table->string('jira_refresh_token')->nullable()->change();
            $table->string('jira_cloud_id')->nullable()->change();
            $table->string('jira_site_url')->nullable()->change();
            $table->string('avatar_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('jira_access_token')->nullable(false)->change();
            $table->string('jira_refresh_token')->nullable(false)->change();
            $table->string('jira_cloud_id')->nullable(false)->change();
            $table->string('jira_site_url')->nullable(false)->change();
            $table->string('avatar_url')->nullable(false)->change();
        });
    }
};
