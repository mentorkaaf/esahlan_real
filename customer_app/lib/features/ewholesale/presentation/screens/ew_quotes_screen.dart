import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/ew_provider.dart';
import '../ui/ew_theme.dart';
import '../ui/widgets/ew_widgets.dart';
import '../../data/models/ew_models.dart';

// ─── Quotes Inbox ─────────────────────────────────────────────────────────────

class EwQuotesScreen extends ConsumerWidget {
  const EwQuotesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final quotesAsync = ref.watch(ewQuotesProvider);

    return Scaffold(
      backgroundColor: EwTheme.bg,
      appBar: AppBar(
        backgroundColor: EwTheme.navy,
        foregroundColor: Colors.white,
        title: const Text('Quotes Inbox', style: TextStyle(fontWeight: FontWeight.w700)),
      ),
      body: RefreshIndicator(
        color: EwTheme.orange,
        onRefresh: () => ref.refresh(ewQuotesProvider.future),
        child: quotesAsync.when(
          loading: () => const Center(child: CircularProgressIndicator(color: EwTheme.orange)),
          error:   (e, _) => EwErrorRetry(error: e, onRetry: () => ref.refresh(ewQuotesProvider.future)),
          data:    (quotes) => quotes.isEmpty
            ? const EwEmptyState(icon: Icons.chat_bubble_outline, title: 'No quotes yet',
                subtitle: 'Send an inquiry from a product page to get quotes from suppliers')
            : ListView.builder(
                padding: const EdgeInsets.all(12),
                itemCount: quotes.length,
                itemBuilder: (_, i) => _QuoteListTile(quote: quotes[i]),
              ),
        ),
      ),
    );
  }
}

class _QuoteListTile extends StatelessWidget {
  final EwQuote quote;
  const _QuoteListTile({required this.quote});

  @override
  Widget build(BuildContext context) {
    final isUnread = quote.status == 'sent';
    final validHrs = quote.validUntil != null
      ? quote.validUntil!.difference(DateTime.now()).inHours
      : null;

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: EwTheme.surface,
        borderRadius: EwTheme.radius12,
        border: Border.all(color: isUnread ? EwTheme.orange.withOpacity(0.4) : EwTheme.border),
        boxShadow: EwTheme.cardShadow,
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: EwTheme.radius12,
        child: InkWell(
          borderRadius: EwTheme.radius12,
          onTap: () => context.push('/ewholesale/quote/${quote.id}'),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(children: [
              if (isUnread) Container(
                width: 8, height: 8,
                decoration: const BoxDecoration(color: EwTheme.orange, shape: BoxShape.circle),
                margin: const EdgeInsets.only(right: 8),
              ),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(
                  quote.supplier?.displayName ?? 'Supplier',
                  style: EwTheme.heading3.copyWith(fontSize: 14),
                ),
                const SizedBox(height: 2),
                Text(
                  '${quote.lines.length} item${quote.lines.length > 1 ? 's' : ''} · ${EwTheme.formatPrice(quote.total)}',
                  style: EwTheme.bodySmall,
                ),
                if (validHrs != null && validHrs > 0) ...[
                  const SizedBox(height: 2),
                  Text('Valid for ${validHrs}h', style: EwTheme.bodySmall.copyWith(
                    color: validHrs < 24 ? EwTheme.red : EwTheme.textMuted,
                  )),
                ],
              ])),
              Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                QuoteStatusChip(status: quote.status),
                const SizedBox(height: 4),
                const Icon(Icons.chevron_right, size: 16, color: EwTheme.textMuted),
              ]),
            ]),
          ),
        ),
      ),
    );
  }
}

// ─── Quote Detail Screen ──────────────────────────────────────────────────────

class EwQuoteDetailScreen extends ConsumerStatefulWidget {
  final int quoteId;
  const EwQuoteDetailScreen({super.key, required this.quoteId});

  @override
  ConsumerState<EwQuoteDetailScreen> createState() => _EwQuoteDetailScreenState();
}

class _EwQuoteDetailScreenState extends ConsumerState<EwQuoteDetailScreen> {
  bool _counterMode = false;
  final _noteCtrl = TextEditingController();
  final Map<int, TextEditingController> _priceCtrlMap = {};
  bool _loading = false;

  @override
  void dispose() { _noteCtrl.dispose(); for (final c in _priceCtrlMap.values) c.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final quoteAsync = ref.watch(ewQuoteDetailProvider(widget.quoteId));

    return Scaffold(
      backgroundColor: EwTheme.bg,
      appBar: AppBar(
        backgroundColor: EwTheme.navy,
        foregroundColor: Colors.white,
        title: const Text('Quote Detail', style: TextStyle(fontWeight: FontWeight.w700)),
      ),
      body: quoteAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: EwTheme.orange)),
        error:   (e, _) => EwErrorRetry(error: e, onRetry: () => ref.refresh(ewQuoteDetailProvider(widget.quoteId))),
        data:    (quote) => _buildContent(context, quote),
      ),
    );
  }

  Widget _buildContent(BuildContext context, EwQuote quote) {
    final canAct = quote.status == 'sent' || quote.status == 'countered';

    // Init price controllers
    for (final l in quote.lines) {
      _priceCtrlMap.putIfAbsent(l.productId, () =>
        TextEditingController(text: l.unitPrice.toStringAsFixed(2)));
    }

    return Stack(children: [
      ListView(padding: const EdgeInsets.fromLTRB(16, 16, 16, 120), children: [
        // ── Header ──────────────────────────────────────────────────────────
        _buildHeader(quote),
        const SizedBox(height: 12),
        // ── Lines ────────────────────────────────────────────────────────────
        _buildLines(quote),
        const SizedBox(height: 12),
        // ── Totals ───────────────────────────────────────────────────────────
        _buildTotals(quote),
        const SizedBox(height: 12),
        // ── Negotiation history ───────────────────────────────────────────────
        if (quote.negotiationChain.isNotEmpty) _buildHistory(quote),
        // ── Counter form ─────────────────────────────────────────────────────
        if (_counterMode) _buildCounterForm(quote),
      ]),
      // ── Action bar ───────────────────────────────────────────────────────
      if (canAct)
        Positioned(bottom: 0, left: 0, right: 0,
          child: _buildActionBar(context, quote)),
    ]);
  }

  Widget _buildHeader(EwQuote quote) {
    final s = quote.supplier;
    return Container(
      decoration: BoxDecoration(color: EwTheme.surface, borderRadius: EwTheme.radius12, border: Border.all(color: EwTheme.border)),
      padding: const EdgeInsets.all(14),
      child: Row(children: [
        if (s != null) ...[
          CircleAvatar(radius: 20, backgroundColor: EwTheme.navy.withOpacity(0.08),
            backgroundImage: s.logo != null ? NetworkImage(s.logo!) : null,
            child: s.logo == null ? Text(s.displayName.isNotEmpty ? s.displayName[0] : '?',
              style: const TextStyle(color: EwTheme.navy, fontWeight: FontWeight.w700)) : null),
          const SizedBox(width: 10),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(s.displayName, style: EwTheme.heading3),
            Row(children: [
              SupplierBadge(verification: s.verification, compact: true),
              const SizedBox(width: 8),
              Icon(Icons.star, size: 12, color: EwTheme.amber),
              Text(' ${s.rating.toStringAsFixed(1)}', style: EwTheme.bodySmall),
            ]),
          ])),
        ],
        QuoteStatusChip(status: quote.status),
      ]),
    );
  }

  Widget _buildLines(EwQuote quote) {
    return Container(
      decoration: BoxDecoration(color: EwTheme.surface, borderRadius: EwTheme.radius12, border: Border.all(color: EwTheme.border)),
      child: Column(children: quote.lines.asMap().entries.map((e) {
        final i = e.key;
        final l = e.value;
        return Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: BoxDecoration(
            border: i == 0 ? null : const Border(top: BorderSide(color: EwTheme.border)),
          ),
          child: Row(children: [
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(l.productName, style: EwTheme.heading3.copyWith(fontSize: 13)),
              Text('${l.qty.toInt()} ${l.unit}', style: EwTheme.bodySmall),
            ])),
            if (_counterMode)
              SizedBox(width: 90, child: TextField(
                controller: _priceCtrlMap[l.productId],
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(
                  prefixText: '\$',
                  isDense: true,
                  contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                  border: OutlineInputBorder(borderRadius: EwTheme.radius4),
                ),
              ))
            else
              Text(EwTheme.formatPrice(l.unitPrice), style: EwTheme.priceSmall),
          ]),
        );
      }).toList()),
    );
  }

  Widget _buildTotals(EwQuote quote) {
    return Container(
      decoration: BoxDecoration(color: EwTheme.surface, borderRadius: EwTheme.radius12, border: Border.all(color: EwTheme.border)),
      padding: const EdgeInsets.all(14),
      child: Column(children: [
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          const Text('Subtotal', style: TextStyle(color: EwTheme.textSecondary, fontSize: 14)),
          Text(EwTheme.formatPrice(quote.total), style: EwTheme.priceSmall),
        ]),
        if (quote.validUntil != null) ...[
          const SizedBox(height: 6),
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text('Valid until', style: EwTheme.bodySmall),
            Text(_fmtDate(quote.validUntil!), style: EwTheme.bodySmall.copyWith(fontWeight: FontWeight.w600)),
          ]),
        ],
      ]),
    );
  }

  Widget _buildHistory(EwQuote quote) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text('Negotiation History', style: EwTheme.heading3),
      const SizedBox(height: 8),
      ...quote.negotiationChain.map((h) => Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: (h['role'] == 'buyer') ? EwTheme.navy.withOpacity(0.04) : EwTheme.bg,
          borderRadius: EwTheme.radius8,
          border: Border.all(color: EwTheme.border),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Text(h['role'] == 'buyer' ? 'You' : (quote.supplier?.displayName ?? 'Supplier'),
              style: EwTheme.bodySmall.copyWith(fontWeight: FontWeight.w600)),
            const Spacer(),
            Text(h['created_at']?.toString() ?? '', style: EwTheme.bodySmall.copyWith(fontSize: 10)),
          ]),
          if (h['note'] != null) ...[const SizedBox(height: 4), Text(h['note'].toString(), style: EwTheme.body)],
        ]),
      )),
      const SizedBox(height: 12),
    ]);
  }

  Widget _buildCounterForm(EwQuote quote) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text('Your Counter Offer', style: EwTheme.heading3),
      const SizedBox(height: 8),
      TextField(
        controller: _noteCtrl,
        maxLines: 3,
        decoration: InputDecoration(
          hintText: 'Add a note (optional)…',
          border: OutlineInputBorder(borderRadius: EwTheme.radius8),
          focusedBorder: OutlineInputBorder(borderRadius: EwTheme.radius8, borderSide: const BorderSide(color: EwTheme.navy, width: 1.5)),
        ),
      ),
    ]);
  }

  Widget _buildActionBar(BuildContext context, EwQuote quote) {
    return Container(
      color: EwTheme.surface,
      padding: EdgeInsets.fromLTRB(16, 12, 16, MediaQuery.of(context).padding.bottom + 12),
      child: Row(children: [
        OutlinedButton(
          onPressed: _loading ? null : () => _decline(context, quote),
          style: OutlinedButton.styleFrom(
            foregroundColor: EwTheme.red, side: BorderSide(color: EwTheme.red),
            minimumSize: const Size(80, 44), shape: const RoundedRectangleBorder(borderRadius: EwTheme.radius8),
          ),
          child: const Text('Decline'),
        ),
        const SizedBox(width: 8),
        if (!_counterMode)
          OutlinedButton(
            onPressed: () => setState(() => _counterMode = true),
            style: EwTheme.secondaryButton.copyWith(minimumSize: const WidgetStatePropertyAll(Size(80, 44))),
            child: const Text('Counter'),
          )
        else
          ElevatedButton(
            onPressed: _loading ? null : () => _submitCounter(context, quote),
            style: EwTheme.secondaryButton.copyWith(
              minimumSize: const WidgetStatePropertyAll(Size(80, 44)),
              backgroundColor: WidgetStatePropertyAll(EwTheme.navy),
              foregroundColor: const WidgetStatePropertyAll(Colors.white),
            ),
            child: _loading ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
              : const Text('Send Counter'),
          ),
        const SizedBox(width: 8),
        Expanded(child: ElevatedButton(
          onPressed: _loading ? null : () => _accept(context, quote),
          style: EwTheme.primaryButton.copyWith(minimumSize: const WidgetStatePropertyAll(Size(0, 44))),
          child: _loading ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
            : const Text('Accept'),
        )),
      ]),
    );
  }

  Future<void> _accept(BuildContext context, EwQuote quote) async {
    setState(() => _loading = true);
    try {
      await ref.read(ewRepoProvider).acceptQuote(quote.id);
      ref.invalidate(ewQuotesProvider);
      if (mounted) context.push('/ewholesale/cart');
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: EwTheme.red));
    } finally { if (mounted) setState(() => _loading = false); }
  }

  Future<void> _decline(BuildContext context, EwQuote quote) async {
    await ref.read(ewRepoProvider).declineQuote(quote.id);
    ref.invalidate(ewQuotesProvider);
    if (mounted) context.pop();
  }

  Future<void> _submitCounter(BuildContext context, EwQuote quote) async {
    setState(() => _loading = true);
    try {
      final lines = quote.lines.map((l) => {
        'product_id': l.productId,
        'qty': l.qty,
        'proposed_price': double.tryParse(_priceCtrlMap[l.productId]?.text ?? '') ?? l.unitPrice,
      }).toList();
      await ref.read(ewRepoProvider).counterQuote(quote.id, lines: lines, note: _noteCtrl.text.trim().isEmpty ? null : _noteCtrl.text.trim());
      ref.invalidate(ewQuoteDetailProvider(widget.quoteId));
      if (mounted) { setState(() { _counterMode = false; _loading = false; }); }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: EwTheme.red));
      if (mounted) setState(() => _loading = false);
    }
  }

  String _fmtDate(DateTime d) => '${d.day}/${d.month}/${d.year}';
}
