<?php

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('credit_transactions')->insertOrIgnoreUsing(
            ['user_id', 'generation_id', 'type', 'amount', 'created_at'],
            DB::table('generations')
                ->whereIn('status', [GenerationStatus::Pending->value, GenerationStatus::Queued->value, GenerationStatus::Processing->value])
                ->selectRaw('user_id, id, ?, -max_price, created_at', [CreditTransactionType::Hold->value]),
        );
    }
};
