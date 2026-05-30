<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dns_logs', function (Blueprint $table) {
            $table->json('txt_records')->nullable()->after('mx_records');
            $table->json('cname_records')->nullable()->after('txt_records');
            $table->json('soa_records')->nullable()->after('cname_records');
            $table->json('caa_records')->nullable()->after('soa_records');
            $table->boolean('dnssec_enabled')->default(false)->after('caa_records');
            $table->unsignedInteger('ttl_min')->nullable()->after('dnssec_enabled');
            $table->unsignedInteger('ttl_max')->nullable()->after('ttl_min');
            $table->float('ttl_average')->nullable()->after('ttl_max');
            $table->float('response_time_ms')->nullable()->after('ttl_average');
            $table->string('hijack_risk')->default('none')->after('hijack_suspected');
            $table->unsignedTinyInteger('dns_score')->nullable()->after('ns_changed');
            $table->string('dns_grade')->nullable()->after('dns_score');
            $table->json('nameserver_health')->nullable()->after('dns_grade');
            $table->json('change_details')->nullable()->after('nameserver_health');
            $table->text('dns_error_details')->nullable()->after('error_message');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->unsignedTinyInteger('dns_score')->nullable()->after('dns_ns_changed');
            $table->string('dns_grade')->nullable()->after('dns_score');
            $table->float('dns_response_time_ms')->nullable()->after('dns_grade');
            $table->boolean('dnssec_enabled')->default(false)->after('dns_response_time_ms');
            $table->string('dns_hijack_risk')->default('none')->after('dnssec_enabled');
            $table->text('dns_error_details')->nullable()->after('dns_hijack_risk');
        });
    }

    public function down(): void
    {
        Schema::table('dns_logs', function (Blueprint $table) {
            $table->dropColumn([
                'txt_records',
                'cname_records',
                'soa_records',
                'caa_records',
                'dnssec_enabled',
                'ttl_min',
                'ttl_max',
                'ttl_average',
                'response_time_ms',
                'hijack_risk',
                'dns_score',
                'dns_grade',
                'nameserver_health',
                'change_details',
                'dns_error_details',
            ]);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'dns_score',
                'dns_grade',
                'dns_response_time_ms',
                'dnssec_enabled',
                'dns_hijack_risk',
                'dns_error_details',
            ]);
        });
    }
};
