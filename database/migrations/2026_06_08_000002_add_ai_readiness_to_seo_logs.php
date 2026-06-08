<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_logs', function (Blueprint $table) {
            $table->boolean('llms_exists')->default(false);
            $table->boolean('gptbot_allowed')->nullable();
            $table->boolean('claudebot_allowed')->nullable();
            $table->boolean('google_extended_allowed')->nullable();
            $table->boolean('ai_policy_found')->default(false);
            $table->boolean('docs_found')->default(false);
            $table->integer('ai_score')->default(0);
            $table->json('ai_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seo_logs', function (Blueprint $table) {
            $table->dropColumn([
                'llms_exists',
                'gptbot_allowed',
                'claudebot_allowed',
                'google_extended_allowed',
                'ai_policy_found',
                'docs_found',
                'ai_score',
                'ai_details'
            ]);
        });
    }
};
