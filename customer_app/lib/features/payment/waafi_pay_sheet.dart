import 'dart:async';
import 'package:flutter/material.dart';
import '../../core/api/module_api_service.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/error_handler.dart';
import '../../core/widgets/phone_input_field.dart';

/// Result returned from showWaafiPaySheet
class WaafiPayResult {
  final bool success;
  final String? reference;
  final String? message;
  const WaafiPayResult({required this.success, this.reference, this.message});
}

/// Show the Waafi Pay payment bottom sheet.
/// [type] can be 'order', 'topup', or 'custom'
/// Returns WaafiPayResult when dismissed.
Future<WaafiPayResult?> showWaafiPaySheet(
  BuildContext context, {
  required double amount,
  required String type,
  String? description,
  String? prefillPhone,
}) {
  return showModalBottomSheet<WaafiPayResult>(
    context: context,
    isScrollControlled: true,
    useRootNavigator: true,
    backgroundColor: Colors.transparent,
    builder: (_) => _WaafiPaySheet(
      amount: amount,
      type: type,
      description: description,
      prefillPhone: prefillPhone,
    ),
  );
}

class _WaafiPaySheet extends StatefulWidget {
  final double amount;
  final String type;
  final String? description;
  final String? prefillPhone;
  const _WaafiPaySheet({required this.amount, required this.type, this.description, this.prefillPhone});

  @override
  State<_WaafiPaySheet> createState() => _WaafiPaySheetState();
}

enum _PayState { input, loading, waiting, success, failed }

class _WaafiPaySheetState extends State<_WaafiPaySheet> {
  final _phoneCtrl = TextEditingController();
  CountryCode _country = kDefaultCountry;
  final _svc = ModuleApiService.create();
  _PayState _state = _PayState.input;
  String? _reference;
  String _message = '';
  Timer? _pollTimer;
  int _pollCount = 0;
  static const _maxPolls = 20; // 60 seconds

  @override
  void initState() {
    super.initState();
    if (widget.prefillPhone != null) _phoneCtrl.text = widget.prefillPhone!;
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _phoneCtrl.dispose();
    super.dispose();
  }

  Future<void> _initiate() async {
    final phone = '${_country.dialCode}${_phoneCtrl.text.trim()}';
    if (_phoneCtrl.text.trim().length < 6) {
      setState(() => _message = 'Please enter a valid phone number');
      return;
    }

    setState(() { _state = _PayState.loading; _message = ''; });

    try {
      final res = await _svc.initiatePayment({
        'amount':      widget.amount,
        'phone':       phone,
        'type':        widget.type,
        'description': widget.description ?? 'eSahlan Payment',
      });

      if (res['success'] == true) {
        _reference = res['reference'];
        if (res['status'] == 'success') {
          setState(() { _state = _PayState.success; _message = 'Payment approved!'; });
        } else {
          setState(() { _state = _PayState.waiting; _message = 'Confirmation sent to $phone'; });
          _startPolling();
        }
      } else {
        setState(() { _state = _PayState.failed; _message = res['message'] ?? 'Payment failed'; });
      }
    } catch (e) {
      setState(() { _state = _PayState.failed; _message = AppErrorHandler.message(e); });
    }
  }

  void _startPolling() {
    _pollCount = 0;
    _pollTimer = Timer.periodic(const Duration(seconds: 3), (_) => _poll());
  }

  Future<void> _poll() async {
    if (_reference == null || !mounted) return;
    _pollCount++;
    if (_pollCount > _maxPolls) {
      _pollTimer?.cancel();
      if (mounted) setState(() { _state = _PayState.failed; _message = 'Payment timeout. Please try again.'; });
      return;
    }

    try {
      final res = await _svc.checkPaymentStatus(_reference!);
      final status = res['status'] ?? 'pending';
      if (status == 'success') {
        _pollTimer?.cancel();
        if (mounted) setState(() { _state = _PayState.success; _message = 'Payment approved!'; });
      } else if (status == 'failed') {
        _pollTimer?.cancel();
        if (mounted) setState(() { _state = _PayState.failed; _message = res['message'] ?? 'Payment rejected'; });
      }
    } catch (_) {}
  }

  void _close({bool success = false}) {
    _pollTimer?.cancel();
    Navigator.of(context).pop(WaafiPayResult(
      success: success,
      reference: _reference,
      message: _message,
    ));
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: EdgeInsets.only(
        left: 24, right: 24, top: 24,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 20),
          _buildHeader(),
          const SizedBox(height: 24),
          _buildBody(),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    return Row(
      children: [
        Container(
          width: 48, height: 48,
          decoration: BoxDecoration(
            gradient: const LinearGradient(colors: [Color(0xFF1a237e), AppColors.primary]),
            borderRadius: BorderRadius.circular(14),
          ),
          child: const Icon(Icons.account_balance_wallet_rounded, color: Colors.white, size: 24),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Waafi Pay', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: AppColors.secondary)),
            Text('\$${widget.amount.toStringAsFixed(2)}', style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w800, fontSize: 15)),
          ]),
        ),
        IconButton(
          icon: const Icon(Icons.close_rounded, color: AppColors.textGrey),
          onPressed: () => _close(success: false),
        ),
      ],
    );
  }

  Widget _buildBody() {
    switch (_state) {
      case _PayState.input:
        return _buildInput();
      case _PayState.loading:
        return _buildLoading('Connecting to Waafi Pay...');
      case _PayState.waiting:
        return _buildWaiting();
      case _PayState.success:
        return _buildSuccess();
      case _PayState.failed:
        return _buildFailed();
    }
  }

  Widget _buildInput() {
    return Column(mainAxisSize: MainAxisSize.min, children: [
      const Align(
        alignment: Alignment.centerLeft,
        child: Text('Phone Number (EVC/Waafi)',
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFF374151))),
      ),
      const SizedBox(height: 8),
      PhoneInputField(
        controller: _phoneCtrl,
        onCountryChanged: (c) => setState(() => _country = c),
      ),
      if (_message.isNotEmpty) ...[
        const SizedBox(height: 8),
        Text(_message, style: const TextStyle(color: AppColors.error, fontSize: 13)),
      ],
      const SizedBox(height: 8),
      Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(10)),
        child: Row(children: const [
          Icon(Icons.info_outline_rounded, size: 16, color: AppColors.textGrey),
          SizedBox(width: 8),
          Expanded(child: Text(
            'You will receive a confirmation prompt on your phone. Accept to complete payment.',
            style: TextStyle(fontSize: 12, color: AppColors.textGrey),
          )),
        ]),
      ),
      const SizedBox(height: 20),
      SizedBox(
        width: double.infinity, height: 52,
        child: ElevatedButton.icon(
          onPressed: _initiate,
          icon: const Icon(Icons.send_rounded),
          label: Text('Pay \$${widget.amount.toStringAsFixed(2)}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
          style: ElevatedButton.styleFrom(
            backgroundColor: AppColors.primary,
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          ),
        ),
      ),
      const SizedBox(height: 8),
    ]);
  }

  Widget _buildLoading(String msg) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 32),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        const CircularProgressIndicator(color: AppColors.primary, strokeWidth: 3),
        const SizedBox(height: 16),
        Text(msg, style: const TextStyle(color: AppColors.textGrey, fontSize: 14)),
      ]),
    );
  }

  Widget _buildWaiting() {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 24),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 80, height: 80,
          decoration: BoxDecoration(
            color: const Color(0xFFFFF8E1),
            shape: BoxShape.circle,
            border: Border.all(color: const Color(0xFFF57F17), width: 2),
          ),
          child: const Icon(Icons.phone_in_talk_rounded, size: 40, color: Color(0xFFF57F17)),
        ),
        const SizedBox(height: 16),
        const Text('Check Your Phone', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: AppColors.secondary)),
        const SizedBox(height: 8),
        Text(_message, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.textGrey, fontSize: 14)),
        const SizedBox(height: 4),
        const Text('Waiting for confirmation...', style: TextStyle(color: AppColors.textGrey, fontSize: 13)),
        const SizedBox(height: 16),
        const CircularProgressIndicator(color: AppColors.primary, strokeWidth: 2),
        const SizedBox(height: 16),
        TextButton(
          onPressed: () => _close(success: false),
          child: const Text('Cancel', style: TextStyle(color: AppColors.textGrey)),
        ),
      ]),
    );
  }

  Widget _buildSuccess() {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 24),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 80, height: 80,
          decoration: BoxDecoration(color: Colors.green.shade50, shape: BoxShape.circle),
          child: Icon(Icons.check_circle_rounded, size: 56, color: Colors.green.shade600),
        ),
        const SizedBox(height: 16),
        const Text('Payment Successful!', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 20, color: AppColors.secondary)),
        const SizedBox(height: 8),
        Text('\$${widget.amount.toStringAsFixed(2)} paid via Waafi Pay',
            style: const TextStyle(color: AppColors.textGrey, fontSize: 14)),
        const SizedBox(height: 24),
        SizedBox(
          width: double.infinity, height: 52,
          child: ElevatedButton(
            onPressed: () => _close(success: true),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.green.shade600,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            child: const Text('Continue', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
          ),
        ),
      ]),
    );
  }

  Widget _buildFailed() {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 24),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 80, height: 80,
          decoration: BoxDecoration(color: Colors.red.shade50, shape: BoxShape.circle),
          child: Icon(Icons.cancel_rounded, size: 56, color: Colors.red.shade600),
        ),
        const SizedBox(height: 16),
        const Text('Payment Failed', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 20, color: AppColors.secondary)),
        const SizedBox(height: 8),
        Text(_message, textAlign: TextAlign.center, style: TextStyle(color: Colors.red.shade700, fontSize: 13)),
        const SizedBox(height: 24),
        Row(children: [
          Expanded(
            child: OutlinedButton(
              onPressed: () => _close(success: false),
              style: OutlinedButton.styleFrom(shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
              child: const Text('Cancel'),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: ElevatedButton(
              onPressed: () => setState(() { _state = _PayState.input; _message = ''; }),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: const Text('Try Again', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
            ),
          ),
        ]),
      ]),
    );
  }
}
