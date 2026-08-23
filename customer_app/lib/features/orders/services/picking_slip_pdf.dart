import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter/services.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import '../data/models/order_model.dart';

// ── Brand colors ──────────────────────────────────────────────────────────────
const _navy   = PdfColor.fromInt(0xFF07003B);
const _navyL  = PdfColor.fromInt(0xFF1B0F6E);
const _amber  = PdfColor.fromInt(0xFFFF8A00);
const _green  = PdfColor.fromInt(0xFF22C55E);
const _red    = PdfColor.fromInt(0xFFEF4444);
const _grey   = PdfColor.fromInt(0xFF6B7280);
const _border = PdfColor.fromInt(0xFFE5E7EB);
const _bg     = PdfColor.fromInt(0xFFF9FAFB);
const _white  = PdfColors.white;

class PickingSlipPdf {
  // Generate PDF bytes for an order
  static Future<Uint8List> generate(OrderModel order) async {
    final doc = pw.Document();

    // Load logo
    pw.ImageProvider? logo;
    try {
      final bytes = await rootBundle.load('assets/images/logo.png');
      logo = pw.MemoryImage(bytes.buffer.asUint8List());
    } catch (_) {}

    // Parse notes JSON (for service modules)
    Map<String, dynamic> notes = {};
    if (order.note != null && order.note!.isNotEmpty) {
      try {
        final decoded = jsonDecode(order.note!);
        if (decoded is Map) notes = Map<String, dynamic>.from(decoded);
      } catch (_) {}
    }

    final slug = (order.moduleSlug ?? '').toLowerCase();
    const serviceModules = ['eparcel', 'edata', 'erent', 'emoving', 'elaundry'];
    final isService = serviceModules.contains(slug);

    doc.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4,
        margin: const pw.EdgeInsets.all(32),
        build: (ctx) => [
          _buildHeader(order, logo, slug),
          pw.SizedBox(height: 16),
          _buildCustomerInfo(order),
          pw.SizedBox(height: 14),
          // Module-specific details
          if (slug == 'eparcel')   _buildEParcel(notes),
          if (slug == 'edata')     _buildEData(notes),
          if (slug == 'erent')     _buildERent(notes),
          if (slug == 'emoving')   _buildEMoving(notes),
          if (slug == 'elaundry')  _buildELaundry(notes),
          if (!isService)          _buildItemsTable(order),
          pw.SizedBox(height: 14),
          _buildTotals(order, slug, notes),
          pw.SizedBox(height: 28),
          _buildSignatures(),
          pw.SizedBox(height: 20),
          _buildFooter(order, slug),
        ],
      ),
    );

    return doc.save();
  }

  // ─── Header ─────────────────────────────────────────────────────────────────
  static pw.Widget _buildHeader(OrderModel order, pw.ImageProvider? logo, String slug) {
    return pw.Container(
      padding: const pw.EdgeInsets.all(16),
      decoration: pw.BoxDecoration(
        gradient: const pw.LinearGradient(colors: [_navy, _navyL]),
        borderRadius: const pw.BorderRadius.all(pw.Radius.circular(10)),
      ),
      child: pw.Row(
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          // Left: picking slip label + order number
          pw.Expanded(
            child: pw.Column(
              crossAxisAlignment: pw.CrossAxisAlignment.start,
              children: [
                pw.Text('PICKING SLIP',
                  style: pw.TextStyle(fontSize: 9, color: _amber,
                    fontWeight: pw.FontWeight.bold, letterSpacing: 2)),
                pw.SizedBox(height: 4),
                pw.Text(order.orderNumber,
                  style: pw.TextStyle(fontSize: 20, color: _white,
                    fontWeight: pw.FontWeight.bold)),
                pw.SizedBox(height: 4),
                pw.Text(_formatDate(order.createdAt),
                  style: pw.TextStyle(fontSize: 10, color: _white.shade(0.75))),
                pw.SizedBox(height: 8),
                _statusBadge(order.status),
              ],
            ),
          ),
          // Right: logo + module name
          pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.end,
            children: [
              if (logo != null)
                pw.Image(logo, width: 60, height: 30, fit: pw.BoxFit.contain)
              else
                pw.Text('eSahlan',
                  style: pw.TextStyle(fontSize: 22, color: _white,
                    fontWeight: pw.FontWeight.bold)),
              pw.SizedBox(height: 4),
              pw.Text(_moduleName(slug),
                style: pw.TextStyle(fontSize: 10, color: _amber)),
            ],
          ),
        ],
      ),
    );
  }

  // ─── Customer info ───────────────────────────────────────────────────────────
  static pw.Widget _buildCustomerInfo(OrderModel order) {
    return pw.Container(
      padding: const pw.EdgeInsets.all(14),
      decoration: pw.BoxDecoration(
        color: _bg,
        border: pw.Border.all(color: _border),
        borderRadius: const pw.BorderRadius.all(pw.Radius.circular(8)),
      ),
      child: pw.Row(
        children: [
          _infoChunk('Payment Method',
            (order.paymentMethod ?? 'cash').toUpperCase().replaceAll('_', ' ')),
          pw.SizedBox(width: 32),
          _infoChunk('Payment Status',
            (order.paymentStatus ?? 'pending').toUpperCase(),
            valueColor: order.paymentStatus == 'paid' ? _green : _amber),
          if ((order.deliveryAddress ?? '').isNotEmpty) ...[
            pw.SizedBox(width: 32),
            _infoChunk('Delivery Address', order.deliveryAddress ?? ''),
          ],
          if ((order.vendorName ?? '').isNotEmpty) ...[
            pw.SizedBox(width: 32),
            _infoChunk('Vendor', order.vendorName ?? ''),
          ],
        ],
      ),
    );
  }

  // ─── eParcel ─────────────────────────────────────────────────────────────────
  static pw.Widget _buildEParcel(Map<String, dynamic> n) {
    final pickup = n['pickup'] is Map ? n['pickup'] as Map : {};
    return _sectionCard('📦 Parcel Details', [
      ['Sender Name',      pickup['name']?.toString() ?? '—'],
      ['Sender Phone',     pickup['phone']?.toString() ?? '—'],
      ['Recipient Name',   n['recipient']?.toString() ?? '—'],
      ['Recipient Phone',  n['recipient_phone']?.toString() ?? '—'],
      if ((n['description'] ?? '').toString().isNotEmpty)
        ['Description',    n['description'].toString()],
    ]);
  }

  // ─── eData ───────────────────────────────────────────────────────────────────
  static pw.Widget _buildEData(Map<String, dynamic> n) {
    return _sectionCard('📡 Data Bundle', [
      ['Bundle Name',    n['item_name']?.toString() ?? '—'],
      ['Data Amount',    n['data_amount']?.toString() ?? '—'],
      ['Validity',       '${n['validity_days'] ?? '—'} days'],
      ['Phone Number',   n['phone_number']?.toString() ?? '—'],
      ['Type',           _capitalize(n['type']?.toString() ?? '—')],
      ['Price',          '\$${_fmt(n['price'])}'],
    ]);
  }

  // ─── eRent ───────────────────────────────────────────────────────────────────
  static pw.Widget _buildERent(Map<String, dynamic> n) {
    return _sectionCard('🏠 Rental Details', [
      ['Property',       n['property_title']?.toString() ?? '—'],
      ['Type',           _capitalize(n['property_type']?.toString() ?? '—')],
      ['Booking Type',   _capitalize(n['booking_type']?.toString() ?? '—')],
      ['Move-in Date',   n['move_in_date']?.toString() ?? '—'],
      ['Duration',       '${n['duration_months'] ?? '—'} month(s)'],
      ['Monthly Rent',   '\$${_fmt(n['monthly_rent'])}'],
      ['Deposit',        '\$${_fmt(n['deposit'])}'],
      ['Brokerage Fee',  '\$${_fmt(n['brokerage_fee'])}'],
      ['Total Value',    '\$${_fmt(n['total_value'])}'],
      ['Amount Paid',    '\$${_fmt(n['amount_paid'])}'],
      ['Balance Due',    '\$${_fmt(n['amount_remaining'])}'],
    ]);
  }

  // ─── eMoving ─────────────────────────────────────────────────────────────────
  static pw.Widget _buildEMoving(Map<String, dynamic> n) {
    final extras = n['extra_services'] is List
        ? (n['extra_services'] as List).join(', ')
        : '';
    return _sectionCard('🚛 Moving Details', [
      ['Move Type',       _capitalize(n['move_type']?.toString() ?? '—')],
      ['Rooms',           n['room_count']?.toString() ?? '—'],
      ['From District',   n['from_district']?.toString() ?? '—'],
      ['To District',     n['to_district']?.toString() ?? '—'],
      ['Scheduled Date',  n['scheduled_date']?.toString() ?? '—'],
      if (extras.isNotEmpty) ['Extra Services', extras],
      ['Base Price',      '\$${_fmt(n['base_price'])}'],
      ['Room Price',      '\$${_fmt(n['room_price'])}'],
      if ((n['extra_fee'] as num? ?? 0) > 0) ['Extra Fee', '\$${_fmt(n['extra_fee'])}'],
      if ((n['distance_fee'] as num? ?? 0) > 0) ['Distance Fee', '\$${_fmt(n['distance_fee'])}'],
    ]);
  }

  // ─── eLaundry ────────────────────────────────────────────────────────────────
  static pw.Widget _buildELaundry(Map<String, dynamic> n) {
    final laundryItems = n['items'] is List ? n['items'] as List : [];
    return pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.start, children: [
      _sectionCard('👕 Laundry Details', [
        ['Service Type', _capitalize(n['service_type']?.toString() ?? '—')],
        ['District',     n['district']?.toString() ?? '—'],
        ['ETA',          n['eta']?.toString() ?? '—'],
      ]),
      if (laundryItems.isNotEmpty) ...[
        pw.SizedBox(height: 10),
        _buildGenericTable(
          headers: ['Item', 'Qty', 'Unit Price', 'Subtotal'],
          rows: laundryItems.map((item) {
            final m = item is Map ? item : {};
            return [
              m['name']?.toString() ?? '—',
              m['qty']?.toString() ?? '1',
              '\$${_fmt(m['price'])}',
              '\$${_fmt(m['sub'] ?? ((m['price'] as num? ?? 0) * (m['qty'] as num? ?? 1)))}',
            ];
          }).toList(),
        ),
      ],
    ]);
  }

  // ─── Product items table (eFood, eGrocery, eShop, etc.) ─────────────────────
  static pw.Widget _buildItemsTable(OrderModel order) {
    if (order.items.isEmpty) {
      return pw.Container(
        padding: const pw.EdgeInsets.all(16),
        decoration: pw.BoxDecoration(color: _bg, border: pw.Border.all(color: _border),
          borderRadius: const pw.BorderRadius.all(pw.Radius.circular(8))),
        child: pw.Center(child: pw.Text('No items', style: pw.TextStyle(color: _grey))),
      );
    }
    return _buildGenericTable(
      headers: ['✓', 'Product', 'Qty', 'Unit Price', 'Subtotal'],
      rows: order.items.map((item) => [
        '☐',
        '${item.productName}${item.variantName != null ? '\n${item.variantName}' : ''}',
        '${item.quantity}',
        '\$${item.price.toStringAsFixed(2)}',
        '\$${item.total.toStringAsFixed(2)}',
      ]).toList(),
    );
  }

  // ─── Totals ──────────────────────────────────────────────────────────────────
  static pw.Widget _buildTotals(OrderModel order, String slug, Map<String, dynamic> n) {
    List<List<String>> rows = [];

    if (slug == 'erent' && n.isNotEmpty) {
      rows = [
        ['Monthly Rent',  '\$${_fmt(n['monthly_rent'])}'],
        ['Deposit',       '\$${_fmt(n['deposit'])}'],
        ['Amount Paid',   '\$${_fmt(n['amount_paid'])}'],
        ['Balance Due',   '\$${_fmt(n['amount_remaining'])}'],
        ['TOTAL VALUE',   '\$${_fmt(n['total_value'])}'],
      ];
    } else if (slug == 'emoving' && n.isNotEmpty) {
      rows = [['TOTAL', '\$${_fmt(n['total'] ?? order.totalAmount)}']];
    } else if (slug == 'edata' && n.isNotEmpty) {
      rows = [['TOTAL', '\$${_fmt(n['price'] ?? order.totalAmount)}']];
    } else {
      final subtotal = order.totalAmount - (order.deliveryFee ?? 0) + (order.discount ?? 0);
      if (subtotal > 0) rows.add(['Subtotal', '\$${subtotal.toStringAsFixed(2)}']);
      if ((order.deliveryFee ?? 0) > 0)
        rows.add(['Delivery Fee', '\$${order.deliveryFee!.toStringAsFixed(2)}']);
      if ((order.discount ?? 0) > 0)
        rows.add(['Discount', '-\$${order.discount!.toStringAsFixed(2)}']);
      rows.add(['TOTAL', '\$${order.totalAmount.toStringAsFixed(2)}']);
    }

    return pw.Row(
      mainAxisAlignment: pw.MainAxisAlignment.end,
      children: [
        pw.Container(
          padding: const pw.EdgeInsets.all(14),
          decoration: pw.BoxDecoration(
            color: _bg, border: pw.Border.all(color: _border),
            borderRadius: const pw.BorderRadius.all(pw.Radius.circular(8))),
          child: pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.end,
            children: rows.map((r) {
              final isTotal = r[0].startsWith('TOTAL');
              return pw.Padding(
                padding: const pw.EdgeInsets.symmetric(vertical: 3),
                child: pw.Row(children: [
                  pw.Text(r[0], style: pw.TextStyle(
                    fontSize: isTotal ? 13 : 11,
                    fontWeight: isTotal ? pw.FontWeight.bold : pw.FontWeight.normal,
                    color: isTotal ? _navy : _grey)),
                  pw.SizedBox(width: 32),
                  pw.Text(r[1], style: pw.TextStyle(
                    fontSize: isTotal ? 14 : 11,
                    fontWeight: isTotal ? pw.FontWeight.bold : pw.FontWeight.normal,
                    color: isTotal ? _amber : _navy)),
                ]),
              );
            }).toList(),
          ),
        ),
      ],
    );
  }

  // ─── Signatures ──────────────────────────────────────────────────────────────
  static pw.Widget _buildSignatures() {
    return pw.Row(children: [
      _signatureBox('Staff Signature'),
      pw.SizedBox(width: 20),
      _signatureBox('Driver Signature'),
      pw.SizedBox(width: 20),
      _signatureBox('Customer Signature'),
    ]);
  }

  static pw.Widget _signatureBox(String label) => pw.Expanded(
    child: pw.Column(children: [
      pw.Container(height: 40,
        decoration: const pw.BoxDecoration(
          border: pw.Border(bottom: pw.BorderSide(color: _grey)))),
      pw.SizedBox(height: 4),
      pw.Text(label, style: pw.TextStyle(fontSize: 9, color: _grey)),
    ]),
  );

  // ─── Footer ──────────────────────────────────────────────────────────────────
  static pw.Widget _buildFooter(OrderModel order, String slug) {
    return pw.Container(
      padding: const pw.EdgeInsets.symmetric(vertical: 8),
      decoration: const pw.BoxDecoration(
        border: pw.Border(top: pw.BorderSide(color: _border))),
      child: pw.Row(
        mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
        children: [
          pw.Text('eSahlan ${_moduleName(slug)}',
            style: pw.TextStyle(fontSize: 9, color: _grey)),
          pw.Text('Order: ${order.orderNumber}',
            style: pw.TextStyle(fontSize: 9, color: _grey)),
          pw.Text('Printed: ${_formatNow()}',
            style: pw.TextStyle(fontSize: 9, color: _grey)),
        ],
      ),
    );
  }

  // ─── Reusable widgets ────────────────────────────────────────────────────────
  static pw.Widget _statusBadge(String status) {
    const colors = {
      'delivered': _green, 'cancelled': _red, 'failed': _red,
      'confirmed': PdfColor.fromInt(0xFF3B82F6),
      'preparing': PdfColor.fromInt(0xFF3B82F6),
    };
    final color = colors[status] ?? _amber;
    return pw.Container(
      padding: const pw.EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: pw.BoxDecoration(
        color: color.shade(0.15),
        border: pw.Border.all(color: color.shade(0.5)),
        borderRadius: const pw.BorderRadius.all(pw.Radius.circular(20))),
      child: pw.Text(status.toUpperCase().replaceAll('_', ' '),
        style: pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold, color: color)),
    );
  }

  static pw.Widget _infoChunk(String label, String value, {PdfColor? valueColor}) =>
    pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.start, children: [
      pw.Text(label.toUpperCase(),
        style: pw.TextStyle(fontSize: 8, color: _grey, fontWeight: pw.FontWeight.bold,
          letterSpacing: 0.5)),
      pw.SizedBox(height: 3),
      pw.Text(value,
        style: pw.TextStyle(fontSize: 11, fontWeight: pw.FontWeight.bold,
          color: valueColor ?? _navy)),
    ]);

  static pw.Widget _sectionCard(String title, List<List<String>> rows) {
    return pw.Container(
      decoration: pw.BoxDecoration(
        border: pw.Border.all(color: _border),
        borderRadius: const pw.BorderRadius.all(pw.Radius.circular(8))),
      child: pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.start, children: [
        pw.Container(
          width: double.infinity,
          padding: const pw.EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: const pw.BoxDecoration(
            color: _navy,
            borderRadius: pw.BorderRadius.only(
              topLeft: pw.Radius.circular(8), topRight: pw.Radius.circular(8))),
          child: pw.Text(title,
            style: pw.TextStyle(fontSize: 11, color: _white, fontWeight: pw.FontWeight.bold))),
        ...rows.asMap().entries.map((e) => pw.Container(
          color: e.key.isEven ? _bg : _white,
          padding: const pw.EdgeInsets.symmetric(horizontal: 12, vertical: 7),
          child: pw.Row(children: [
            pw.SizedBox(width: 120,
              child: pw.Text(e.value[0],
                style: pw.TextStyle(fontSize: 10, color: _grey, fontWeight: pw.FontWeight.bold))),
            pw.Text(e.value[1],
              style: pw.TextStyle(fontSize: 11, color: _navy)),
          ]),
        )),
      ]),
    );
  }

  static pw.Widget _buildGenericTable({required List<String> headers, required List<List<String>> rows}) {
    return pw.Table(
      border: pw.TableBorder.all(color: _border, width: 0.5),
      columnWidths: const {0: pw.FlexColumnWidth(0.5), 1: pw.FlexColumnWidth(3)},
      children: [
        // Header
        pw.TableRow(
          decoration: const pw.BoxDecoration(color: _navy),
          children: headers.map((h) => pw.Padding(
            padding: const pw.EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            child: pw.Text(h, style: pw.TextStyle(fontSize: 10, color: _white,
              fontWeight: pw.FontWeight.bold)),
          )).toList(),
        ),
        // Rows
        ...rows.asMap().entries.map((e) => pw.TableRow(
          decoration: pw.BoxDecoration(color: e.key.isEven ? _bg : _white),
          children: e.value.map((cell) => pw.Padding(
            padding: const pw.EdgeInsets.symmetric(horizontal: 8, vertical: 7),
            child: pw.Text(cell, style: pw.TextStyle(fontSize: 10, color: _navy)),
          )).toList(),
        )),
      ],
    );
  }

  // ─── Helpers ─────────────────────────────────────────────────────────────────
  static String _fmt(dynamic v) {
    if (v == null) return '0.00';
    final d = double.tryParse(v.toString()) ?? 0.0;
    return d.toStringAsFixed(2);
  }

  static String _capitalize(String s) =>
    s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';

  static String _moduleName(String slug) {
    const names = {
      'eparcel': 'eParcel', 'edata': 'eData', 'erent': 'eRent',
      'emoving': 'eMoving', 'elaundry': 'eLaundry', 'efood': 'eFood',
      'egrocery': 'eGrocery', 'eshop': 'eShop', 'elearning': 'eLearning',
    };
    return names[slug] ?? slug;
  }

  static String _formatDate(String dt) {
    try {
      final d = DateTime.parse(dt).toLocal();
      const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      const days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
      return '${days[d.weekday-1]}, ${months[d.month-1]} ${d.day}, ${d.year}  ${d.hour.toString().padLeft(2,'0')}:${d.minute.toString().padLeft(2,'0')}';
    } catch (_) {
      return dt;
    }
  }

  static String _formatNow() {
    final d = DateTime.now();
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return '${months[d.month-1]} ${d.day}, ${d.year}  ${d.hour.toString().padLeft(2,'0')}:${d.minute.toString().padLeft(2,'0')}';
  }
}
