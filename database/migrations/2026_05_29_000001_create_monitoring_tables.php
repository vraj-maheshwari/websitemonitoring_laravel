<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('normalized_url');
            $table->string('name')->nullable();
            $table->unsignedInteger('uptime_interval')->default(300);
            $table->unsignedInteger('ssl_interval')->default(86400);
            $table->unsignedInteger('seo_interval')->default(86400);
            $table->unsignedInteger('security_interval')->default(86400);
            $table->unsignedInteger('dns_interval')->default(86400);
            $table->json('tracked_keywords')->nullable();
            $table->string('app_status')->default('unknown');
            $table->boolean('is_processing')->default(false);
            foreach (['uptime', 'ssl', 'seo', 'security', 'dns'] as $type) {
                $table->string("{$type}_status")->default('unknown');
                $table->timestamp("next_{$type}_check_at")->nullable();
                $table->timestamp("last_{$type}_check_at")->nullable();
                $table->timestamp("{$type}_started_at")->nullable();
            }
            $table->timestamp('next_check_at')->nullable();
            $table->string('current_status')->default('unknown');
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->float('last_response_time')->nullable();
            $table->float('last_ttfb')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamp('last_downtime_started_at')->nullable();
            $table->timestamp('last_downtime_ended_at')->nullable();
            $table->string('ssl_state')->default('unknown');
            $table->string('ssl_issuer')->nullable();
            $table->date('ssl_expiry_date')->nullable();
            $table->integer('ssl_days_remaining')->nullable();
            $table->float('seo_score')->nullable();
            $table->string('seo_state')->default('unknown');
            $table->float('security_score')->nullable();
            $table->string('security_grade')->nullable();
            $table->json('security_headers')->nullable();
            $table->boolean('dns_resolved')->default(false);
            $table->json('dns_last_ips')->nullable();
            $table->json('dns_last_ns')->nullable();
            $table->boolean('dns_hijack_suspected')->default(false);
            $table->boolean('dns_ns_changed')->default(false);
            $table->float('performance_score')->nullable();
            $table->float('lcp_ms')->nullable();
            $table->float('tbt_ms')->nullable();
            $table->float('fcp_ms')->nullable();
            $table->float('cls')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'normalized_url']);
            $table->index('next_check_at');
        });

        Schema::create('uptime_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at')->index();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->float('response_time_ms')->nullable();
            $table->float('ttfb_ms')->nullable();
            $table->boolean('is_up')->default(false);
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('ssl_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at')->index();
            $table->boolean('is_valid')->default(false);
            $table->string('issuer')->nullable();
            $table->date('expiry_date')->nullable();
            $table->integer('days_remaining')->nullable();
            $table->string('ssl_state');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('seo_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at')->index();
            $table->float('score')->nullable();
            $table->string('state')->default('unknown');
            $table->boolean('fetch_is_valid')->default(false);
            $table->string('fetch_status')->nullable();
            $table->text('fetch_html_preview')->nullable();
            $table->json('seo_signals')->nullable();
            $table->json('issues')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('cwv_estimate')->nullable();
            $table->json('lighthouse')->nullable();
            $table->json('tech_stack')->nullable();
            $table->json('broken_links')->nullable();
            $table->json('security_categories')->nullable();
            $table->float('security_score')->nullable();
            $table->string('security_grade')->nullable();
            $table->json('security_headers')->nullable();
            $table->timestamps();
        });

        Schema::create('dns_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at')->index();
            $table->boolean('resolved')->default(false);
            $table->json('ips')->nullable();
            $table->json('nameservers')->nullable();
            $table->json('mx_records')->nullable();
            $table->boolean('hijack_suspected')->default(false);
            $table->boolean('ns_changed')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('OPEN');
            $table->string('root_cause')->default('unknown');
            $table->timestamp('opened_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->json('timeline')->nullable();
            $table->timestamps();
        });

        Schema::create('alert_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('check_type');
            $table->string('alert_level');
            $table->string('subject');
            $table->text('body');
            $table->timestamp('sent_at')->index();
            $table->foreignId('incident_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('full_link_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('queued');
            $table->unsignedInteger('max_depth')->default(1);
            $table->unsignedInteger('max_pages')->default(25);
            $table->unsignedInteger('pages_crawled')->default(0);
            $table->unsignedInteger('links_checked')->default(0);
            $table->json('results')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        foreach (['uptime', 'ssl', 'seo'] as $type) {
            Schema::create("daily_{$type}_summaries", function (Blueprint $table) use ($type) {
                $table->id();
                $table->foreignId('site_id')->constrained()->cascadeOnDelete();
                $table->date('date')->index();
                if ($type === 'uptime') {
                    $table->unsignedInteger('total_checks')->default(0);
                    $table->unsignedInteger('up_count')->default(0);
                    $table->unsignedInteger('down_count')->default(0);
                    $table->unsignedInteger('degraded_count')->default(0);
                    $table->float('avg_response_time_ms')->nullable();
                    $table->float('uptime_percent')->nullable();
                } elseif ($type === 'ssl') {
                    $table->string('ssl_state')->default('unknown');
                    $table->integer('days_remaining')->nullable();
                } else {
                    $table->float('score')->nullable();
                    $table->string('state')->default('unknown');
                }
                $table->timestamps();
                $table->unique(['site_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        foreach (['daily_seo_summaries', 'daily_ssl_summaries', 'daily_uptime_summaries', 'full_link_audit_logs', 'alert_histories', 'incidents', 'dns_logs', 'seo_logs', 'ssl_logs', 'uptime_logs', 'sites'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
