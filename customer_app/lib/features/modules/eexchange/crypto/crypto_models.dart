// Crypto Exchange — Data Models
// All relative imports; no package: prefix

class CryptoCoin {
  final int id;
  final String symbol;
  final String name;
  final double priceUsd;
  final double change24h;
  final double volume24h;
  final double marketCap;
  final double high24h;
  final double low24h;
  final bool isActive;
  final bool isTradable;
  final bool isP2pEnabled;
  final double buyFee;
  final double sellFee;
  final List<CoinNetwork> networks;

  const CryptoCoin({
    required this.id,
    required this.symbol,
    required this.name,
    required this.priceUsd,
    required this.change24h,
    required this.volume24h,
    required this.marketCap,
    required this.high24h,
    required this.low24h,
    required this.isActive,
    required this.isTradable,
    required this.isP2pEnabled,
    required this.buyFee,
    required this.sellFee,
    required this.networks,
  });

  factory CryptoCoin.fromJson(Map<String, dynamic> j) => CryptoCoin(
    id: j['id'] ?? 0,
    symbol: j['symbol'] ?? '',
    name: j['name'] ?? '',
    priceUsd: (j['price_usd'] ?? 0).toDouble(),
    change24h: (j['change_24h'] ?? 0).toDouble(),
    volume24h: (j['volume_24h'] ?? 0).toDouble(),
    marketCap: (j['market_cap'] ?? 0).toDouble(),
    high24h: (j['high_24h'] ?? 0).toDouble(),
    low24h: (j['low_24h'] ?? 0).toDouble(),
    isActive: j['is_active'] == true,
    isTradable: j['is_tradable'] == true,
    isP2pEnabled: j['is_p2p_enabled'] == true,
    buyFee: (j['buy_fee_pct'] ?? 1.5).toDouble(),
    sellFee: (j['sell_fee_pct'] ?? 1.5).toDouble(),
    networks: (j['networks'] as List? ?? []).map((n) => CoinNetwork.fromJson(n)).toList(),
  );

  String get priceFormatted {
    if (priceUsd >= 10) return '\$${priceUsd.toStringAsFixed(2)}';
    if (priceUsd >= 0.01) return '\$${priceUsd.toStringAsFixed(4)}';
    return '\$${priceUsd.toStringAsFixed(6)}';
  }

  bool get isUp => change24h >= 0;
}

class CoinNetwork {
  final int id;
  final String name;
  final String symbol;
  final double withdrawalFee;
  final bool isActive;

  const CoinNetwork({required this.id, required this.name, required this.symbol, required this.withdrawalFee, required this.isActive});

  factory CoinNetwork.fromJson(Map<String, dynamic> j) => CoinNetwork(
    id: j['id'] ?? 0,
    name: j['network_name'] ?? '',
    symbol: j['network_symbol'] ?? '',
    withdrawalFee: (j['withdrawal_fee'] ?? 0).toDouble(),
    isActive: j['is_active'] == true,
  );
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

  const CryptoWalletBalance({
    required this.coinId,
    required this.symbol,
    required this.name,
    required this.balance,
    required this.lockedBalance,
    required this.balanceUsd,
    required this.priceUsd,
    required this.depositAddress,
  });

  factory CryptoWalletBalance.fromJson(Map<String, dynamic> j) => CryptoWalletBalance(
    coinId: j['coin_id'] ?? 0,
    symbol: j['symbol'] ?? '',
    name: j['name'] ?? '',
    balance: (j['balance'] ?? 0).toDouble(),
    lockedBalance: (j['locked_balance'] ?? 0).toDouble(),
    balanceUsd: (j['balance_usd'] ?? 0).toDouble(),
    priceUsd: (j['price_usd'] ?? 0).toDouble(),
    depositAddress: j['deposit_address'] ?? '',
  );
}

class CryptoOrder {
  final String uuid;
  final String side;
  final String coinSymbol;
  final double quantity;
  final double priceUsd;
  final double totalUsd;
  final String status;
  final String paymentMethod;
  final String createdAt;

  const CryptoOrder({
    required this.uuid,
    required this.side,
    required this.coinSymbol,
    required this.quantity,
    required this.priceUsd,
    required this.totalUsd,
    required this.status,
    required this.paymentMethod,
    required this.createdAt,
  });

  factory CryptoOrder.fromJson(Map<String, dynamic> j) => CryptoOrder(
    uuid: j['uuid'] ?? '',
    side: j['side'] ?? '',
    coinSymbol: j['coin']?['symbol'] ?? '',
    quantity: (j['quantity'] ?? 0).toDouble(),
    priceUsd: (j['price_usd'] ?? 0).toDouble(),
    totalUsd: (j['total_usd'] ?? 0).toDouble(),
    status: j['status'] ?? '',
    paymentMethod: j['payment_method'] ?? '',
    createdAt: j['created_at'] ?? '',
  );
}

class P2pAd {
  final int id;
  final String uuid;
  final String type; // buy | sell
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
  });

  factory P2pOrder.fromJson(Map<String, dynamic> j, int myId) => P2pOrder(
    uuid: j['uuid'] ?? '',
    status: j['status'] ?? '',
    coinSymbol: j['coin']?['symbol'] ?? '',
    cryptoAmount: (j['crypto_amount'] ?? 0).toDouble(),
    priceUsd: (j['price_usd'] ?? 0).toDouble(),
    totalUsd: (j['total_usd'] ?? 0).toDouble(),
    paymentMethod: j['payment_method'] ?? '',
    isBuyer: (j['buyer_id'] ?? 0) == myId,
    counterpartyName: (j['buyer_id'] ?? 0) == myId
        ? (j['seller']?['name'] ?? 'Seller')
        : (j['buyer']?['name'] ?? 'Buyer'),
    paymentProof: j['payment_proof'],
    createdAt: j['created_at'] ?? '',
  );
}
