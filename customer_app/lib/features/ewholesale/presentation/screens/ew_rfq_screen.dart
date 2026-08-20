import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/ew_provider.dart';
import '../ui/ew_theme.dart';
import '../ui/widgets/ew_widgets.dart';
import '../../data/models/ew_models.dart';

// ─── RFQ Create Screen ────────────────────────────────────────────────────────

class EwRfqCreateScreen extends ConsumerStatefulWidget {
  const EwRfqCreateScreen({super.key});

  @override
  ConsumerState<EwRfqCreateScreen> createState() => _EwRfqCreateScreenState();
}

class _EwRfqCreateScreenState extends ConsumerState<EwRfqCreateScreen> {
  final _formKey  = GlobalKey<FormState>();
  final _titleCtrl = TextEditingController();
  final _qtyCtrl   = TextEditingController();
  final _priceCtrl = TextEditingController();
  String _unit = 'carton';
  int?   _categoryId;
  String? _categoryName;
  DateTime? _neededBy;
  bool _loading = false;

  static const _units = ['piece','carton','bag','kg','sack','pallet','box','set'];

  @override
  void dispose() {
    _titleCtrl.dispose(); _qtyCtrl.dispose(); _priceCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final catsAsync = ref.watch(ewCategoriesProvider);
    return Scaffold(
      backgroundColor: EwTheme.bg,
      appBar: AppBar(
        backgroundColor: EwTheme.navy,
        foregroundColor: Colors.white,
        title: const Text('Post RFQ', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
      ),
      body: Form(
        key: _formKey,
        child: ListView(padding: const EdgeInsets.all(16), children: [
          _card(children: [
            _label('Category *'),
            const SizedBox(height: 6),
            catsAsync.when(
              loading: () => const ShimmerBox.fill(height: 44),
              error:   (_, __) => const Text('Could not load categories'),
              data:    (cats) => DropdownButtonFormField<int>(
                value: _categoryId,
                decoration: _inputDeco('Select category'),
                items: cats.map((c) => DropdownMenuItem(value: c.id, child: Text(c.name))).toList(),
                validator: (v) => v == null ? 'Required' : null,
                onChanged: (v) => setState(() {
                  _categoryId   = v;
                  _categoryName = cats.where((c) => c.id == v).firstOrNull?.name;
                }),
              ),
            ),
          ]),
          const SizedBox(height: 12),
          _card(children: [
            _label('What are you looking for? *'),
            const SizedBox(height: 6),
            TextFormField(
              controller: _titleCtrl,
              decoration: _inputDeco('e.g. Rice 25kg bags, Grade A'),
              validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
            ),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                _label('Quantity *'),
                const SizedBox(height: 6),
                TextFormField(
                  controller: _qtyCtrl,
                  keyboardType: TextInputType.number,
                  decoration: _inputDeco('500'),
                  validator: (v) {
                    final n = double.tryParse(v ?? '');
                    if (n == null || n <= 0) return 'Enter valid qty';
                    return null;
                  },
                ),
              ])),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                _label('Unit *'),
                const SizedBox(height: 6),
                DropdownButtonFormField<String>(
                  value: _unit,
                  decoration: _inputDeco(''),
                  items: _units.map((u) => DropdownMenuItem(value: u, child: Text(u))).toList(),
                  onChanged: (v) => setState(() => _unit = v ?? _unit),
                ),
              ])),
            ]),
          ]),
          const SizedBox(height: 12),
          _card(children: [
            _label('Target Price per unit (optional)'),
            const SizedBox(height: 6),
            TextFormField(
              controller: _priceCtrl,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: _inputDeco('\$0.00'),
            ),
            const SizedBox(height: 12),
            _label('Needed by (optional)'),
            const SizedBox(height: 6),
            GestureDetector(
              onTap: _pickDate,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 13),
                decoration: BoxDecoration(
                  border: Border.all(color: EwTheme.border),
                  borderRadius: EwTheme.radius8,
                ),
                child: Row(children: [
                  const Icon(Icons.calendar_today_outlined, size: 16, color: EwTheme.textSecondary),
                  const SizedBox(width: 8),
                  Text(
                    _neededBy != null ? '${_neededBy!.year}-${_neededBy!.month.toString().padLeft(2,'0')}-${_neededBy!.day.toString().padLeft(2,'0')}' : 'Select date',
                    style: EwTheme.body.copyWith(color: _neededBy != null ? EwTheme.textPrimary : EwTheme.textMuted),
                  ),
                ]),
              ),
            ),
          ]),
          const SizedBox(height: 20),
          ElevatedButton(
            style: EwTheme.primaryButton,
            onPressed: _loading ? null : _submit,
            child: _loading
              ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
              : const Text('Post RFQ'),
          ),
          const SizedBox(height: 8),
          Text('Suppliers will respond within ${14} days',
            style: EwTheme.bodySmall, textAlign: TextAlign.center),
        ]),
      ),
    );
  }

  Future<void> _pickDate() async {
    final d = await showDatePicker(
      context: context,
      initialDate: DateTime.now().add(const Duration(days: 7)),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 180)),
      builder: (ctx, child) => Theme(
        data: Theme.of(ctx).copyWith(colorScheme: const ColorScheme.light(primary: EwTheme.navy)),
        child: child!,
      ),
    );
    if (d != null) setState(() => _neededBy = d);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _loading = true);
    try {
      final repo = ref.read(ewRepoProvider);
      await repo.createRfq(
        categoryId:  _categoryId!,
        title:       _titleCtrl.text.trim(),
        qty:         double.parse(_qtyCtrl.text.trim()),
        unit:        _unit,
        targetPrice: _priceCtrl.text.trim().isEmpty ? null : double.tryParse(_priceCtrl.text.trim()),
        neededBy:    _neededBy,
      );
      ref.invalidate(ewRfqsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('RFQ posted! Suppliers will respond soon.'), backgroundColor: EwTheme.green));
        context.pop();
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: EwTheme.red));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Widget _card({required List<Widget> children}) => Container(
    decoration: BoxDecoration(color: EwTheme.surface, borderRadius: EwTheme.radius12,
      boxShadow: EwTheme.cardShadow, border: Border.all(color: EwTheme.border)),
    padding: const EdgeInsets.all(16),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: children),
  );

  InputDecoration _inputDeco(String hint) => InputDecoration(
    hintText: hint,
    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
    border: OutlineInputBorder(borderRadius: EwTheme.radius8, borderSide: const BorderSide(color: EwTheme.border)),
    enabledBorder: OutlineInputBorder(borderRadius: EwTheme.radius8, borderSide: const BorderSide(color: EwTheme.border)),
    focusedBorder: OutlineInputBorder(borderRadius: EwTheme.radius8, borderSide: const BorderSide(color: EwTheme.navy, width: 1.5)),
  );

  Widget _label(String t) => Text(t, style: EwTheme.label);
}

// ─── My RFQs Screen ──────────────────────────────────────────────────────────

class EwMyRfqsScreen extends ConsumerWidget {
  const EwMyRfqsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final rfqsAsync = ref.watch(ewRfqsProvider);

    return Scaffold(
      backgroundColor: EwTheme.bg,
      appBar: AppBar(
        backgroundColor: EwTheme.navy,
        foregroundColor: Colors.white,
        title: const Text('My RFQs', style: TextStyle(fontWeight: FontWeight.w700)),
        actions: [
          TextButton.icon(
            onPressed: () => context.push('/ewholesale/rfq/create'),
            icon: const Icon(Icons.add, color: EwTheme.orange),
            label: const Text('Post RFQ', style: TextStyle(color: EwTheme.orange)),
          ),
        ],
      ),
      body: RefreshIndicator(
        color: EwTheme.orange,
        onRefresh: () => ref.refresh(ewRfqsProvider.future),
        child: rfqsAsync.when(
          loading: () => const Center(child: CircularProgressIndicator(color: EwTheme.orange)),
          error:   (e, _) => EwErrorRetry(error: e, onRetry: () => ref.refresh(ewRfqsProvider.future)),
          data:    (rfqs) => rfqs.isEmpty
            ? EwEmptyState(
                icon: Icons.request_quote_outlined,
                title: 'No RFQs yet',
                subtitle: 'Post an RFQ and get quotes from multiple suppliers',
                actionLabel: 'Post RFQ',
                onAction: () => context.push('/ewholesale/rfq/create'),
              )
            : ListView.builder(
                padding: const EdgeInsets.all(12),
                itemCount: rfqs.length,
                itemBuilder: (_, i) => _RfqCard(rfq: rfqs[i]),
              ),
        ),
      ),
    );
  }
}

class _RfqCard extends StatelessWidget {
  final EwRfq rfq;
  const _RfqCard({required this.rfq});

  @override
  Widget build(BuildContext context) {
    final daysLeft = rfq.expiresAt.difference(DateTime.now()).inDays;
    final quotesCount = rfq.quotes.length;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: EwTheme.surface,
        borderRadius: EwTheme.radius12,
        boxShadow: EwTheme.cardShadow,
        border: Border.all(color: EwTheme.border),
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: EwTheme.radius12,
        child: InkWell(
          borderRadius: EwTheme.radius12,
          onTap: () => context.push('/ewholesale/rfq/${rfq.id}'),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Expanded(child: Text(rfq.title, style: EwTheme.heading3, maxLines: 2, overflow: TextOverflow.ellipsis)),
                _statusChip(rfq.status),
              ]),
              const SizedBox(height: 6),
              Text('${rfq.qty.toInt()} ${rfq.unit} · ${rfq.categoryName ?? ''}',
                style: EwTheme.bodySmall),
              const SizedBox(height: 8),
              Row(children: [
                if (quotesCount > 0) ...[
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(color: EwTheme.green.withOpacity(0.1), borderRadius: EwTheme.radius4),
                    child: Text('$quotesCount quote${quotesCount > 1 ? 's' : ''} received',
                      style: const TextStyle(fontSize: 11, color: EwTheme.green, fontWeight: FontWeight.w600)),
                  ),
                  const SizedBox(width: 8),
                ],
                if (daysLeft > 0)
                  Text('Expires in $daysLeft days', style: EwTheme.bodySmall.copyWith(
                    color: daysLeft <= 3 ? EwTheme.red : EwTheme.textSecondary)),
                const Spacer(),
                const Icon(Icons.chevron_right, size: 16, color: EwTheme.textMuted),
              ]),
            ]),
          ),
        ),
      ),
    );
  }

  Widget _statusChip(String status) {
    final (label, fg, bg) = switch (status) {
      'open'     => ('Open', EwTheme.green, const Color(0xFFDCFCE7)),
      'awarded'  => ('Awarded', EwTheme.badgeBlue, EwTheme.badgeBlueSurface),
      'expired'  => ('Expired', EwTheme.textMuted, EwTheme.badgeGraySurface),
      'closed'   => ('Closed', EwTheme.textMuted, EwTheme.badgeGraySurface),
      _          => (status, EwTheme.textSecondary, EwTheme.badgeGraySurface),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: bg, borderRadius: EwTheme.radius4),
      child: Text(label, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: fg)),
    );
  }
}

// ─── RFQ Detail Screen ────────────────────────────────────────────────────────

class EwRfqDetailScreen extends ConsumerWidget {
  final int rfqId;
  const EwRfqDetailScreen({super.key, required this.rfqId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final rfqsAsync = ref.watch(ewRfqsProvider);

    return Scaffold(
      backgroundColor: EwTheme.bg,
      appBar: AppBar(
        backgroundColor: EwTheme.navy,
        foregroundColor: Colors.white,
        title: const Text('RFQ Detail', style: TextStyle(fontWeight: FontWeight.w700)),
      ),
      body: rfqsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: EwTheme.orange)),
        error:   (e, _) => EwErrorRetry(error: e, onRetry: () => ref.refresh(ewRfqsProvider.future)),
        data:    (rfqs) {
          final rfq = rfqs.where((r) => r.id == rfqId).firstOrNull;
          if (rfq == null) return const EwEmptyState(icon: Icons.search_off, title: 'RFQ not found');
          return _buildContent(context, ref, rfq);
        },
      ),
    );
  }

  Widget _buildContent(BuildContext context, WidgetRef ref, EwRfq rfq) {
    return ListView(padding: const EdgeInsets.all(16), children: [
      // RFQ summary
      Container(
        decoration: BoxDecoration(color: EwTheme.surface, borderRadius: EwTheme.radius12, border: Border.all(color: EwTheme.border)),
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(rfq.title, style: EwTheme.heading2),
          const SizedBox(height: 8),
          _row('Category', rfq.categoryName ?? '—'),
          _row('Quantity', '${rfq.qty.toInt()} ${rfq.unit}'),
          if (rfq.targetPrice != null) _row('Target Price', EwTheme.formatPrice(rfq.targetPrice!)),
          if (rfq.neededBy != null) _row('Needed by', _formatDate(rfq.neededBy!)),
          _row('Expires', _formatDate(rfq.expiresAt)),
        ]),
      ),
      const SizedBox(height: 20),
      EwSectionHeader(title: '${rfq.quotes.length} Quotes Received'),
      const SizedBox(height: 12),
      if (rfq.quotes.isEmpty)
        const EwEmptyState(icon: Icons.hourglass_empty_outlined,
          title: 'Waiting for quotes', subtitle: 'Suppliers will submit quotes within the expiry window')
      else
        ...rfq.quotes.map((q) => _RfqQuoteCard(rfqId: rfq.id, quote: q, ref: ref)),
    ]);
  }

  Widget _row(String label, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Row(children: [
      Expanded(flex: 2, child: Text(label, style: EwTheme.bodySmall.copyWith(fontWeight: FontWeight.w600))),
      Expanded(flex: 3, child: Text(value, style: EwTheme.body)),
    ]),
  );

  String _formatDate(DateTime d) => '${d.day}/${d.month}/${d.year}';
}

class _RfqQuoteCard extends StatelessWidget {
  final int rfqId;
  final EwRfqQuote quote;
  final WidgetRef ref;
  const _RfqQuoteCard({required this.rfqId, required this.quote, required this.ref});

  @override
  Widget build(BuildContext context) {
    final s = quote.supplier;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: EwTheme.surface,
        borderRadius: EwTheme.radius12,
        border: Border.all(
          color: quote.status == 'shortlisted' ? EwTheme.orange : EwTheme.border,
          width: quote.status == 'shortlisted' ? 2 : 1,
        ),
        boxShadow: EwTheme.cardShadow,
      ),
      padding: const EdgeInsets.all(14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (s != null) Row(children: [
          CircleAvatar(radius: 16, backgroundColor: EwTheme.navy.withOpacity(0.08),
            backgroundImage: s.logo != null ? NetworkImage(s.logo!) : null,
            child: s.logo == null ? Text(s.displayName.isNotEmpty ? s.displayName[0] : '?',
              style: const TextStyle(fontSize: 12, color: EwTheme.navy)) : null),
          const SizedBox(width: 8),
          Expanded(child: Text(s.displayName, style: EwTheme.heading3, maxLines: 1, overflow: TextOverflow.ellipsis)),
          SupplierBadge(verification: s.verification, compact: true),
          const SizedBox(width: 6),
          QuoteStatusChip(status: quote.status),
        ]),
        const SizedBox(height: 10),
        Row(children: [
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(EwTheme.formatPrice(quote.unitPrice), style: EwTheme.price.copyWith(color: EwTheme.navy)),
            Text('per unit', style: EwTheme.bodySmall),
          ]),
          if (quote.leadTimeDays != null) ...[
            const SizedBox(width: 20),
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${quote.leadTimeDays}d', style: EwTheme.heading3),
              const Text('Lead time', style: TextStyle(fontSize: 11, color: EwTheme.textSecondary)),
            ]),
          ],
        ]),
        if (quote.note != null) ...[
          const SizedBox(height: 8),
          Text(quote.note!, style: EwTheme.bodySmall),
        ],
        if (quote.status == 'sent' || quote.status == 'shortlisted') ...[
          const SizedBox(height: 12),
          Row(children: [
            if (quote.status != 'shortlisted')
              Expanded(child: OutlinedButton(
                onPressed: () => _shortlist(context, quote),
                style: EwTheme.secondaryButton.copyWith(minimumSize: const WidgetStatePropertyAll(Size(0, 36))),
                child: const Text('Shortlist', style: TextStyle(fontSize: 12)),
              )),
            if (quote.status != 'shortlisted') const SizedBox(width: 8),
            Expanded(child: ElevatedButton(
              onPressed: () => _accept(context, quote),
              style: EwTheme.primaryButton.copyWith(minimumSize: const WidgetStatePropertyAll(Size(0, 36))),
              child: const Text('Accept', style: TextStyle(fontSize: 12)),
            )),
            const SizedBox(width: 8),
            IconButton(
              onPressed: () => _reject(context, quote),
              icon: const Icon(Icons.close, color: EwTheme.red, size: 18),
            ),
          ]),
        ],
      ]),
    );
  }

  Future<void> _shortlist(BuildContext ctx, EwRfqQuote q) async {
    await ref.read(ewRepoProvider).shortlistRfqQuote(q.id);
    ref.invalidate(ewRfqsProvider);
  }

  Future<void> _accept(BuildContext ctx, EwRfqQuote q) async {
    await ref.read(ewRepoProvider).acceptRfqQuote(q.id);
    ref.invalidate(ewRfqsProvider);
    if (ctx.mounted) ctx.push('/ewholesale/cart');
  }

  Future<void> _reject(BuildContext ctx, EwRfqQuote q) async {
    await ref.read(ewRepoProvider).rejectRfqQuote(q.id);
    ref.invalidate(ewRfqsProvider);
  }
}
