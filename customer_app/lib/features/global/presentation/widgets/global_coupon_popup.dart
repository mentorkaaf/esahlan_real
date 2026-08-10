import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/global_models.dart';
import '../providers/global_provider.dart';

/// Shows the Shein-style "Special Deals" coupon collection popup.
Future<void> showCouponPopup(
    BuildContext context, List<GlobalCoupon> coupons, WidgetRef ref) {
  return showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (_) => _CouponPopupSheet(coupons: coupons, ref: ref),
  );
}

class _CouponPopupSheet extends StatefulWidget {
  final List<GlobalCoupon> coupons;
  final WidgetRef ref;
  const _CouponPopupSheet({required this.coupons, required this.ref});

  @override
  State<_CouponPopupSheet> createState() => _CouponPopupSheetState();
}

class _CouponPopupSheetState extends State<_CouponPopupSheet> {
  bool _collecting = false;
  final Set<int> _collected = {};

  static const _navy = Color(0xFF1A1A2E);
  static const _gold = Color(0xFFF59E0B);

  Future<void> _collectAll() async {
    if (_collecting) return;
    setState(() => _collecting = true);
    HapticFeedback.mediumImpact();
    try {
      await widget.ref.read(globalRepoProvider).collectAllCoupons();
      setState(() {
        _collected.addAll(widget.coupons.map((c) => c.id));
      });
      widget.ref.invalidate(globalAvailableCouponsProvider);
      widget.ref.invalidate(globalMyCouponsProvider);
      await Future.delayed(const Duration(milliseconds: 600));
      if (mounted) Navigator.pop(context);
    } catch (_) {
      setState(() => _collecting = false);
    }
  }

  Future<void> _collectOne(GlobalCoupon coupon) async {
    if (_collected.contains(coupon.id)) return;
    HapticFeedback.lightImpact();
    try {
      await widget.ref.read(globalRepoProvider).collectCoupon(coupon.id);
      setState(() => _collected.add(coupon.id));
      widget.ref.invalidate(globalMyCouponsProvider);
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final allCollected = widget.coupons.every((c) => _collected.contains(c.id));

    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Handle
          Container(
            margin: const EdgeInsets.only(top: 12),
            width: 40,
            height: 4,
            decoration: BoxDecoration(
              color: Colors.grey[300],
              borderRadius: BorderRadius.circular(2),
            ),
          ),

          // Header
          Container(
            margin: const EdgeInsets.fromLTRB(20, 16, 20, 0),
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF1A1A2E), Color(0xFF2D2D5E)],
                begin: Alignment.centerLeft,
                end: Alignment.centerRight,
              ),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: _gold.withOpacity(0.2),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.local_offer_rounded,
                      color: _gold, size: 22),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Special Deals for You! 🎉',
                        style: TextStyle(
                            color: Colors.white,
                            fontSize: 15,
                            fontWeight: FontWeight.w800),
                      ),
                      Text(
                        '${widget.coupons.length} exclusive coupon${widget.coupons.length != 1 ? 's' : ''} available',
                        style: TextStyle(
                            color: Colors.white.withOpacity(0.7),
                            fontSize: 11),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Coupon list
          ConstrainedBox(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.of(context).size.height * 0.45,
            ),
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 4),
              shrinkWrap: true,
              itemCount: widget.coupons.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (_, i) => _CouponTile(
                coupon: widget.coupons[i],
                collected: _collected.contains(widget.coupons[i].id),
                onCollect: () => _collectOne(widget.coupons[i]),
              ),
            ),
          ),

          // Collect All button
          Padding(
            padding: EdgeInsets.fromLTRB(
                20, 8, 20, 20 + MediaQuery.of(context).padding.bottom),
            child: SizedBox(
              width: double.infinity,
              height: 52,
              child: ElevatedButton(
                onPressed: allCollected ? null : _collectAll,
                style: ElevatedButton.styleFrom(
                  backgroundColor: allCollected ? Colors.grey[300] : _navy,
                  foregroundColor: allCollected ? Colors.grey : _gold,
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14)),
                  elevation: allCollected ? 0 : 2,
                ),
                child: _collecting
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                            color: _gold, strokeWidth: 2.5))
                    : Text(
                        allCollected
                            ? '✓ All Coupons Collected!'
                            : 'Collect All ${widget.coupons.length} Coupons',
                        style: const TextStyle(
                            fontSize: 15, fontWeight: FontWeight.w800),
                      ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CouponTile extends StatelessWidget {
  final GlobalCoupon coupon;
  final bool collected;
  final VoidCallback onCollect;

  const _CouponTile({
    required this.coupon,
    required this.collected,
    required this.onCollect,
  });

  static const _navy = Color(0xFF1A1A2E);
  static const _gold = Color(0xFFF59E0B);

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 82,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: collected ? Colors.green.shade200 : const Color(0xFFE5E7EB),
          width: 1.5,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          // Left colored discount slab
          Container(
            width: 80,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: collected
                    ? [Colors.green.shade400, Colors.green.shade600]
                    : [const Color(0xFFEF4444), const Color(0xFFDC2626)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: const BorderRadius.horizontal(
                  left: Radius.circular(12)),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  collected ? '✓' : coupon.discountLabel.split(' ').first,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  collected ? 'Collected' : 'OFF',
                  style: const TextStyle(
                    color: Colors.white70,
                    fontSize: 10,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),

          // Dotted separator
          _DottedDivider(),

          // Middle: coupon info
          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          coupon.name,
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF1A1A2E),
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      if (coupon.isNewUserOnly)
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: _gold.withOpacity(0.15),
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: const Text(
                            'NEW USER',
                            style: TextStyle(
                              fontSize: 8,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFFB45309),
                            ),
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 3),
                  if (coupon.minimumOrder > 0)
                    Text(
                      'Min order \$${coupon.minimumOrder.toStringAsFixed(0)}',
                      style: TextStyle(
                        fontSize: 10,
                        color: Colors.grey[500],
                      ),
                    ),
                  if (coupon.daysLeft != null)
                    Text(
                      coupon.daysLeft! == 0
                          ? 'Expires today!'
                          : 'Expires in ${coupon.daysLeft}d',
                      style: TextStyle(
                        fontSize: 10,
                        color: coupon.daysLeft! <= 3
                            ? Colors.red[400]
                            : Colors.grey[500],
                      ),
                    ),
                ],
              ),
            ),
          ),

          // Collect button
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: GestureDetector(
              onTap: collected ? null : onCollect,
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 300),
                padding:
                    const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                decoration: BoxDecoration(
                  color: collected ? Colors.green : _navy,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  collected ? '✓' : 'Collect',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _DottedDivider extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 16,
      height: 82,
      child: CustomPaint(painter: _DottedPainter()),
    );
  }
}

class _DottedPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFFE5E7EB)
      ..strokeWidth = 1.5
      ..style = PaintingStyle.fill;

    const dotSize = 4.0;
    const spacing = 7.0;
    double y = dotSize;
    while (y < size.height - dotSize) {
      canvas.drawCircle(Offset(size.width / 2, y), dotSize / 2, paint);
      y += spacing;
    }
  }

  @override
  bool shouldRepaint(_) => false;
}
