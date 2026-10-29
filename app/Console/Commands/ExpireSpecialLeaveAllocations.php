<?php

namespace App\Console\Commands;

use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireSpecialLeaveAllocations extends Command
{
    protected $signature = 'leave:expire-special-allocations';

    protected $description =
    'Expire unused MT/PT allocation days after their validity end date';

    public function handle(): int
    {
        /*
         * The end date is inclusive. An allocation valid until today
         * must remain usable today, so only dates before today expire.
         */
        $balanceIds = LeaveBalance::query()
            ->where('allocation_type', 'special')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', today())
            ->where('remaining_days', '>', 0)
            ->whereHas('leaveType', function ($query) {
                $query->whereIn('code', ['MT', 'PT']);
            })
            ->orderBy('id')
            ->pluck('id');

        $expiredCount = 0;

        foreach ($balanceIds as $balanceId) {
            $wasExpired = DB::transaction(function () use ($balanceId) {
                $balance = LeaveBalance::query()
                    ->whereKey($balanceId)
                    ->lockForUpdate()
                    ->first();

                if (! $balance) {
                    return false;
                }

                if (
                    $balance->allocation_type !== 'special'
                    || $balance->expires_at === null
                    || ! $balance->expires_at->lt(today())
                    || (float) $balance->remaining_days <= 0
                ) {
                    return false;
                }

                $isSpecialType = $balance->leaveType()
                    ->whereIn('code', ['MT', 'PT'])
                    ->exists();

                if (! $isSpecialType) {
                    return false;
                }

                /*
                 * A previous expiry transaction means this allocation
                 * has already been processed. Never duplicate it.
                 */
                $alreadyExpired = LeaveBalanceTransaction::query()
                    ->where('leave_balance_id', $balance->id)
                    ->where('transaction_type', 'expiry')
                    ->exists();

                if ($alreadyExpired) {
                    return false;
                }

                $remaining = number_format(
                    (float) $balance->remaining_days,
                    2,
                    '.',
                    ''
                );

                $expiredAmount = number_format(
                    -1 * (float) $remaining,
                    2,
                    '.',
                    ''
                );

                $balance->remaining_days = '0.00';
                $balance->save();

                LeaveBalanceTransaction::create([
                    'leave_balance_id' => $balance->id,
                    'leave_application_id' => null,
                    'reverses_transaction_id' => null,
                    'transaction_type' => 'expiry',
                    'days' => $expiredAmount,
                    'balance_after' => '0.00',
                    'description' => sprintf(
                        '%s unused %s days expired after the validity end date %s.',
                        $remaining,
                        $balance->leaveType->code,
                        $balance->expires_at->toDateString()
                    ),
                    'created_by' => null,
                ]);

                return true;
            });

            if ($wasExpired) {
                $expiredCount++;
            }
        }

        $this->info(
            "Expired {$expiredCount} special leave allocation(s)."
        );

        return self::SUCCESS;
    }
}
