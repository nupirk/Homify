<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\User;

class DebugInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get debug information for testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get an order
        $order = Order::first();
        if ($order) {
            $this->line("Order ID: " . $order->order_id);
            $this->line("User ID: " . $order->user_id);
            $this->line("Order Status: " . $order->status);

            // Get the user and create a token
            $user = User::find($order->user_id);
            if ($user) {
                $token = $user->createToken('debug-token')->plainTextToken;
                $this->line("Auth Token: " . $token);
                $this->line("User Email: " . $user->email);
            }
        } else {
            $this->error("No orders found");
        }
    }
}
