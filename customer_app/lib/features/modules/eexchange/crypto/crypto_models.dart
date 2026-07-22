// Crypto Exchange — Data Models

class CryptoCoin {
  final int id;
  final String symbol;
  final String name;
  final double priceUsd;
  final double change24h;
  final double change7d;
  final double volume24h;
  final double marketCap;
  final double high24h;
  final double low24h;
  final bool buyEnabled;
  final bool sellEnabled;
  final bool p2pEnabled;
  final bool depositEnabled;
  final bool withdrawalEnabled;
  final double buyFee;
  final double sellFee;
  final double minWithdrawal;
  final double withdrawalFee;
  final int decimals;
  final String? logoUrl;
  final String? coingeckoId;
  final List<CoinNetwork> networks;

  const CryptoCoin({
    required this.id,
    required this.symbol,
    required this.name,
    required this.priceUsd,
    required this.change24h,
    required this.change7d,
    required this.volume24h,
    required this.marketCap,
    required this.high24h,
    required this.low24h,
    required this.buyEnabled,
    required this.sellEnabled,
    required this.p2pEnabled,
    required this.depositEnabled,
    required this.withdrawalEnabled,
    required this.buyFee,
    required this.sellFee,
    required this.minWithdrawal,
    required this.withdrawalFee,
    required this.decimals,
    required this.networks,
    this.logoUrl,
    this.coingeckoId,
  });

  factory CryptoCoin.fromJson(Map<String, dynamic> j) => CryptoCoin(
    id: j['id'] ?? 0,
    symbol: j['symbol'] ?? '',
    name: j['name'] ?? '',
    priceUsd: (j['price_usd'] ?? 0).toDouble(),
    change24h: (j['change_24h'] ?? 0).toDouble(),
    change7d: (j['change_7d'] ?? 0).toDouble(),
    volume24h: (j['volume_24h'] ?? 0).toDouble(),
    marketCap: (j['market_cap'] ?? 0).toDouble(),
    high24h: (j['high_24h'] ?? 0).toDouble(),
    low24h: (j['low_24h'] ?? 0).toDouble(),
    buyEnabled: j['buy_enabled'] == true,
    sellEnabled: j['sell_enabled'] == true,
    p2pEnabled: j['p2p_enabled'] == true,
    depositEnabled: j['deposit_enabled'] == true,
    withdrawalEnabled: j['withdrawal_enabled'] == true,
    buyFee: (j['buy_fee_pct'] ?? 0.5).toDouble(),
    sellFee: (j['sell_fee_pct'] ?? 0.5).toDouble(),
    minWithdrawal: (j['min_withdrawal'] ?? 0).toDouble(),
    withdrawalFee: (j['withdrawal_fee'] ?? 0).toDouble(),
    decimals: j['decimals'] ?? 6,
    logoUrl: j['logo_url']?.toString(),
    coingeckoId: j['coingecko_id']?.toString(),
    networks: (j['networks'] as List? ?? []).map((n) => CoinNetwork.fromJson(n)).toList(),
  );

  String get priceFormatted {
    if (priceUsd >= 10) return '\$${priceUsd.toStringAsFixed(2)}';
    if (priceUsd >= 0.01) return '\$${priceUsd.toStringAsFixed(4)}';
    return '\$${priceUsd.toStringAsFixed(6)}';
  }

  bool get isUp => change24h >= 0;
  CoinNetwork? get firstNetwork => networks.isNotEmpty ? networks.first : null;

  String volumeFormatted() {
    if (volume24h >= 1e9) return '\$${(volume24h / 1e9).toStringAsFixed(2)}B';
    if (volume24h >= 1e6) return '\$${(volume24h / 1e6).toStringAsFixed(2)}M';
    if (volume24h >= 1e3) return '\$${(volume24h / 1e3).toStringAsFixed(2)}K';
    return '\$${volume24h.toStringAsFixed(2)}';
  }

  String marketCapFormatted() {
    if (marketCap >= 1e12) return '\$${(marketCap / 1e12).toStringAsFixed(2)}T';
    if (marketCap >= 1e9) return '\$${(marketCap / 1e9).toStringAsFixed(2)}B';
    if (marketCap >= 1e6) return '\$${(marketCap / 1e6).toStringAsFixed(2)}M';
    return '\$${marketCap.toStringAsFixed(0)}';
  }
}

class CoinNetwork {
  final int id;
  final String name;
  final String chain;
  final double withdrawalFee;
  final bool isActive;
  final int confirmationsRequired;
  final String? contractAddress;

  const CoinNetwork({
    required this.id,
    required this.name,
    required this.chain,
    required this.withdrawalFee,
    required this.isActive,
    required this.confirmationsRequired,
    this.contractAddress,
  });

  factory CoinNetwork.fromJson(Map<String, dynamic> j) => CoinNetwork(
    id: j['id'] ?? 0,
    name: j['name'] ?? '',
    chain: j['chain'] ?? '',
    withdrawalFee: (j['withdrawal_fee'] ?? 0).toDouble(),
    isActive: j['is_active'] != false,
    confirmationsRequired: j['confirmations_required'] ?? 1,
    contractAddress: j['contract_address']?.toString(),
  );

  String get label => '$name ($chain)';
}

class CryptoWalletBalance {
  final int coinId;
  final String symbol;
  final String name;
  final double balance;
  final double lockedBalance;
  final double balanceUsd;
  final double priceUsd;
  final String depositAddress;
  final int? networkId;
  final List<Map<String, dynamic>> wallets;

  const CryptoWalletBalance({
    required this.coinId,
    required this.symbol,
    required this.name,
    required this.balance,
    required this.lockedBalance,
    required this.balanceUsd,
    required this.priceUsd,
    required this.depositAddress,
    required this.wallets,
    this.networkId,
  });

  factory CryptoWalletBalance.fromJson(Map<String, dynamic> j) {
    final coin = j['coin'] as Map<String, dynamic>? ?? {};
    final wallets = (j['wallets'] as List? ?? [])
        .cast<Map<String, dynamic>>();
    final firstWallet = wallets.isNotEmpty ? wallets.first : <String, dynamic>{};
    return CryptoWalletBalance(
      coinId: coin['id'] ?? 0,
      symbol: coin['symbol'] ?? '',
      name: coin['name'] ?? '',
      balance: (j['balance'] ?? 0).toDouble(),
      lockedBalance: (firstWallet['locked'] ?? 0).toDouble(),
      balanceUsd: (j['usd_value'] ?? 0).toDouble(),
      priceUsd: (coin['price_usd'] ?? 0).toDouble(),
      depositAddress: firstWallet['address'] ?? '',
      networkId: firstWallet['network_id'] as int?,
      wallets: wallets,
    );
  }
}

class CryptoOrder {
  final String uuid;
  final String side;
  final String coinSymbol;
  final double cryptoAmount;
  final double priceUsd;
  final double totalUsd;
  final double feeUsd;
  final String status;
  final String paymentMethod;
  final String createdAt;

  const CryptoOrder({
    required this.uuid,
    required this.side,
    required this.coinSymbol,
    required this.cryptoAmount,
    required this.priceUsd,
    required this.totalUsd,
    required this.feeUsd,
    required this.status,
    required this.paymentMethod,
    required this.createdAt,
  });

  factory CryptoOrder.fromJson(Map<String, dynamic> j) => CryptoOrder(
    uuid: j['uuid'] ?? '',
    side: j['side'] ?? '',
    coinSymbol: j['coin']?['symbol'] ?? '',
    cryptoAmount: (j['crypto_amount'] ?? 0).toDouble(),
    priceUsd: (j['price_usd'] ?? 0).toDouble(),
    totalUsd: (j['total_usd'] ?? 0).toDouble(),
    feeUsd: (j['fee_usd'] ?? 0).toDouble(),
    status: j['status'] ?? '',
    paymentMethod: j['payment_method'] ?? '',
    createdAt: j['created_at'] ?? '',
  );

  bool get isCompleted => status == 'completed';
  bool get isPending => status == 'pending';
  bool get isBuy => side == 'buy';
}

// ── Extra getters (UI aliases) ────────────────────────────────────────────────

extension CryptoCoinUi on CryptoCoin {
  double get changePercent24h => change24h;
  double get marketCapUsd    => marketCap;
  List<double> get sparkline7d => const [];
  bool get isFavorite        => false;
}

extension P2pAdUi on P2pAd {
  String get traderName      => sellerName;
  bool   get isVerified      => false;
  int    get completedOrders => completedCount;
  double get successRate     => 90.0;
  double get price           => priceUsd;
  double get minAmount       => minOrder;
  double get maxAmount       => maxOrder;
  double get available       => remaining;
}

extension CryptoTransactionUi on CryptoTransaction {
  String get symbol => coinSymbol;
}

extension P2pOrderUi on P2pOrder {
  String get type     => isBuyer ? 'buy' : 'sell';
  double get amountUsd => totalUsd;
}

class CryptoTransaction {
  final int id;
  final String type;
  final double amount;
  final double fee;
  final double balanceBefore;
  final double balanceAfter;
  final String note;
  final String coinSymbol;
  final String createdAt;
  final String status;

  const CryptoTransaction({
    required this.id,
    required this.type,
    required this.amount,
    required this.fee,
    required this.balanceBefore,
    required this.balanceAfter,
    required this.note,
    required this.coinSymbol,
    required this.createdAt,
    required this.status,
  });

  factory CryptoTransaction.fromJson(Map<String, dynamic> j) => CryptoTransaction(
    id: j['id'] ?? 0,
    type: j['type'] ?? '',
    amount: (j['amount'] ?? 0).toDouble(),
    fee: (j['fee'] ?? 0).toDouble(),
    balanceBefore: (j['balance_before'] ?? 0).toDouble(),
    balanceAfter: (j['balance_after'] ?? 0).toDouble(),
    note: j['note'] ?? '',
    coinSymbol: j['coin']?['symbol'] ?? '',
    createdAt: j['created_at'] ?? '',
    status: j['status']?.toString() ?? 'completed',
  );

  bool get isCredit => ['buy','deposit','transfer_in','sell_refund'].contains(type);
}

class P2pAd {
  final int id;
  final String uuid;
  final String type;
  final String coinSymbol;
  final String coinName;
  final double priceUsd;
  final double amount;
  final double remaining;
  final double minOrder;
  final double maxOrder;
  final List<String> paymentMethods;
  final String terms;
  final String sellerName;
  final int sellerId;
  final int completedCount;

  const P2pAd({
    required this.id,
    required this.uuid,
    required this.type,
    required this.coinSymbol,
    required this.coinName,
    required this.priceUsd,
    required this.amount,
    required this.remaining,
    required this.minOrder,
    required this.maxOrder,
    required this.paymentMethods,
    required this.terms,
    required this.sellerName,
    required this.sellerId,
    required this.completedCount,
  });

  factory P2pAd.fromJson(Map<String, dynamic> j) => P2pAd(
    id: j['id'] ?? 0,
    uuid: j['uuid'] ?? '',
    type: j['type'] ?? '',
    coinSymbol: j['coin']?['symbol'] ?? '',
    coinName: j['coin']?['name'] ?? '',
    priceUsd: (j['price_usd'] ?? 0).toDouble(),
    amount: (j['amount'] ?? 0).toDouble(),
    remaining: (j['remaining'] ?? 0).toDouble(),
    minOrder: (j['min_order_usd'] ?? 0).toDouble(),
    maxOrder: (j['max_order_usd'] ?? 0).toDouble(),
    paymentMethods: List<String>.from(j['payment_methods'] ?? []),
    terms: j['terms'] ?? '',
    sellerName: j['user']?['name'] ?? '',
    sellerId: j['user']?['id'] ?? 0,
    completedCount: j['completed_count'] ?? 0,
  );
}

class P2pOrder {
  final String uuid;
  final String status;
  final String coinSymbol;
  final double cryptoAmount;
  final double priceUsd;
  final double totalUsd;
  final String paymentMethod;
  final bool isBuyer;
  final String counterpartyName;
  final String? paymentProof;
  final String createdAt;
  final String? expiresAt;
  final String? terms;

  const P2pOrder({
    required this.uuid,
    required this.status,
    required this.coinSymbol,
    required this.cryptoAmount,
    required this.priceUsd,
    required this.totalUsd,
    required this.paymentMethod,
    required this.isBuyer,
    required this.counterpartyName,
    this.paymentProof,
    required this.createdAt,
    this.expiresAt,
    this.terms,
  });

  factory P2pOrder.fromJson(Map<String, dynamic> j, int myId) => P2pOrder(
    uuid: j['uuid'] ?? '',
    status: j['status'] ?? '',
    coinSymbol: j['coin']?['symbol'] ?? j['ad']?['coin']?['symbol'] ?? '',
    cryptoAmount: (j['crypto_amount'] ?? 0).toDouble(),
    priceUsd: (j['price_usd'] ?? 0).toDouble(),
    totalUsd: (j['total_usd'] ?? 0).toDouble(),
    paymentMethod: j['payment_method'] ?? '',
    isBuyer: (j['buyer']?['id'] ?? 0) == myId,
    counterpartyName: (j['buyer']?['id'] ?? 0) == myId
        ? (j['seller']?['name'] ?? 'Seller')
        : (j['buyer']?['name'] ?? 'Buyer'),
    paymentProof: j['payment_proof'],
    createdAt: j['created_at'] ?? '',
    expiresAt: j['expires_at'],
    terms: j['ad']?['terms'],
  );

  bool get isActive => ['payment_waiting', 'paid'].contains(status);
  bool get canMarkPaid => status == 'payment_waiting' && isBuyer;
  bool get canRelease => status == 'paid' && !isBuyer;
}

class ChartPoint {
  final DateTime time;
  final double price;
  const ChartPoint({required this.time, required this.price});

  factory ChartPoint.fromJson(dynamic j) {
    if (j is List && j.length >= 2) {
      return ChartPoint(
        time: DateTime.fromMillisecondsSinceEpoch((j[0] as num).toInt()),
        price: (j[1] as num).toDouble(),
      );
    }
    return ChartPoint(time: DateTime.now(), price: 0);
  }
}
