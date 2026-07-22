<?php

namespace App\Jobs;

use App\Services\CryptoMarketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshCryptoPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 2;
    public $timeout = 30;

    public function handle(): void
    {
        $count = CryptoMarketService::refreshPrices();
        Log::info("[CryptoPrices] Updated {$count} coins");
    }
}
