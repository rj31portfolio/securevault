<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('roles', function (Blueprint $t) { $t->id(); $t->string('name')->unique(); $t->timestamps(); });
        Schema::create('permissions', function (Blueprint $t) { $t->id(); $t->string('name')->unique(); $t->timestamps(); });
        Schema::create('permission_role', function (Blueprint $t) { $t->foreignId('role_id')->constrained()->cascadeOnDelete(); $t->foreignId('permission_id')->constrained()->cascadeOnDelete(); $t->primary(['role_id','permission_id']); });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->restrictOnDelete(); $t->string('status')->default('pending')->index();
            $t->unsignedInteger('session_version')->default(1); $t->timestamp('last_login_at')->nullable();
            $t->timestamp('terms_accepted_at')->nullable(); $t->softDeletes();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id(); $t->string('name')->unique(); $t->boolean('enabled')->default(true); $t->boolean('is_screenshot')->default(false);
            $t->json('allowed_extensions'); $t->unsignedInteger('max_upload_mb')->default(100); $t->timestamps();
        });
        Schema::create('files', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignId('user_id')->constrained()->restrictOnDelete(); $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->string('title'); $t->text('description')->nullable(); $t->string('disk_path'); $t->string('mime',100); $t->string('extension',12);
            $t->unsignedBigInteger('size'); $t->string('sha256',64); $t->string('scan_status')->default('not_scanned'); $t->timestamps(); $t->softDeletes();
            $t->index(['user_id','created_at']); $t->index(['category_id','created_at']);
        });
        Schema::create('storage_usage', function (Blueprint $t) { $t->id(); $t->foreignId('user_id')->unique()->constrained()->restrictOnDelete(); $t->unsignedBigInteger('bytes_used')->default(0); $t->unsignedBigInteger('file_count')->default(0); $t->timestamps(); });
        foreach (['user_activities','security_logs','login_histories','email_verification_logs'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id(); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $t->string('email')->nullable(); $t->string('type')->index();
                $t->text('description'); $t->string('ip_address',45)->nullable(); $t->string('browser',500)->nullable(); $t->string('device',40)->nullable();
                $t->timestamps(); $t->index(['user_id','created_at']); $t->index('created_at');
            });
        }
        Schema::create('file_views', function (Blueprint $t) { $t->id(); $t->uuid('file_id'); $t->foreign('file_id')->references('id')->on('files')->restrictOnDelete(); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $t->string('ip_address',45)->nullable(); $t->timestamps(); $t->index(['file_id','created_at']); });
        Schema::create('user_sessions', function (Blueprint $t) { $t->id(); $t->foreignId('user_id')->constrained()->restrictOnDelete(); $t->string('session_hash',64)->unique(); $t->string('ip_address',45)->nullable(); $t->string('browser',500)->nullable(); $t->timestamp('last_seen_at'); $t->timestamp('revoked_at')->nullable(); $t->timestamps(); });
        Schema::create('settings', function (Blueprint $t) { $t->string('key')->primary(); $t->longText('value')->nullable(); $t->boolean('encrypted')->default(false); $t->timestamps(); });
    }
    public function down(): void {
        foreach (['settings','user_sessions','file_views','email_verification_logs','login_histories','security_logs','user_activities','storage_usage','files','categories','permission_role','permissions'] as $table) Schema::dropIfExists($table);
        Schema::table('users', function(Blueprint $t) { $t->dropConstrainedForeignId('role_id'); $t->dropColumn(['status','session_version','last_login_at','terms_accepted_at','deleted_at']); });
        Schema::dropIfExists('roles');
    }
};
