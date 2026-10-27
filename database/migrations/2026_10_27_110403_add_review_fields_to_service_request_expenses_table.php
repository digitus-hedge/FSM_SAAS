<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_request_expenses', function (Blueprint $t) {
            if (! Schema::hasColumn('service_request_expenses', 'approved_by_user_id'))
                $t->unsignedBigInteger('approved_by_user_id')->nullable()->after('approval_status');
            if (! Schema::hasColumn('service_request_expenses', 'approved_at'))
                $t->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            if (! Schema::hasColumn('service_request_expenses', 'rejected_by_user_id'))
                $t->unsignedBigInteger('rejected_by_user_id')->nullable()->after('approved_at');
            if (! Schema::hasColumn('service_request_expenses', 'rejected_at'))
                $t->timestamp('rejected_at')->nullable()->after('rejected_by_user_id');
            if (! Schema::hasColumn('service_request_expenses', 'rejected_reason'))
                $t->string('rejected_reason', 500)->nullable()->after('rejected_at');
        });
    }
    public function down(): void
    {
        Schema::table('service_request_expenses', function (Blueprint $t) {
            foreach (['approved_by_user_id','approved_at','rejected_by_user_id','rejected_at','rejected_reason'] as $c) {
                if (Schema::hasColumn('service_request_expenses', $c)) $t->dropColumn($c);
            }
        });
    }
};