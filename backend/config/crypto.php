<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Master HD Wallet Key
    |--------------------------------------------------------------------------
    | 64 hex chars (32 bytes). NEVER commit to git.
    | Generate with: openssl rand -hex 32
    | Used to derive unique deposit addresses for every user deterministically.
    */
    'master_key' => env('MASTER_CRYPTO_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Blockchain API Keys
    |--------------------------------------------------------------------------
    */
    'trongrid_api_key' => env('TRONGRID_API_KEY', ''),
    'alchemy_api_key'  => env('ALCHEMY_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | USDT Contract Addresses
    |--------------------------------------------------------------------------
    */
    'usdt_trc20_contract' => env('USDT_TRC20_CONTRACT', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),
    'usdt_erc20_contract' => env('USDT_ERC20_CONTRACT', '0xdAC17F958D2ee523a2206206994597C13D831ec7'),
    'usdt_bep20_contract' => env('USDT_BEP20_CONTRACT', '0x55d398326f99059fF775485246999027B3197955'),

    /*
    |--------------------------------------------------------------------------
    | Hot Wallet Addresses (eSahlan's sending wallets)
    |--------------------------------------------------------------------------
    | These are the addresses from which withdrawal transactions are broadcast.
    */
    'hot_wallet_tron' => env('HOT_WALLET_TRON', ''),
    'hot_wallet_eth'  => env('HOT_WALLET_ETH', ''),
];
