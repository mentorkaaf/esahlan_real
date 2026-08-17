import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/services/realtime_client.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/error_handler.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/wallet_pin_dialog.dart';
import '../../auth/presentation/providers/auth_provider.dart';
import '../../payment/waafi_pay_sheet.dart';
import '../../payment/mobile_pay_sheet.dart';
import '../../payment/payment_method_section.dart';
import '../../wallet/presentation/providers/wallet_provider.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../features/global/presentation/providers/global_provider.dart';
import 'crypto/crypto_exchange_screen.dart';

final _svc = ModuleApiService.create();

/// Wallet logos from admin — cached globally, refreshes on hot restart
final _walletLogosProvider = FutureProvider<Map<String, String>>((ref) async {
  try {
    final res = await _svc.getWalletLogos();
    final data = res['data'] as Map<String, dynamic>? ?? {};
    return data.map((k, v) => MapEntry(k, v.toString()));
  } catch (_) { return {}; }
});

final _exchangeAccountsProvider = FutureProvider<List<_ExchangeAccount>>((ref) async {
  try {
    final res = await _svc.getExchangeAccounts();
    final list = res['data'] as List? ?? [];
    return list.map((e) => _ExchangeAccount.fromJson(e)).toList();
  } catch (_) { return []; }
});
final _exchangeOrdersProvider = FutureProvider<List<_ExchangeOrder>>((ref) async {
  try {
    final res = await _svc.getExchangeOrders();
    final list = res['data'] as List? ?? [];
    return list.map((e) => _ExchangeOrder.fromJson(e)).toList();
  } catch (_) { return []; }
});

// ── Models ─────────────────────────────────────────────────────────────────────

class _ExchangeAccount {
  final int id;
  final String walletType;
  final String phoneNumber;
  final String? label;
  const _ExchangeAccount({required this.id, required this.walletType, required this.phoneNumber, this.label});
  factory _ExchangeAccount.fromJson(Map<String, dynamic> j) => _ExchangeAccount(
    id: j['id'] as int, walletType: j['wallet_type'] as String,
    phoneNumber: j['phone_number'] as String, label: j['label'] as String?,
  );
}

class _ExchangeOrder {
  final int id;
  final String fromWallet;
  final String toWallet;
  final double sentAmount;
  final double receivedAmount;
  final String status;
  final String? reference;
  final DateTime createdAt;
  _ExchangeOrder({required this.id, required this.fromWallet, required this.toWallet,
    required this.sentAmount, required this.receivedAmount, required this.status,
    this.reference, required this.createdAt});
  factory _ExchangeOrder.fromJson(Map<String, dynamic> j) => _ExchangeOrder(
    id: j['id'] as int,
    fromWallet: j['from_wallet'] as String? ?? '',
    toWallet: j['to_wallet'] as String? ?? '',
    sentAmount: double.tryParse(j['sent_amount']?.toString() ?? '0') ?? 0,
    receivedAmount: double.tryParse(j['received_amount']?.toString() ?? '0') ?? 0,
    status: j['status'] as String? ?? 'pending',
    reference: j['reference'] as String?,
    createdAt: DateTime.tryParse(j['created_at']?.toString() ?? '') ?? DateTime.now(),
  );
}

// ── Wallet definitions ────────────────────────────────────────────────────────

class _WalletDef {
  final String id;
  final String name;
  final String shortName;
  final Color color;
  final Color bgColor;
  const _WalletDef(this.id, this.name, this.shortName, this.color, this.bgColor);
}

const _wallets = [
  _WalletDef('evc',     'EVC Plus',             'EVC',     Color(0xFF00A8E8), Color(0xFFE6F6FD)),
  _WalletDef('edahab',  'eDahab Merchant',       'eDahab',  Color(0xFF2E7D32), Color(0xFFE8F5E9)),
  _WalletDef('jeep',    'Jeep Money',            'Jeep',    Color(0xFF0D47A1), Color(0xFFE3F2FD)),
  _WalletDef('premier', 'Premier Wallet',        'Premier', Color(0xFF1A237E), Color(0xFFEDE7F6)),
  _WalletDef('ebesa',   'eBesa',                 'eBesa',   Color(0xFF6A1B9A), Color(0xFFF3E5F5)),
];

_WalletDef _wallet(String id) =>
    _wallets.firstWhere((w) => w.id == id, orElse: () => _wallets.first);

// ── Main Screen ───────────────────────────────────────────────────────────────

class EExchangeScreen extends ConsumerStatefulWidget {
  const EExchangeScreen({super.key});
  @override
  ConsumerState<EExchangeScreen> createState() => _EExchangeScreenState();
}

class _EExchangeScreenState extends ConsumerState<EExchangeScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabCtrl;
  bool _lastCryptoEnabled = true;

  @override
  void initState() {
    super.initState();
    _tabCtrl = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabCtrl.dispose();
    super.dispose();
  }

  void _rebuildTabs(bool cryptoEnabled) {
    if (cryptoEnabled == _lastCryptoEnabled) return;
    _lastCryptoEnabled = cryptoEnabled;
    final oldCtrl = _tabCtrl;
    _tabCtrl = TabController(length: cryptoEnabled ? 2 : 1, vsync: this);
    oldCtrl.dispose();
  }

  void _openAddAccounts() async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const AddAccountsScreen()));
    ref.invalidate(_exchangeAccountsProvider);
  }

  @override
  Widget build(BuildContext context) {
    final cryptoEnabled = ref.watch(cryptoEnabledProvider);
    _rebuildTabs(cryptoEnabled);

    return Scaffold(
      backgroundColor: const Color(0xFFF5F6FA),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: context.colors.navyText),
          onPressed: () => context.pop(),
        ),
        title: Text('eExchange',
            style: TextStyle(fontWeight: FontWeight.w800, color: context.colors.navyText, fontFamily: 'Cairo')),
        actions: [
          // ── Add Accounts button ──────────────────────────────────────
          GestureDetector(
            onTap: _openAddAccounts,
            child: Container(
              margin: const EdgeInsets.only(right: 16),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.account_balance_wallet_rounded, size: 16, color: AppColors.primary),
                const SizedBox(width: 5),
                Text('My Accounts', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: AppColors.primary)),
              ]),
            ),
          ),
        ],
        bottom: TabBar(
          controller: _tabCtrl,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textGrey,
          indicatorColor: AppColors.primary,
          indicatorWeight: 2.5,
          labelStyle: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
          tabs: [
            const Tab(text: 'Local'),
            if (cryptoEnabled) const Tab(text: 'Crypto'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabCtrl,
        children: [
          _LocalExchangeTab(onAddAccounts: _openAddAccounts),
          if (cryptoEnabled) const CryptoExchangeScreen(),
        ],
      ),
    );
  }
}

// ── Local Exchange Tab ────────────────────────────────────────────────────────

class _LocalExchangeTab extends ConsumerStatefulWidget {
  final VoidCallback onAddAccounts;
  const _LocalExchangeTab({required this.onAddAccounts});
  @override
  ConsumerState<_LocalExchangeTab> createState() => _LocalExchangeTabState();
}

class _LocalExchangeTabState extends ConsumerState<_LocalExchangeTab> {
  String _fromWallet = 'premier';
  String _toWallet   = 'evc';
  double _amount     = 0;
  String _paymentMethod = 'wallet';
  String? _waafiRef;
  Map<String, dynamic>? _preview;
  bool _converting = false;
  bool _confirming = false;

  final _amountCtrl = TextEditingController();

  // ── Real-time order status ─────────────────────────────────────────────────
  void Function(dynamic)? _orderUpdateListener;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _subscribeRealtime());
  }

  void _subscribeRealtime() {
    final user = ref.read(authStateProvider).valueOrNull;
    if (user == null) return;
    _orderUpdateListener = (data) {
      if (!mounted) return;
      // Refresh the orders list when server pushes an update
      ref.invalidate(_exchangeOrdersProvider);
    };
    RealtimeClient.instance.listen(
      'private-user.${user.id}',
      'exchange.order_updated',
      _orderUpdateListener!,
    );
  }

  @override
  void dispose() {
    if (_orderUpdateListener != null) {
      final user = ref.read(authStateProvider).valueOrNull;
      if (user != null) {
        RealtimeClient.instance.removeListener(
          'private-user.${user.id}',
          'exchange.order_updated',
          _orderUpdateListener!,
        );
      }
    }
    _amountCtrl.dispose();
    super.dispose();
  }

  List<_ExchangeAccount> get _accounts =>
      ref.read(_exchangeAccountsProvider).valueOrNull ?? [];

  _ExchangeAccount? _accountFor(String walletId) {
    try { return _accounts.firstWhere((a) => a.walletType == walletId); }
    catch (_) { return null; }
  }

  // Recipient phone comes from saved "To" account
  String get _recipientPhone => _accountFor(_toWallet)?.phoneNumber ?? '';
  bool get _hasFromAccount  => _accountFor(_fromWallet) != null;
  bool get _hasToAccount    => _accountFor(_toWallet) != null;
  bool get _canPreview      => _amount > 0 && _fromWallet != _toWallet && _hasToAccount;

  void _selectFromWallet(String id) => setState(() { _fromWallet = id; _preview = null; });
  void _selectToWallet(String id)   => setState(() { _toWallet = id;   _preview = null; });

  void _swapWallets() {
    setState(() {
      final tmp = _fromWallet; _fromWallet = _toWallet; _toWallet = tmp;
      _preview = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    final accountsAsync = ref.watch(_exchangeAccountsProvider);
    final ordersAsync   = ref.watch(_exchangeOrdersProvider);
    final orders        = ordersAsync.valueOrNull ?? [];

    // If accounts still loading, show loader
    if (accountsAsync.isLoading) {
      return const Center(child: CircularProgressIndicator(strokeWidth: 2));
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

        // ── Missing accounts banner ────────────────────────────────────
        if (!_hasFromAccount || !_hasToAccount)
          _buildAddAccountBanner(!_hasFromAccount ? _fromWallet : _toWallet),

        // ── From / To Card ─────────────────────────────────────────────
        _buildFromToCard(),
        const SizedBox(height: 16),

        // ── Amount ─────────────────────────────────────────────────────
        _buildAmountCard(),
        const SizedBox(height: 16),

        // ── Preview / Confirm ──────────────────────────────────────────
        if (_preview != null) ...[
          _buildPreviewCard(),
          const SizedBox(height: 14),
          _buildPaymentMethod(),
          const SizedBox(height: 14),
          AppButton(
            label: _confirming ? 'Processing...' : 'Confirm Exchange',
            isLoading: _confirming,
            onPressed: _fromWallet == _toWallet ? null : _confirm,
          ),
        ] else ...[
          AppButton(
            label: _converting ? 'Calculating...' : 'Preview Exchange',
            isLoading: _converting,
            outlined: true,
            onPressed: _canPreview ? _previewExchange : null,
          ),
          if (_fromWallet == _toWallet)
            _hint('Select different wallets', isError: true)
          else if (!_hasToAccount)
            _hint('Add your ${_wallet(_toWallet).name} account first', isError: true)
          else if (_amount <= 0)
            _hint('Enter an amount to continue'),
        ],
        const SizedBox(height: 28),

        // ── Recent Transactions ────────────────────────────────────────
        _buildRecentTransactions(orders, ordersAsync.isLoading),
        const SizedBox(height: 40),
      ]),
    );
  }

  // ── Missing account banner ────────────────────────────────────────────────

  Widget _buildAddAccountBanner(String walletId) {
    final w = _wallet(walletId);
    return GestureDetector(
      onTap: widget.onAddAccounts,
      child: Container(
        margin: const EdgeInsets.only(bottom: 14),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          color: w.bgColor,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: w.color.withValues(alpha: 0.3)),
        ),
        child: Row(children: [
          _WalletLogo(wallet: w, size: 32),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Add your ${w.name} account',
                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: w.color)),
            Text('Tap to save your phone number for quick exchange',
                style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
          ])),
          Icon(Icons.add_circle_rounded, color: w.color, size: 22),
        ]),
      ),
    );
  }

  // ── From / To Card ────────────────────────────────────────────────────────

  Widget _buildFromToCard() {
    final fromW    = _wallet(_fromWallet);
    final toW      = _wallet(_toWallet);
    final fromAcc  = _accountFor(_fromWallet);
    final toAcc    = _accountFor(_toWallet);

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 12, offset: const Offset(0, 4))],
      ),
      child: Column(children: [

        // ── FROM ────────────────────────────────────────────────────────
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
          child: Row(children: [
            Text('From', style: const TextStyle(fontSize: 12, color: AppColors.textGrey, fontWeight: FontWeight.w600)),
            const Spacer(),
            _buildWalletPills(_fromWallet, _selectFromWallet),
          ]),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 10, 20, 16),
          child: Row(children: [
            _WalletLogo(wallet: fromW, size: 42),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(fromW.name,
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText)),
              const SizedBox(height: 2),
              // Saved account phone
              if (fromAcc != null)
                Text(fromAcc.phoneNumber,
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: fromW.color))
              else
                GestureDetector(
                  onTap: widget.onAddAccounts,
                  child: Text('+ Add account',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: fromW.color.withValues(alpha: 0.7))),
                ),
            ])),
            Text('\$  0.00', style: TextStyle(fontWeight: FontWeight.w300, fontSize: 18,
                color: context.colors.navyText.withValues(alpha: 0.35))),
          ]),
        ),

        // ── Divider + Swap ──────────────────────────────────────────────
        Stack(alignment: Alignment.center, children: [
          Divider(height: 1, color: Colors.grey.shade100),
          GestureDetector(
            onTap: _swapWallets,
            child: Container(
              padding: const EdgeInsets.all(7),
              decoration: BoxDecoration(
                color: Colors.white,
                shape: BoxShape.circle,
                border: Border.all(color: Colors.grey.shade200, width: 1.5),
              ),
              child: Icon(Icons.swap_vert_rounded, size: 22, color: AppColors.primary),
            ),
          ),
        ]),

        // ── TO ──────────────────────────────────────────────────────────
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
          child: Row(children: [
            _WalletLogo(wallet: toW, size: 42),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(toW.name,
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText)),
              const SizedBox(height: 2),
              // Saved account phone (recipient auto-filled)
              if (toAcc != null)
                Row(children: [
                  Text(toAcc.phoneNumber,
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: toW.color)),
                  const SizedBox(width: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                    decoration: BoxDecoration(color: toW.bgColor, borderRadius: BorderRadius.circular(4)),
                    child: Text('Saved', style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: toW.color)),
                  ),
                ])
              else
                GestureDetector(
                  onTap: widget.onAddAccounts,
                  child: Text('+ Add account to auto-fill',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: toW.color.withValues(alpha: 0.7))),
                ),
            ])),
            Text('\$  0.00', style: TextStyle(fontWeight: FontWeight.w300, fontSize: 18,
                color: context.colors.navyText.withValues(alpha: 0.35))),
          ]),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
          child: Row(children: [
            Text('To', style: const TextStyle(fontSize: 12, color: AppColors.textGrey, fontWeight: FontWeight.w600)),
            const Spacer(),
            _buildWalletPills(_toWallet, _selectToWallet),
          ]),
        ),
      ]),
    );
  }

  Widget _buildWalletPills(String selected, void Function(String) onSelect) {
    return Row(mainAxisSize: MainAxisSize.min, children: _wallets.map((w) {
      final sel = selected == w.id;
      final hasSaved = _accountFor(w.id) != null;
      return GestureDetector(
        onTap: () => onSelect(w.id),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 150),
          margin: const EdgeInsets.only(left: 5),
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(
            color: sel ? w.color : (hasSaved ? w.bgColor : Colors.grey.shade100),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Text(w.shortName,
                style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800,
                    color: sel ? Colors.white : (hasSaved ? w.color : AppColors.textGrey))),
            if (hasSaved && !sel) ...[
              const SizedBox(width: 3),
              Container(width: 5, height: 5,
                  decoration: BoxDecoration(color: w.color, shape: BoxShape.circle)),
            ],
          ]),
        ),
      );
    }).toList());
  }

  // ── Amount Card ───────────────────────────────────────────────────────────

  Widget _buildAmountCard() => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8)]),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('Amount to Send', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.textGrey)),
      const SizedBox(height: 8),
      TextField(
        controller: _amountCtrl,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        style: TextStyle(fontWeight: FontWeight.w900, fontSize: 32, color: context.colors.navyText),
        onChanged: (v) => setState(() { _amount = double.tryParse(v) ?? 0; _preview = null; }),
        decoration: InputDecoration(
          hintText: '0.00',
          hintStyle: TextStyle(fontWeight: FontWeight.w300, fontSize: 32, color: Colors.grey.shade300),
          border: InputBorder.none,
          prefixText: '\$  ',
          prefixStyle: TextStyle(fontWeight: FontWeight.w900, fontSize: 32, color: context.colors.navyText),
          isDense: true,
        ),
      ),
      const SizedBox(height: 12),
      Wrap(spacing: 8, children: [10, 25, 50, 100, 200].map((v) => GestureDetector(
        onTap: () { _amountCtrl.text = v.toString(); setState(() { _amount = v.toDouble(); _preview = null; }); },
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
          decoration: BoxDecoration(color: Colors.grey.shade100, borderRadius: BorderRadius.circular(20)),
          child: Text('\$$v', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: context.colors.navyText)),
        ),
      )).toList()),
    ]),
  );

  // ── Preview Card ──────────────────────────────────────────────────────────

  Widget _buildPreviewCard() {
    final fromW = _wallet(_fromWallet);
    final toW   = _wallet(_toWallet);
    final toAcc = _accountFor(_toWallet);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white, borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10)],
      ),
      child: Column(children: [
        Row(children: [
          _WalletLogo(wallet: fromW, size: 36),
          const SizedBox(width: 8),
          Expanded(child: Text(fromW.shortName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14))),
          Icon(Icons.arrow_forward_rounded, color: Colors.grey.shade400, size: 20),
          const SizedBox(width: 8),
          _WalletLogo(wallet: toW, size: 36),
          const SizedBox(width: 8),
          Expanded(child: Text(toW.shortName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14))),
        ]),
        const SizedBox(height: 16),
        Divider(color: Colors.grey.shade100),
        const SizedBox(height: 12),
        _previewRow('You Send',      '\$${_preview!['amount']}'),
        _previewRow('Exchange Rate', '${_preview!['rate']}'),
        _previewRow('Service Fee',   '-\$${_preview!['fee']}'),
        const SizedBox(height: 8),
        Divider(color: Colors.grey.shade100),
        const SizedBox(height: 10),
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Text('Recipient Gets', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText)),
          Text('\$${_preview!['converted_amount'] ?? _preview!['converted'] ?? _preview!['you_receive']}',
              style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 26, color: Color(0xFF2E7D32))),
        ]),
        if (toAcc != null) ...[
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(color: toW.bgColor, borderRadius: BorderRadius.circular(12)),
            child: Row(children: [
              _WalletLogo(wallet: toW, size: 28),
              const SizedBox(width: 10),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(toAcc.phoneNumber, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: toW.color)),
                Text(toW.name, style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
              ])),
              Icon(Icons.check_circle_rounded, color: toW.color, size: 18),
            ]),
          ),
        ],
      ]),
    );
  }

  Widget _previewRow(String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: const TextStyle(fontSize: 13, color: AppColors.textGrey)),
      Text(value, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: context.colors.navyText)),
    ]),
  );

  Widget _buildPaymentMethod() => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8)]),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Row(children: [
        Icon(Icons.payment_rounded, size: 18, color: AppColors.primary),
        SizedBox(width: 8),
        Text('Payment Method', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
      ]),
      const SizedBox(height: 12),
      PaymentMethodSection(selected: _paymentMethod, onChanged: (v) => setState(() { _paymentMethod = v; _waafiRef = null; })),
    ]),
  );

  // ── Recent Transactions ───────────────────────────────────────────────────

  Widget _buildRecentTransactions(List<_ExchangeOrder> orders, bool loading) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Icon(Icons.receipt_long_rounded, size: 18, color: context.colors.navyText),
        const SizedBox(width: 8),
        Text('Recent Transactions',
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.navyText)),
        const Spacer(),
        if (orders.isNotEmpty)
          TextButton(
            onPressed: () => ref.invalidate(_exchangeOrdersProvider),
            style: TextButton.styleFrom(minimumSize: Size.zero, padding: EdgeInsets.zero, tapTargetSize: MaterialTapTargetSize.shrinkWrap),
            child: const Text('Refresh', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 12)),
          ),
      ]),
      const SizedBox(height: 12),
      if (loading)
        Container(
          width: double.infinity, padding: const EdgeInsets.all(32),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
          child: const Center(child: CircularProgressIndicator(strokeWidth: 2)),
        )
      else if (orders.isEmpty)
        Container(
          width: double.infinity, padding: const EdgeInsets.all(28),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
              boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8)]),
          child: Column(children: [
            Container(width: 56, height: 56,
                decoration: BoxDecoration(color: const Color(0xFFF3F4F6), shape: BoxShape.circle),
                child: const Icon(Icons.swap_horiz_rounded, size: 28, color: AppColors.textGrey)),
            const SizedBox(height: 12),
            const Text('No transactions yet', style: TextStyle(color: AppColors.textGrey, fontWeight: FontWeight.w700, fontSize: 14)),
            const SizedBox(height: 4),
            Text('Your exchange history will appear here', style: TextStyle(fontSize: 12, color: Colors.grey.shade400)),
          ]),
        )
      else
        Container(
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
              boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8)]),
          child: ListView.separated(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: orders.length,
            separatorBuilder: (_, __) => Divider(height: 1, indent: 70, color: Colors.grey.shade100),
            itemBuilder: (_, i) => _buildOrderTile(orders[i]),
          ),
        ),
    ]);
  }

  Widget _buildOrderTile(_ExchangeOrder order) {
    final fromW = _wallet(order.fromWallet);
    final toW   = _wallet(order.toWallet);

    // Status colors + labels
    final Color statusColor;
    final String statusLabel;
    final IconData statusIcon;
    switch (order.status) {
      case 'completed':
        statusColor = const Color(0xFF2E7D32);
        statusLabel = 'Completed';
        statusIcon  = Icons.check_circle_rounded;
        break;
      case 'processing':
        statusColor = const Color(0xFF1565C0);
        statusLabel = 'Processing';
        statusIcon  = Icons.autorenew_rounded;
        break;
      case 'failed':
        statusColor = AppColors.error;
        statusLabel = 'Failed';
        statusIcon  = Icons.cancel_rounded;
        break;
      default:
        statusColor = const Color(0xFFF57C00);
        statusLabel = 'Pending';
        statusIcon  = Icons.access_time_rounded;
    }

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
      child: Row(children: [
        // Wallet logo with overlay
        Stack(children: [
          _WalletLogo(wallet: fromW, size: 46),
          Positioned(bottom: 0, right: 0,
            child: Container(
              padding: const EdgeInsets.all(2),
              decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
              child: _WalletLogo(wallet: toW, size: 18),
            ),
          ),
        ]),
        const SizedBox(width: 13),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // "eDahab Merchant to EVC" — matches sample design
          Text('${fromW.name} to ${toW.shortName}',
              style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5, color: context.colors.navyText),
              maxLines: 1, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 4),
          Row(children: [
            Icon(statusIcon, size: 12, color: statusColor),
            const SizedBox(width: 4),
            Text('$statusLabel · ${toW.name}',
                style: TextStyle(fontSize: 11.5, color: statusColor, fontWeight: FontWeight.w600),
                maxLines: 1, overflow: TextOverflow.ellipsis),
          ]),
        ])),
        const SizedBox(width: 8),
        Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
          Text('\$${order.sentAmount.toStringAsFixed(2)}',
              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText)),
          const SizedBox(height: 3),
          Text('sent', style: TextStyle(fontSize: 10, color: Colors.grey.shade400, fontWeight: FontWeight.w500)),
        ]),
      ]),
    );
  }

  Widget _hint(String text, {bool isError = false}) => Padding(
    padding: const EdgeInsets.only(top: 10),
    child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
      Icon(isError ? Icons.info_outline : Icons.check_circle_outline,
          size: 14, color: isError ? AppColors.error : AppColors.success),
      const SizedBox(width: 6),
      Flexible(child: Text(text, style: TextStyle(color: isError ? AppColors.error : AppColors.success, fontSize: 12), textAlign: TextAlign.center)),
    ]),
  );

  // ── Logic ─────────────────────────────────────────────────────────────────

  Future<void> _previewExchange() async {
    setState(() => _converting = true);
    try {
      final res = await _svc.previewExchange({'from_wallet': _fromWallet, 'to_wallet': _toWallet, 'amount': _amount});
      setState(() => _preview = Map<String, dynamic>.from(res['data']));
    } catch (e) {
      if (mounted) _showErrorDialog(AppErrorHandler.message(e));
    } finally {
      if (mounted) setState(() => _converting = false);
    }
  }

  Future<void> _confirm() async {
    final phone = _recipientPhone;
    if (phone.isEmpty) { _showErrorDialog('Add your ${_wallet(_toWallet).name} account first'); return; }

    if (_paymentMethod == 'mobile_pay') {
      final result = await showMobilePaySheet(context, amount: _amount,
          description: 'eExchange ${_fromWallet.toUpperCase()} → ${_toWallet.toUpperCase()}');
      if (result?.success != true) return;
      _waafiRef = result!.account != null ? 'mobile_pay_${result.account!.id}' : 'mobile_pay';
    } else if (_paymentMethod == 'waafi_pay') {
      if (!mounted) return;
      final result = await showWaafiPaySheet(context, amount: _amount, type: 'order',
          description: 'eExchange', prefillPhone: phone);
      if (result?.success != true) return;
      _waafiRef = result!.reference;
    }
    if (_paymentMethod == 'wallet') {
      if (!mounted) return;
      final pinOk = await showWalletPinDialog(context);
      if (!pinOk) return;
    }
    setState(() => _confirming = true);
    try {
      final res = await _svc.confirmExchange({
        'from_wallet': _fromWallet, 'to_wallet': _toWallet,
        'amount': _amount, 'recipient_phone': phone,
        'payment_method': _paymentMethod,
        if (_waafiRef != null) 'payment_reference': _waafiRef,
      });
      if (mounted) {
        final data = Map<String, dynamic>.from(res['data'] ?? {});
        if (_paymentMethod == 'wallet') ref.invalidate(walletProvider);
        ref.invalidate(_exchangeOrdersProvider);
        Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => _ExchangeSuccessScreen(data: data)));
      }
    } catch (e) {
      if (mounted) _showErrorDialog(AppErrorHandler.message(e));
    } finally {
      if (mounted) setState(() => _confirming = false);
    }
  }

  void _showErrorDialog(String message) {
    showDialog(context: context, builder: (_) => Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 64, height: 64,
              decoration: BoxDecoration(color: AppColors.error.withValues(alpha: 0.1), shape: BoxShape.circle),
              child: const Icon(Icons.error_outline_rounded, color: AppColors.error, size: 36)),
          const SizedBox(height: 16),
          Text('Something went wrong',
              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17, color: context.colors.navyText),
              textAlign: TextAlign.center),
          const SizedBox(height: 10),
          Text(message, style: const TextStyle(color: AppColors.textGrey, fontSize: 13, height: 1.5), textAlign: TextAlign.center),
          const SizedBox(height: 22),
          SizedBox(width: double.infinity, child: ElevatedButton(
            onPressed: () => Navigator.of(context).pop(),
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary, foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                padding: const EdgeInsets.symmetric(vertical: 14)),
            child: const Text('OK', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
          )),
        ]),
      ),
    ));
  }
}

// ── Wallet Logo Widget ────────────────────────────────────────────────────────

class _WalletLogo extends ConsumerWidget {
  final _WalletDef wallet;
  final double size;
  const _WalletLogo({required this.wallet, required this.size});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final logos = ref.watch(_walletLogosProvider).valueOrNull ?? {};
    final logoUrl = logos[wallet.id];

    return Container(
      width: size, height: size,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: wallet.bgColor,
        borderRadius: BorderRadius.circular(size * 0.25),
      ),
      child: logoUrl != null
          ? Image.network(
              logoUrl,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => _fallbackText(),
            )
          : _fallbackText(),
    );
  }

  Widget _fallbackText() => Center(
    child: Text(wallet.shortName,
        style: TextStyle(fontSize: size * 0.28, fontWeight: FontWeight.w900, color: wallet.color),
        maxLines: 1, overflow: TextOverflow.clip),
  );
}

// ── Add Accounts Screen ───────────────────────────────────────────────────────

class AddAccountsScreen extends ConsumerStatefulWidget {
  const AddAccountsScreen({super.key});
  @override
  ConsumerState<AddAccountsScreen> createState() => _AddAccountsScreenState();
}

class _AddAccountsScreenState extends ConsumerState<AddAccountsScreen> {
  final Map<String, TextEditingController> _ctrls = {
    for (final w in _wallets) w.id: TextEditingController(text: '+252 ')
  };
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final accounts = ref.read(_exchangeAccountsProvider).valueOrNull ?? [];
      for (final acc in accounts) {
        _ctrls[acc.walletType]?.text = acc.phoneNumber;
      }
      setState(() {});
    });
  }

  @override
  void dispose() {
    for (final c in _ctrls.values) { c.dispose(); }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: context.colors.navyText),
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: Text('My Wallet Accounts',
            style: TextStyle(fontWeight: FontWeight.w800, color: context.colors.navyText)),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const SizedBox(height: 4),
          Container(
            width: double.infinity, padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(color: const Color(0xFFF0F4FF), borderRadius: BorderRadius.circular(12)),
            child: const Text(
              'Save your wallet phone numbers here. They will be auto-filled when you exchange between wallets — no need to type them each time.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 13, color: Color(0xFF374151), height: 1.6),
            ),
          ),
          const SizedBox(height: 20),
          ..._wallets.map((w) => _buildWalletField(w)),
          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _saving ? null : _saveAll,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary, foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                padding: const EdgeInsets.symmetric(vertical: 16),
              ),
              child: Text(_saving ? 'Saving...' : 'Save All Accounts',
                  style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
            ),
          ),
          const SizedBox(height: 40),
        ]),
      ),
    );
  }

  Widget _buildWalletField(_WalletDef w) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          _WalletLogo(wallet: w, size: 24),
          const SizedBox(width: 8),
          Text(w.name, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText)),
        ]),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
          decoration: BoxDecoration(
            border: Border.all(color: Colors.grey.shade300),
            borderRadius: BorderRadius.circular(12),
          ),
          child: TextField(
            controller: _ctrls[w.id],
            keyboardType: TextInputType.phone,
            inputFormatters: [_PhonePrefixFormatter('+252 ')],
            style: TextStyle(fontWeight: FontWeight.w600, fontSize: 16, color: context.colors.navyText),
            decoration: InputDecoration(
              hintText: '+252 61 234 5678',
              hintStyle: TextStyle(color: Colors.grey.shade400, fontWeight: FontWeight.w400),
              border: InputBorder.none, isDense: true,
            ),
          ),
        ),
      ]),
    );
  }

  Future<void> _saveAll() async {
    setState(() => _saving = true);
    int saved = 0;
    for (final w in _wallets) {
      final phone = _ctrls[w.id]?.text.trim() ?? '';
      final clean = phone.replaceAll(RegExp(r'\s'), '');
      if (!clean.startsWith('+252') || clean.length < 10) continue;
      try {
        await _svc.addExchangeAccount({'wallet_type': w.id, 'phone_number': phone});
        saved++;
      } catch (_) {}
    }
    if (mounted) {
      setState(() => _saving = false);
      ref.invalidate(_exchangeAccountsProvider);
      if (saved > 0) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('$saved account${saved == 1 ? '' : 's'} saved!')));
      }
      Navigator.of(context).pop();
    }
  }
}

// ─── Phone Prefix Formatter ────────────────────────────────────────────────────

class _PhonePrefixFormatter extends TextInputFormatter {
  final String prefix;
  _PhonePrefixFormatter(this.prefix);
  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) {
    String text = newValue.text;
    if (!text.startsWith(prefix)) {
      text = prefix + text.replaceAll(RegExp(r'^\+252\s*'), '');
    }
    return newValue.copyWith(text: text, selection: TextSelection.collapsed(offset: text.length));
  }
}

// ─── Success Screen ────────────────────────────────────────────────────────────

class _ExchangeSuccessScreen extends StatelessWidget {
  final Map<String, dynamic> data;
  const _ExchangeSuccessScreen({required this.data});

  @override
  Widget build(BuildContext context) {
    final ref      = data['reference']        ?? '—';
    final from     = '${data['from_wallet'] ?? '—'}';
    final to       = '${data['to_wallet']   ?? '—'}';
    final sent     = data['sent_amount']      ?? data['amount']   ?? 0;
    final fee      = data['fee']              ?? 0;
    final received = data['converted_amount'] ?? data['converted'] ?? 0;
    final phone    = data['recipient_phone']  ?? '—';

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(children: [
            const SizedBox(height: 30),
            Container(width: 100, height: 100,
                decoration: BoxDecoration(color: const Color(0xFF2E7D32).withValues(alpha: 0.1), shape: BoxShape.circle),
                child: const Icon(Icons.check_circle_rounded, color: Color(0xFF2E7D32), size: 58)),
            const SizedBox(height: 20),
            Text('Order Submitted!',
                style: TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: context.colors.navyText)),
            const SizedBox(height: 8),
            const Text('Your exchange request has been received.\nWe\'ll process it shortly.',
                style: TextStyle(fontSize: 14, color: AppColors.textGrey, height: 1.6), textAlign: TextAlign.center),
            const SizedBox(height: 32),
            Container(
              width: double.infinity, padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 20),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [AppColors.primary, Color(0xFF2C3E8A)]),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Column(children: [
                const Text('Reference Number', style: TextStyle(color: Colors.white60, fontSize: 12)),
                const SizedBox(height: 6),
                GestureDetector(
                  onTap: () {
                    Clipboard.setData(ClipboardData(text: ref.toString()));
                    ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Reference copied!'), duration: Duration(seconds: 1)));
                  },
                  child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Text(ref.toString(), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 20, letterSpacing: 1.5)),
                    const SizedBox(width: 8),
                    const Icon(Icons.copy_rounded, color: Colors.white54, size: 18),
                  ]),
                ),
              ]),
            ),
            const SizedBox(height: 20),
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppColors.divider)),
              child: Column(children: [
                Align(alignment: Alignment.centerLeft,
                    child: Text('Exchange Details', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText))),
                const SizedBox(height: 14),
                _detailRow(context, 'From Wallet', from.toUpperCase(), icon: Icons.arrow_upward_rounded, color: Colors.red),
                _detailRow(context, 'To Wallet', to.toUpperCase(), icon: Icons.arrow_downward_rounded, color: const Color(0xFF2E7D32)),
                _detailRow(context, 'Amount Sent', '\$$sent'),
                _detailRow(context, 'Service Fee', '-\$$fee'),
                const Divider(height: 20),
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  Text('Recipient Receives', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: context.colors.navyText)),
                  Text('\$$received', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 22, color: Color(0xFF2E7D32))),
                ]),
              ]),
            ),
            const SizedBox(height: 16),
            Container(
              width: double.infinity, padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppColors.divider)),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Sending To', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText)),
                const SizedBox(height: 12),
                Row(children: [
                  _WalletLogo(wallet: _wallet(to), size: 48),
                  const SizedBox(width: 12),
                  Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(phone.toString(), style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17, color: context.colors.navyText)),
                    Text('$to Wallet', style: const TextStyle(color: AppColors.textGrey, fontSize: 12)),
                  ]),
                ]),
              ]),
            ),
            const SizedBox(height: 30),
            AppButton(label: 'Done', onPressed: () { while (context.canPop()) { context.pop(); } }),
            const SizedBox(height: 12),
            TextButton(
              onPressed: () => Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const EExchangeScreen())),
              child: const Text('New Exchange', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700)),
            ),
            const SizedBox(height: 20),
          ]),
        ),
      ),
    );
  }

  Widget _detailRow(BuildContext context, String label, String value, {IconData? icon, Color? color}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 7),
    child: Row(children: [
      if (icon != null) ...[Icon(icon, size: 16, color: color ?? AppColors.textGrey), const SizedBox(width: 8)],
      Expanded(child: Text(label, style: const TextStyle(color: AppColors.textGrey, fontSize: 13))),
      Text(value, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: color ?? AppColors.secondary)),
    ]),
  );
}
