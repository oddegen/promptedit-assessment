<?php

use App\Enums\CreditTransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNotExists(fn ($query) => $query
                ->from('credit_transactions')
                ->whereColumn('credit_transactions.user_id', 'users.id')
                ->where('credit_transactions.type', CreditTransactionType::Bonus->value))
            ->orderBy('id')
            ->each(fn (stdClass $user) => DB::table('credit_transactions')->insert([
                'user_id' => $user->id,
                'type' => CreditTransactionType::Bonus->value,
                'amount' => Config::integer('credits.welcome_bonus'),
                'created_at' => now(),
            ]));
    }
};
