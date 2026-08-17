import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/error_handler.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/wallet_pin_dialog.dart';
import '../../payment/waafi_pay_sheet.dart';
import '../../payment/mobile_pay_sheet.dart';
import '../../payment/payment_method_section.dart';
import '../../wallet/presentation/providers/wallet_provider.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../features/global/presentation/providers/global_provider.dart';
import 'crypto/crypto_exchange_screen.dart';

final _svc = ModuleApiService.create();
final _exchangeRatesProvider = FutureProvider((_) => _svc.getExchangeRates());
final _exchangeAccountsProvider = FutureProvider<List<_ExchangeAccount>>((ref) async {
  try {
    final res = await _svc.getExchangeAccounts();
    final list = res['data'] as List? ?? [];
    return list.map((e) => _ExchangeAccount.fromJson(e)).toList();
  } catch (_) {
    return [];
  }
});

// ── Model ─────────────────────────────────────────────────────────────────────

class _ExchangeAccount {
  final int id;
  final String walletType;
  final String phoneNumber;
  final String? label;

  const _ExchangeAccount({
    required this.id,
    required this.walletType,
    required this.phoneNumber,
    this.label,
  });

  factory _ExchangeAccount.fromJson(Map<String, dynamic> j) => _ExchangeAccount(
    id:          j['id'] as int,
    walletType:  j['wallet_type'] as String,
    phoneNumber: j['phone_number'] as String,
    label:       j['label'] as String?,
  );
}

// ── Wallet definitions ────────────────────────────────────────────────────────

class _WalletDef {
  final String id;
  final String name;
  final Color color;
  final String logo; // emoji shorthand
  const _WalletDef(this.id, this.name, this.color, this.logo);
}

const _wallets = [
  _WalletDef('evc',     'EVC Plus',       Color(0xFFE74C3C), '📱'),
  _WalletDef('edahab',  'eDahab',         Color(0xFF27AE60), '💚'),
  _WalletDef('jeep',    'Jeep Money',     Color(0xFF2980B9), '💳'),
  _WalletDef('premier', 'Premier Wallet', Color(0xFF8E44AD), '⭐'),
  _WalletDef('ebesa',   'eBesa',          Color(0xFFD35400), '🟠'),
];

_WalletDef _wallet(String id) => _wallets.firstWhere((w) => w.id == id, orElse: () => _wallets.first);

// ── Main Screen ───────────────────────────────────────────────────────────────

class EExchangeScreen extends ConsumerStatefulWidget {
  const EExchangeScreen({super.key});
  @override
  ConsumerState<EExchangeScreen> createState() => _EExchangeScreenState();
}

class _EExchangeScreenState extends ConsumerState<EExchangeScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabCtrl;

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

  @override
  Widget build(BuildContext context) {
    final cryptoEnabled = ref.watch(cryptoEnabledProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: context.colors.navyText),
          onPressed: () => context.pop(),
        ),
        title: Text('eExchange', style: TextStyle(fontWeight: FontWeight.w800, color: context.colors.navyText, fontFamily: 'Cairo')),
        bottom: TabBar(
          controller: _tabCtrl,
          tabs: [
            const Tab(text: 'Local'),
            Tab(
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                const Text('Crypto'),
                if (!cryptoEnabled) ...[
                  const SizedBox(width: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                    decoration: BoxDecoration(color: Colors.grey.shade400, borderRadius: BorderRadius.circular(4)),
                    child: const Text('OFF', style: TextStyle(fontSize: 8, fontWeight: FontWeight.w900, color: Colors.white)),
                  ),
                ],
              ]),
            ),
          ],
          labelStyle: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
        ),
      ),
      body: TabBarView(
        controller: _tabCtrl,
        children: [
          // ── Local Exchange ─────────────────────────────────────────
          const _LocalExchangeTab(),
          // ── Crypto Exchange ────────────────────────────────────────
          cryptoEnabled
              ? const CryptoExchangeScreen()
              : _CryptoDisabledScreen(),
        ],
      ),
    );
  }
}

// ── Crypto Disabled Screen ────────────────────────────────────────────────────

class _CryptoDisabledScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(40),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 80, height: 80,
          decoration: BoxDecoration(color: Colors.grey.shade100, shape: BoxShape.circle),
          child: Icon(Icons.lock_outline_rounded, size: 36, color: Colors.grey.shade400),
        ),
        const SizedBox(height: 20),
        Text('Crypto Exchange Unavailable',
            style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: context.colors.navyText),
            textAlign: TextAlign.center),
        const SizedBox(height: 8),
        Text('Crypto exchange is temporarily disabled.\nCheck back later.',
            style: const TextStyle(fontSize: 13, color: AppColors.textGrey, height: 1.5),
            textAlign: TextAlign.center),
      ]),
    ),
  );
}

// ── Local Exchange Tab ────────────────────────────────────────────────────────

class _LocalExchangeTab extends ConsumerStatefulWidget {
  const _LocalExchangeTab();
  @override
  ConsumerState<_LocalExchangeTab> createState() => _LocalExchangeTabState();
}

class _LocalExchangeTabState extends ConsumerState<_LocalExchangeTab> {
  String _fromWallet = 'evc';
  String _toWallet   = 'edahab';
  double _amount     = 0;
  String _paymentMethod = 'wallet';
  String? _waafiRef;
  Map<String, dynamic>? _preview;
  bool _converting = false;
  bool _confirming = false;

  // Recipient phone (auto-filled from saved accounts or manual)
  final _phoneCtrl  = TextEditingController(text: '+252 ');
  final _phoneFocus = FocusNode();
  final _amountCtrl = TextEditingController();

  @override
  void dispose() {
    _phoneCtrl.dispose();
    _phoneFocus.dispose();
    _amountCtrl.dispose();
    super.dispose();
  }

  List<_ExchangeAccount> get _accounts =>
      ref.read(_exchangeAccountsProvider).valueOrNull ?? [];

  _ExchangeAccount? _accountFor(String walletId) {
    try {
      return _accounts.firstWhere((a) => a.walletType == walletId);
    } catch (_) {
      return null;
    }
  }

  void _selectFromWallet(String id) {
    setState(() { _fromWallet = id; _preview = null; });
  }

  void _selectToWallet(String id) {
    setState(() {
      _toWallet = id; _preview = null;
      // Auto-fill phone if user has saved this wallet
      final acc = _accountFor(id);
      if (acc != null) {
        _phoneCtrl.text = acc.phoneNumber;
      } else {
        _phoneCtrl.text = '+252 ';
      }
    });
  }

  void _swapWallets() {
    setState(() {
      final tmp = _fromWallet; _fromWallet = _toWallet; _toWallet = tmp;
      _preview = null;
      final acc = _accountFor(_toWallet);
      _phoneCtrl.text = acc?.phoneNumber ?? '+252 ';
    });
  }

  String get _cleanPhone => _phoneCtrl.text.trim();
  bool get _phoneValid {
    final p = _cleanPhone.replaceAll(RegExp(r'\s'), '');
    return p.startsWith('+252') && p.length >= 10;
  }

  @override
  Widget build(BuildContext context) {
    final accountsAsync = ref.watch(_exchangeAccountsProvider);
    final ratesAsync    = ref.watch(_exchangeRatesProvider);
    final accounts      = accountsAsync.valueOrNull ?? [];

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(children: [

        // ── Hero ────────────────────────────────────────────────────
        Container(
          width: double.infinity, padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: const LinearGradient(colors: [AppColors.primary, Color(0xFF2C3E8A)]),
            borderRadius: BorderRadius.circular(18),
          ),
          child: Row(children: [
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Digital Wallet Exchange', style: TextStyle(color: Colors.white70, fontSize: 13)),
              const SizedBox(height: 4),
              const Text('Transfer between wallets instantly',
                  style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.2), borderRadius: BorderRadius.circular(20)),
                child: const Text('1% Service Fee', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
              ),
            ])),
            GestureDetector(
              onTap: () => _showAddAccountSheet(context, accounts),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.2), borderRadius: BorderRadius.circular(12)),
                child: Column(children: [
                  const Icon(Icons.account_balance_wallet_rounded, color: Colors.white, size: 22),
                  const SizedBox(height: 4),
                  Text(accounts.isEmpty ? 'Add\nAccounts' : 'My\nAccounts',
                      style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700),
                      textAlign: TextAlign.center),
                  if (accounts.isNotEmpty)
                    Container(
                      margin: const EdgeInsets.only(top: 4),
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(8)),
                      child: Text('${accounts.length}', style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w900, color: AppColors.primary)),
                    ),
                ]),
              ),
            ),
          ]),
        ),
        const SizedBox(height: 20),

        // ── Saved accounts bar ───────────────────────────────────────
        if (accounts.isNotEmpty) ...[
          _buildAccountsBar(accounts),
          const SizedBox(height: 20),
        ],

        // ── FROM wallet ──────────────────────────────────────────────
        Align(alignment: Alignment.centerLeft,
            child: Text('From Wallet', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText))),
        const SizedBox(height: 10),
        _buildWalletRow(_fromWallet, _selectFromWallet, accounts),
        const SizedBox(height: 16),

        // ── Swap button ──────────────────────────────────────────────
        Center(child: GestureDetector(
          onTap: _swapWallets,
          child: Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: AppColors.primary, shape: BoxShape.circle,
              boxShadow: [BoxShadow(color: AppColors.primary.withValues(alpha: 0.3), blurRadius: 10)],
            ),
            child: const Icon(Icons.swap_vert_rounded, color: Colors.white, size: 24),
          ),
        )),
        const SizedBox(height: 16),

        // ── TO wallet ────────────────────────────────────────────────
        Align(alignment: Alignment.centerLeft,
            child: Text('To Wallet', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText))),
        const SizedBox(height: 10),
        _buildWalletRow(_toWallet, _selectToWallet, accounts),
        const SizedBox(height: 20),

        // ── Amount ───────────────────────────────────────────────────
        _buildAmountCard(),
        const SizedBox(height: 14),

        // ── Recipient Phone ──────────────────────────────────────────
        _buildPhoneCard(accounts),
        const SizedBox(height: 16),

        // ── Live Rates ────────────────────────────────────────────────
        ratesAsync.when(
          loading: () => const SizedBox(),
          error: (_, __) => const SizedBox(),
          data: (res) {
            final rates = res['data'] as List? ?? [];
            if (rates.isEmpty) return const SizedBox();
            return Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.divider)),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Live Rates', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText)),
                const SizedBox(height: 8),
                ...rates.take(4).map((r) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 3),
                  child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                    Text('${r['from_wallet']?.toString().toUpperCase()} → ${r['to_wallet']?.toString().toUpperCase()}',
                        style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
                    Text('Rate: ${r['rate']}',
                        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: context.colors.navyText)),
                  ]),
                )),
              ]),
            );
          },
        ),
        const SizedBox(height: 16),

        // ── Preview / Confirm ─────────────────────────────────────────
        if (_preview != null) ...[
          _buildPreviewCard(),
          const SizedBox(height: 16),
          _buildPaymentMethod(),
          const SizedBox(height: 14),
          AppButton(
            label: _confirming ? 'Processing...' : 'Confirm & Pay',
            isLoading: _confirming,
            onPressed: _fromWallet == _toWallet ? null : _confirm,
          ),
        ] else ...[
          AppButton(
            label: _converting ? 'Calculating...' : 'Preview Exchange',
            isLoading: _converting,
            outlined: true,
            onPressed: (_amount <= 0 || _fromWallet == _toWallet || !_phoneValid) ? null : _previewExchange,
          ),
          if (_fromWallet == _toWallet)
            _hint('Select different wallets', isError: true)
          else if (_amount > 0 && !_phoneValid)
            _hint('Enter a valid phone number (e.g. +252 61 234 5678)', isError: true),
        ],
        const SizedBox(height: 40),
      ]),
    );
  }

  // ── Saved accounts horizontal bar ─────────────────────────────────────────

  Widget _buildAccountsBar(List<_ExchangeAccount> accounts) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Text('My Saved Accounts', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: context.colors.navyText)),
        GestureDetector(
          onTap: () => _showAddAccountSheet(context, accounts),
          child: Text('Manage', style: const TextStyle(fontSize: 12, color: AppColors.primary, fontWeight: FontWeight.w700)),
        ),
      ]),
      const SizedBox(height: 10),
      SizedBox(
        height: 72,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          itemCount: accounts.length,
          separatorBuilder: (_, __) => const SizedBox(width: 10),
          itemBuilder: (_, i) {
            final acc = accounts[i];
            final w = _wallet(acc.walletType);
            return GestureDetector(
              onTap: () {
                // Tap saved account → set as "from" or "to"
                if (_fromWallet == acc.walletType) {
                  _selectToWallet(acc.walletType);
                } else {
                  _selectFromWallet(acc.walletType);
                }
              },
              child: Container(
                width: 90,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: context.colors.cardBg,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: (_fromWallet == acc.walletType || _toWallet == acc.walletType)
                        ? w.color : AppColors.divider,
                    width: (_fromWallet == acc.walletType || _toWallet == acc.walletType) ? 2 : 1,
                  ),
                ),
                child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Text(w.logo, style: const TextStyle(fontSize: 18)),
                  const SizedBox(height: 4),
                  Text(w.name.split(' ').first, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: w.color), overflow: TextOverflow.ellipsis),
                  Text(acc.phoneNumber.length > 10 ? acc.phoneNumber.substring(acc.phoneNumber.length - 6) : acc.phoneNumber,
                      style: const TextStyle(fontSize: 9, color: AppColors.textGrey)),
                ]),
              ),
            );
          },
        ),
      ),
    ]);
  }

  Widget _buildWalletRow(String selected, void Function(String) onSelect, List<_ExchangeAccount> accounts) {
    return Row(children: _wallets.map((w) {
      final sel = selected == w.id;
      final hasSaved = accounts.any((a) => a.walletType == w.id);
      return Expanded(child: GestureDetector(
        onTap: () => onSelect(w.id),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          margin: const EdgeInsets.only(right: 8),
          padding: const EdgeInsets.symmetric(vertical: 10),
          decoration: BoxDecoration(
            color: sel ? w.color.withValues(alpha: 0.12) : context.colors.cardBg,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: sel ? w.color : AppColors.divider, width: sel ? 2 : 1),
          ),
          child: Column(children: [
            Text(w.logo, style: const TextStyle(fontSize: 18)),
            const SizedBox(height: 3),
            Text(w.name.split(' ').first, style: TextStyle(fontSize: 9, fontWeight: FontWeight.w700, color: sel ? w.color : AppColors.textGrey), overflow: TextOverflow.ellipsis),
            if (hasSaved)
              Container(
                margin: const EdgeInsets.only(top: 3),
                width: 6, height: 6,
                decoration: BoxDecoration(color: w.color, shape: BoxShape.circle),
              ),
          ]),
        ),
      ));
    }).toList());
  }

  Widget _buildAmountCard() => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.divider)),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('Amount to Send', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.textGrey)),
      const SizedBox(height: 8),
      TextField(
        controller: _amountCtrl,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        style: TextStyle(fontWeight: FontWeight.w900, fontSize: 28, color: context.colors.navyText),
        onChanged: (v) => setState(() { _amount = double.tryParse(v) ?? 0; _preview = null; }),
        decoration: InputDecoration(
          hintText: '0.00',
          hintStyle: const TextStyle(fontWeight: FontWeight.w300, fontSize: 28, color: AppColors.divider),
          border: InputBorder.none,
          prefixText: '\$  ',
          prefixStyle: TextStyle(fontWeight: FontWeight.w900, fontSize: 28, color: context.colors.navyText),
        ),
      ),
      Wrap(spacing: 8, children: [10, 25, 50, 100, 200].map((v) => GestureDetector(
        onTap: () { _amountCtrl.text = v.toString(); setState(() { _amount = v.toDouble(); _preview = null; }); },
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(color: context.colors.surfaceBg, borderRadius: BorderRadius.circular(8), border: Border.all(color: AppColors.divider)),
          child: Text('\$$v', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: context.colors.navyText)),
        ),
      )).toList()),
    ]),
  );

  Widget _buildPhoneCard(List<_ExchangeAccount> accounts) {
    final toWallet = _wallet(_toWallet);
    final savedAcc = _accountFor(_toWallet);
    final focusColor = _phoneFocus.hasFocus ? AppColors.primary : AppColors.divider;

    return AnimatedContainer(
      duration: const Duration(milliseconds: 150),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: context.colors.cardBg, borderRadius: BorderRadius.circular(14),
        border: Border.all(color: focusColor, width: _phoneFocus.hasFocus ? 2 : 1),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(
            padding: const EdgeInsets.all(7),
            decoration: BoxDecoration(color: toWallet.color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(8)),
            child: Icon(Icons.phone_rounded, color: toWallet.color, size: 18),
          ),
          const SizedBox(width: 10),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${toWallet.name} Phone Number',
                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: context.colors.navyText)),
            const Text('Recipient who will receive the transfer',
                style: TextStyle(fontSize: 11, color: AppColors.textGrey)),
          ])),
          if (savedAcc != null)
            GestureDetector(
              onTap: () => setState(() { _phoneCtrl.text = savedAcc.phoneNumber; _preview = null; }),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(color: toWallet.color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
                child: Text('Use Saved', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: toWallet.color)),
              ),
            ),
        ]),
        const SizedBox(height: 12),

        // Quick select from saved accounts
        if (accounts.isNotEmpty) ...[
          Wrap(spacing: 6, children: accounts.where((a) => a.walletType == _toWallet).map((acc) =>
            GestureDetector(
              onTap: () => setState(() { _phoneCtrl.text = acc.phoneNumber; _preview = null; }),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: toWallet.color.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: toWallet.color.withValues(alpha: 0.3)),
                ),
                child: Row(mainAxisSize: MainAxisSize.min, children: [
                  Text(toWallet.logo, style: const TextStyle(fontSize: 12)),
                  const SizedBox(width: 4),
                  Text(acc.phoneNumber, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: toWallet.color)),
                ]),
              ),
            ),
          ).toList()),
          const SizedBox(height: 8),
        ],

        TextField(
          controller: _phoneCtrl,
          focusNode: _phoneFocus,
          keyboardType: TextInputType.phone,
          inputFormatters: [_PhonePrefixFormatter('+252 ')],
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 22, color: context.colors.navyText, letterSpacing: 1),
          onChanged: (_) => setState(() => _preview = null),
          onTap: () { setState(() {}); _phoneCtrl.selection = TextSelection.fromPosition(TextPosition(offset: _phoneCtrl.text.length)); },
          decoration: const InputDecoration(
            hintText: '+252 61 234 5678',
            hintStyle: TextStyle(fontSize: 20, color: AppColors.divider, fontWeight: FontWeight.w400, letterSpacing: .5),
            border: InputBorder.none,
          ),
        ),

        if (_cleanPhone.length > 5)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Row(children: [
              Icon(_phoneValid ? Icons.check_circle_rounded : Icons.info_outline_rounded,
                  size: 14, color: _phoneValid ? AppColors.success : AppColors.textGrey),
              const SizedBox(width: 6),
              Text(_phoneValid ? '✓ Valid number' : 'Enter remaining digits',
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600,
                      color: _phoneValid ? AppColors.success : AppColors.textGrey)),
            ]),
          ),
      ]),
    );
  }

  Widget _buildPreviewCard() => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      gradient: const LinearGradient(colors: [AppColors.secondary, Color(0xFF1A1060)]),
      borderRadius: BorderRadius.circular(16),
    ),
    child: Column(children: [
      Row(children: [
        const Icon(Icons.receipt_long_rounded, color: Colors.white70, size: 18),
        const SizedBox(width: 8),
        const Text('Exchange Summary', style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w700)),
      ]),
      const SizedBox(height: 14),
      _row('From',     '${(_preview!['from_wallet'] ?? _preview!['from'])?.toString().toUpperCase()}', white: true),
      _row('To',       '${(_preview!['to_wallet']   ?? _preview!['to'])?.toString().toUpperCase()}',   white: true),
      _row('You Send', '\$${_preview!['amount']}',   white: true),
      _row('Rate',     '${_preview!['rate']}',        white: true),
      _row('Fee (1%)', '-\$${_preview!['fee']}',     white: true),
      const SizedBox(height: 4),
      const Divider(color: Colors.white24, height: 1),
      const SizedBox(height: 12),
      Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        const Text('Recipient Gets', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
        Text('\$${_preview!['converted_amount'] ?? _preview!['converted'] ?? _preview!['you_receive']}',
            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 28)),
      ]),
      const SizedBox(height: 14),
      const Divider(color: Colors.white24, height: 1),
      const SizedBox(height: 12),
      Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10), border: Border.all(color: Colors.white24)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Sending to', style: TextStyle(color: Colors.white60, fontSize: 11)),
          const SizedBox(height: 6),
          Row(children: [
            const Text('📱', style: TextStyle(fontSize: 18)),
            const SizedBox(width: 8),
            Expanded(child: Text(_cleanPhone, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18))),
          ]),
          const SizedBox(height: 2),
          Text(_wallet(_toWallet).name, style: const TextStyle(color: Colors.white60, fontSize: 11)),
        ]),
      ),
    ]),
  );

  Widget _buildPaymentMethod() => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(14), border: Border.all(color: AppColors.divider)),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Row(children: [
        Icon(Icons.payment_rounded, size: 18, color: AppColors.primary),
        SizedBox(width: 8),
        Text('Payment Method', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
      ]),
      const SizedBox(height: 12),
      PaymentMethodSection(selected: _paymentMethod, onChanged: (v) => setState(() { _paymentMethod = v; _waafiRef = null; })),
    ]),
  );

  Widget _row(String label, String value, {bool white = false}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: TextStyle(color: white ? Colors.white70 : AppColors.textGrey, fontSize: 13)),
      Text(value,  style: TextStyle(color: white ? Colors.white : context.colors.navyText, fontWeight: FontWeight.w700, fontSize: 13)),
    ]),
  );

  Widget _hint(String text, {bool isError = false}) => Padding(
    padding: const EdgeInsets.only(top: 10),
    child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
      Icon(isError ? Icons.info_outline : Icons.check_circle_outline, size: 14, color: isError ? AppColors.error : AppColors.success),
      const SizedBox(width: 6),
      Flexible(child: Text(text, style: TextStyle(color: isError ? AppColors.error : AppColors.success, fontSize: 12), textAlign: TextAlign.center)),
    ]),
  );

  // ── Add Account Sheet ──────────────────────────────────────────────────────

  void _showAddAccountSheet(BuildContext ctx, List<_ExchangeAccount> accounts) {
    showModalBottomSheet(
      context: ctx,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _AddAccountSheet(
        accounts: accounts,
        onChanged: () => ref.invalidate(_exchangeAccountsProvider),
      ),
    );
  }

  // ── Logic ──────────────────────────────────────────────────────────────────

  Future<void> _previewExchange() async {
    if (!_phoneValid) { _phoneFocus.requestFocus(); _showErrorDialog('Please enter a valid Somalia phone number starting with +252'); return; }
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
    if (_paymentMethod == 'mobile_pay') {
      final result = await showMobilePaySheet(context, amount: _amount, description: 'eExchange ${_fromWallet.toUpperCase()} → ${_toWallet.toUpperCase()}');
      if (result?.success != true) return;
      _waafiRef = result!.account != null ? 'mobile_pay_${result.account!.id}' : 'mobile_pay';
    } else if (_paymentMethod == 'waafi_pay') {
      if (!mounted) return;
      final result = await showWaafiPaySheet(context, amount: _amount, type: 'order', description: 'eExchange', prefillPhone: _cleanPhone);
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
        'amount': _amount, 'recipient_phone': _cleanPhone,
        'payment_method': _paymentMethod,
        if (_waafiRef != null) 'payment_reference': _waafiRef,
      });
      if (mounted) {
        final data = Map<String, dynamic>.from(res['data'] ?? {});
        if (_paymentMethod == 'wallet') ref.invalidate(walletProvider);
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
          Container(width: 64, height: 64, decoration: BoxDecoration(color: AppColors.error.withValues(alpha: 0.1), shape: BoxShape.circle),
              child: const Icon(Icons.error_outline_rounded, color: AppColors.error, size: 36)),
          const SizedBox(height: 16),
          Text('Something went wrong', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17, color: context.colors.navyText), textAlign: TextAlign.center),
          const SizedBox(height: 10),
          Text(message, style: const TextStyle(color: AppColors.textGrey, fontSize: 13, height: 1.5), textAlign: TextAlign.center),
          const SizedBox(height: 22),
          SizedBox(width: double.infinity, child: ElevatedButton(
            onPressed: () => Navigator.of(context).pop(),
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary, foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)), padding: const EdgeInsets.symmetric(vertical: 14)),
            child: const Text('OK', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
          )),
        ]),
      ),
    ));
  }
}

// ── Add / Manage Accounts Sheet ───────────────────────────────────────────────

class _AddAccountSheet extends StatefulWidget {
  final List<_ExchangeAccount> accounts;
  final VoidCallback onChanged;
  const _AddAccountSheet({required this.accounts, required this.onChanged});

  @override
  State<_AddAccountSheet> createState() => _AddAccountSheetState();
}

class _AddAccountSheetState extends State<_AddAccountSheet> {
  String _selectedWallet = 'evc';
  final _phoneCtrl = TextEditingController(text: '+252 ');
  bool _saving = false;
  late List<_ExchangeAccount> _accounts;

  @override
  void initState() {
    super.initState();
    _accounts = List.from(widget.accounts);
  }

  @override
  void dispose() { _phoneCtrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final bottomPad = MediaQuery.of(context).viewInsets.bottom;
    return Container(
      margin: EdgeInsets.only(bottom: bottomPad),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        // Handle
        Container(margin: const EdgeInsets.only(top: 12, bottom: 8), width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
        // Title
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
          child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            const Text('My Wallet Accounts', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF07003B))),
            IconButton(onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close_rounded)),
          ]),
        ),
        const Divider(height: 1),
        // Saved list
        if (_accounts.isNotEmpty) ...[
          Flexible(
            child: ListView.builder(
              shrinkWrap: true,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              itemCount: _accounts.length,
              itemBuilder: (_, i) {
                final acc = _accounts[i];
                final w = _wallet(acc.walletType);
                return Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE5E7EB)),
                  ),
                  child: Row(children: [
                    Container(
                      width: 40, height: 40, decoration: BoxDecoration(color: w.color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
                      child: Center(child: Text(w.logo, style: const TextStyle(fontSize: 20))),
                    ),
                    const SizedBox(width: 12),
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(w.name, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: Color(0xFF111827))),
                      Text(acc.phoneNumber, style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280))),
                    ])),
                    GestureDetector(
                      onTap: () async {
                        try {
                          await _svc.removeExchangeAccount(acc.id);
                          setState(() => _accounts.removeAt(i));
                          widget.onChanged();
                        } catch (_) {}
                      },
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                        decoration: BoxDecoration(color: const Color(0xFFFEF2F2), borderRadius: BorderRadius.circular(8), border: Border.all(color: const Color(0xFFFECACA))),
                        child: const Text('Remove', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFFEF4444))),
                      ),
                    ),
                  ]),
                );
              },
            ),
          ),
          const Divider(height: 1),
        ],
        // Add new
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Add New Account', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: Color(0xFF374151))),
            const SizedBox(height: 12),
            // Wallet selector
            Row(children: _wallets.map((w) {
              final sel = _selectedWallet == w.id;
              return Expanded(child: GestureDetector(
                onTap: () => setState(() => _selectedWallet = w.id),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 150),
                  margin: const EdgeInsets.only(right: 6),
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  decoration: BoxDecoration(
                    color: sel ? w.color.withValues(alpha: 0.1) : const Color(0xFFF9FAFB),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: sel ? w.color : const Color(0xFFE5E7EB), width: sel ? 2 : 1),
                  ),
                  child: Column(children: [
                    Text(w.logo, style: const TextStyle(fontSize: 16)),
                    const SizedBox(height: 2),
                    Text(w.name.split(' ').first, style: TextStyle(fontSize: 8, fontWeight: FontWeight.w700, color: sel ? w.color : const Color(0xFF9CA3AF)), overflow: TextOverflow.ellipsis),
                  ]),
                ),
              ));
            }).toList()),
            const SizedBox(height: 12),
            // Phone field
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
              decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFE5E7EB))),
              child: TextField(
                controller: _phoneCtrl,
                keyboardType: TextInputType.phone,
                inputFormatters: [_PhonePrefixFormatter('+252 ')],
                style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 18, color: Color(0xFF111827)),
                decoration: const InputDecoration(hintText: '+252 61 234 5678', border: InputBorder.none,
                    hintStyle: TextStyle(fontWeight: FontWeight.w400, fontSize: 16, color: Color(0xFFD1D5DB))),
              ),
            ),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _saving ? null : _saveAccount,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary, foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                child: Text(_saving ? 'Saving...' : 'Save Account', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
              ),
            ),
          ]),
        ),
      ]),
    );
  }

  Future<void> _saveAccount() async {
    final phone = _phoneCtrl.text.trim();
    final cleanPhone = phone.replaceAll(RegExp(r'\s'), '');
    if (!cleanPhone.startsWith('+252') || cleanPhone.length < 10) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Enter a valid +252 phone number')));
      return;
    }
    setState(() => _saving = true);
    try {
      final res = await _svc.addExchangeAccount({'wallet_type': _selectedWallet, 'phone_number': phone});
      final saved = _ExchangeAccount.fromJson(res['data']);
      setState(() {
        _accounts.removeWhere((a) => a.walletType == saved.walletType);
        _accounts.add(saved);
        _phoneCtrl.text = '+252 ';
      });
      widget.onChanged();
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('${_wallet(_selectedWallet).name} account saved!')));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AppErrorHandler.message(e))));
    } finally {
      if (mounted) setState(() => _saving = false);
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
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(children: [
            const SizedBox(height: 30),
            Container(width: 100, height: 100, decoration: BoxDecoration(color: AppColors.success.withValues(alpha: 0.12), shape: BoxShape.circle),
                child: const Icon(Icons.check_circle_rounded, color: AppColors.success, size: 58)),
            const SizedBox(height: 20),
            Text('Order Submitted!', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: context.colors.navyText)),
            const SizedBox(height: 8),
            const Text('Your exchange request has been received.\nWe\'ll process it shortly.',
                style: TextStyle(fontSize: 14, color: AppColors.textGrey, height: 1.6), textAlign: TextAlign.center),
            const SizedBox(height: 32),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 20),
              decoration: BoxDecoration(gradient: const LinearGradient(colors: [AppColors.primary, Color(0xFF2C3E8A)]), borderRadius: BorderRadius.circular(14)),
              child: Column(children: [
                const Text('Reference Number', style: TextStyle(color: Colors.white60, fontSize: 12, fontWeight: FontWeight.w600)),
                const SizedBox(height: 6),
                GestureDetector(
                  onTap: () {
                    Clipboard.setData(ClipboardData(text: ref.toString()));
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Reference copied!'), duration: Duration(seconds: 1)));
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
              decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.divider)),
              child: Column(children: [
                Align(alignment: Alignment.centerLeft,
                    child: Text('Exchange Details', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText))),
                const SizedBox(height: 14),
                _detailRow(context, 'From Wallet', from, icon: Icons.arrow_upward_rounded, color: Colors.red),
                _detailRow(context, 'To Wallet',   to,   icon: Icons.arrow_downward_rounded, color: AppColors.success),
                _detailRow(context, 'Amount Sent',  '\$$sent'),
                _detailRow(context, 'Service Fee',  '-\$$fee'),
                const Divider(height: 20),
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  Text('Recipient Receives', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: context.colors.navyText)),
                  Text('\$$received', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 22, color: AppColors.success)),
                ]),
              ]),
            ),
            const SizedBox(height: 16),
            Container(
              width: double.infinity, padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.divider)),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Sending To', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText)),
                const SizedBox(height: 12),
                Row(children: [
                  Container(width: 48, height: 48, decoration: BoxDecoration(color: AppColors.primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
                      child: const Center(child: Text('📱', style: TextStyle(fontSize: 24)))),
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
