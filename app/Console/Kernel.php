<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\Order;
use Carbon\Carbon;
use App\Models\Product;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->call(function () {
            $products = Product::all();
            foreach ($products as $product) {
                if ($product->counter) {
                    $timer = new Carbon($product->counter_created_at);
                    if (now()->greaterThanOrEqualTo($timer)) {
                        while(now()->greaterThanOrEqualTo($timer)) {
                            $timer = $timer->addHours($product->counter);
                        }
                        $orders = \DB::table('orders')
                            ->where('status', Order::STATUS['verified'])
                            ->where('created_at', '>=', (new Carbon($product->counter_created_at))->subHours($product->counter))
                            ->get();
                        foreach ($orders as $order) {
                            $orderProd = \DB::table('order_product')
                                ->where('order_id', $order->id)
                                ->where('product_id', $product->id)
                                ->first();
                            if ($orderProd) {
                                $diff = $product->discount_price - $orderProd->price;
                                \DB::transaction(function () use ($diff, $order, $orderProd, $product) {
                                    \DB::table('transactions')
                                        ->insert([
                                            'transactionable_type' => 'Discount',
                                            'transactionable_id' => $product->id,
                                            'user_id' => $order->user_id,
                                            'amount' => $diff * $orderProd->quantity,
                                            'type' => 'wallet',
                                            'is_verified' => true,
                                            'code' => rand(10000, 99999) . str_replace('.', '', microtime(true)),
                                            'device' => 'browser',
                                            'created_at' => now(),
                                            'updated_at' => now(),
                                        ]);
                                    $wallet = \DB::table('wallets')
                                        ->where('user_id', $order->user_id)
                                        ->first();
                                    \DB::table('wallets')
                                        ->update(['user_id' => $wallet->user_id], ['balance' => $wallet->balance + $diff * $orderProd->quantity]);
                                });
                                
                            }
                        }
                        \DB::table('products')
                            ->where('id', $product->id)
                            ->update(['counter_created_at' => $timer, 'counter_sales' => 0]);
                    }
                }
            }
        });
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
