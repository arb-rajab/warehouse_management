<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Models\StoreUser;
use App\Notifications\UnorderedCartNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

class CheckUnorderedCarts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:unordered-carts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for unordered carts and send notifications.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $startTime = Carbon::now();
            $threshold = $startTime->copy()->subDays(5);

            $carts = Cart::query()
                ->select('user_id')
                ->distinct()
                ->where('updated_at', '<=', $threshold)
                ->where('is_admin_notified', false)
                ->with(['user' => fn ($query) => $query->select('id', 'name', 'is_rep')->withCount('carts')])
                ->get();

            $admins = StoreUser::query()->where('user_type', 'admin')->get(['id']);
            $notificationsCount = 0;

            foreach ($carts as $cart) {
                $user = $cart->user;

                if (! $user || $user->is_rep) {
                    continue;
                }

                $message = 'have added '.$user->carts_count.' products to their cart since 5 days and not placed an order.';
                Notification::send($admins, new UnorderedCartNotification($user->name, $user->id, $user->carts_count, $message));

                // Every stale cart of this user, so the next run does not notify about the same user again.
                Cart::query()
                    ->where('user_id', $user->id)
                    ->where('updated_at', '<=', $threshold)
                    ->where('is_admin_notified', false)
                    ->update(['is_admin_notified' => true]);

                $notificationsCount++;
            }

            $time = (int) Carbon::now()->diffInSeconds($startTime, true);
            $this->info($notificationsCount.' notifications have sent successfully. Process took '.$time.' seconds');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Command failed '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
