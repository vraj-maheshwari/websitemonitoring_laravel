<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ssl_logs', function (Blueprint $table) {
            $table->text('ssl_subject')->nullable()->after('issuer');
            $table->json('ssl_subject_alt_names')->nullable()->after('ssl_subject');
            $table->string('ssl_serial_number')->nullable()->after('ssl_subject_alt_names');
            $table->string('ssl_signature_algorithm')->nullable()->after('ssl_serial_number');
            $table->string('ssl_certificate_version')->nullable()->after('ssl_signature_algorithm');
            $table->string('ssl_tls_version')->nullable()->after('ssl_certificate_version');
            $table->date('ssl_valid_from')->nullable()->after('ssl_tls_version');
            $table->date('ssl_valid_until')->nullable()->after('ssl_valid_from');
            $table->boolean('ssl_is_trusted')->default(false)->after('ssl_valid_until');
            $table->unsignedTinyInteger('ssl_security_score')->nullable()->after('ssl_is_trusted');
            $table->string('ssl_grade')->nullable()->after('ssl_security_score');
            $table->boolean('ssl_hostname_valid')->default(false)->after('ssl_grade');
            $table->text('ssl_error_details')->nullable()->after('ssl_hostname_valid');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->text('ssl_subject')->nullable()->after('ssl_issuer');
            $table->json('ssl_subject_alt_names')->nullable()->after('ssl_subject');
            $table->string('ssl_serial_number')->nullable()->after('ssl_subject_alt_names');
            $table->string('ssl_signature_algorithm')->nullable()->after('ssl_serial_number');
            $table->string('ssl_certificate_version')->nullable()->after('ssl_signature_algorithm');
            $table->string('ssl_tls_version')->nullable()->after('ssl_certificate_version');
            $table->date('ssl_valid_from')->nullable()->after('ssl_tls_version');
            $table->date('ssl_valid_until')->nullable()->after('ssl_valid_from');
            $table->boolean('ssl_is_trusted')->default(false)->after('ssl_valid_until');
            $table->unsignedTinyInteger('ssl_security_score')->nullable()->after('ssl_is_trusted');
            $table->string('ssl_grade')->nullable()->after('ssl_security_score');
            $table->boolean('ssl_hostname_valid')->default(false)->after('ssl_grade');
            $table->text('ssl_error_details')->nullable()->after('ssl_hostname_valid');
        });
    }

    public function down(): void
    {
        Schema::table('ssl_logs', function (Blueprint $table) {
            $table->dropColumn([
                'ssl_subject',
                'ssl_subject_alt_names',
                'ssl_serial_number',
                'ssl_signature_algorithm',
                'ssl_certificate_version',
                'ssl_tls_version',
                'ssl_valid_from',
                'ssl_valid_until',
                'ssl_is_trusted',
                'ssl_security_score',
                'ssl_grade',
                'ssl_hostname_valid',
                'ssl_error_details',
            ]);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'ssl_subject',
                'ssl_subject_alt_names',
                'ssl_serial_number',
                'ssl_signature_algorithm',
                'ssl_certificate_version',
                'ssl_tls_version',
                'ssl_valid_from',
                'ssl_valid_until',
                'ssl_is_trusted',
                'ssl_security_score',
                'ssl_grade',
                'ssl_hostname_valid',
                'ssl_error_details',
            ]);
        });
    }
};
