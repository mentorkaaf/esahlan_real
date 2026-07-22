import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'crypto_theme.dart';

// ── CoinAvatar ────────────────────────────────────────────────────────────────

class CoinAvatarWidget extends StatelessWidget {
  const CoinAvatarWidget({super.key, required this.symbol, this.logoUrl, this.size = 36});
  final String symbol;
  final String? logoUrl;
  final double size;

  @override
  Widget build(BuildContext context) {
    if (logoUrl != null && logoUrl!.isNotEmpty) {
      return CachedNetworkImage(
        imageUrl: logoUrl!,
        width: size, height: size,
        imageBuilder: (_, img) => CircleAvatar(
          radius: size / 2,
          backgroundImage: img,
        ),
        errorWidget: (_, __, ___) => _FallbackAvatar(symbol: symbol, size: size),
        placeholder: (_, __) => _FallbackAvatar(symbol: symbol, size: size),
      );
    }
    return _FallbackAvatar(symbol: symbol, size: size);
  }
}

class _FallbackAvatar extends StatelessWidget {
  const _FallbackAvatar({required this.symbol, required this.size});
  final String symbol;
  final double size;

  static const _coinColors = {
    'BTC': Color(0xFFF7931A),
    'ETH': Color(0xFF627EEA),
    'USDT': Color(0xFF26A17B),
    'BNB': Color(0xFFF3BA2F),
    'SOL': Color(0xFF9945FF),
    'XRP': Color(0xFF0085C0),
    'USDC': Color(0xFF2775CA),
    'ADA': Color(0xFF0033AD),
    'DOGE': Color(0xFFC2A633),
    'TRX': Color(0xFFEF0027),
  };

  @override
  Widget build(BuildContext context) {
    final color = _coinColors[symbol.toUpperCase()] ?? kCryptoPrimary;
    return Container(
      width: size, height: size,
      decoration: BoxDecoration(color: color, shape: BoxShape.circle),
      alignment: Alignment.center,
      child: Text(
        symbol.length > 2 ? symbol.substring(0, 2) : symbol,
        style: TextStyle(
          color: Colors.white,
          fontSize: size * 0.33,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

// ── ChangeBadge ───────────────────────────────────────────────────────────────

class ChangeBadge extends StatelessWidget {
  const ChangeBadge(this.pct, {super.key});
  final double pct;

  @override
  Widget build(BuildContext context) {
    final color = cryptoChangeColor(pct);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
      decoration: BoxDecoration(
        color: color.withAlpha(30),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        cryptoPct(pct),
        style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w600),
      ),
    );
  }
}

// ── MiniSparkline ─────────────────────────────────────────────────────────────

class MiniSparkline extends StatelessWidget {
  const MiniSparkline(this.pts, {super.key, this.width = 60, this.height = 28});
  final List<double> pts;
  final double width, height;

  @override
  Widget build(BuildContext context) {
    if (pts.length < 2) return SizedBox(width: width, height: height);
    final positive = pts.last >= pts.first;
    return SizedBox(
      width: width, height: height,
      child: CustomPaint(painter: _SparkPainter(pts, positive)),
    );
  }
}

class _SparkPainter extends CustomPainter {
  const _SparkPainter(this.pts, this.positive);
  final List<double> pts;
  final bool positive;

  @override
  void paint(Canvas canvas, Size size) {
    if (pts.length < 2) return;
    final min = pts.reduce(math.min);
    final max = pts.reduce(math.max);
    final r = (max - min).abs() == 0 ? 1.0 : max - min;
    final path = Path();
    final color = positive ? kCryptoGreen : kCryptoRed;

    for (var i = 0; i < pts.length; i++) {
      final x = size.width * i / (pts.length - 1);
      final y = size.height - size.height * (pts[i] - min) / r;
      i == 0 ? path.moveTo(x, y) : path.lineTo(x, y);
    }
    canvas.drawPath(path, Paint()
      ..color = color
      ..strokeWidth = 1.5
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round);

    // Fill under the line
    final fill = Path.from(path)
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();
    canvas.drawPath(fill, Paint()
      ..shader = LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [color.withAlpha(60), color.withAlpha(0)],
      ).createShader(Rect.fromLTWH(0, 0, size.width, size.height))
      ..style = PaintingStyle.fill);
  }

  @override
  bool shouldRepaint(_SparkPainter o) => o.pts != pts;
}

// ── CryptoTextField ───────────────────────────────────────────────────────────

class CryptoTextField extends StatelessWidget {
  const CryptoTextField({
    super.key,
    required this.controller,
    this.label,
    this.hint,
    this.suffix,
    this.prefix,
    this.keyboardType,
    this.onChanged,
    this.readOnly = false,
  });
  final TextEditingController controller;
  final String? label, hint, suffix, prefix;
  final TextInputType? keyboardType;
  final ValueChanged<String>? onChanged;
  final bool readOnly;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (label != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: Text(label!, style: const TextStyle(color: kCryptoMuted, fontSize: 12)),
          ),
        TextField(
          controller: controller,
          keyboardType: keyboardType,
          onChanged: onChanged,
          readOnly: readOnly,
          style: const TextStyle(color: kCryptoText, fontSize: 15),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: const TextStyle(color: kCryptoMuted),
            prefixText: prefix,
            prefixStyle: const TextStyle(color: kCryptoMuted),
            suffixText: suffix,
            suffixStyle: const TextStyle(color: kCryptoPrimary, fontWeight: FontWeight.w600),
            filled: true,
            fillColor: kCryptoCard,
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: const BorderSide(color: kCryptoBorder),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: const BorderSide(color: kCryptoBorder),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: const BorderSide(color: kCryptoPrimary),
            ),
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          ),
        ),
      ],
    );
  }
}

// ── CryptoPrimaryButton ───────────────────────────────────────────────────────

class CryptoPrimaryButton extends StatelessWidget {
  const CryptoPrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.isLoading = false,
    this.color,
  });
  final String label;
  final VoidCallback? onPressed;
  final bool isLoading;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: 50,
      child: ElevatedButton(
        onPressed: isLoading ? null : onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: color ?? kCryptoPrimary,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          disabledBackgroundColor: (color ?? kCryptoPrimary).withAlpha(120),
        ),
        child: isLoading
            ? const SizedBox(width: 20, height: 20,
                child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
            : Text(label, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
      ),
    );
  }
}

// ── Section header ────────────────────────────────────────────────────────────

class SectionHeader extends StatelessWidget {
  const SectionHeader(this.title, {super.key, this.trailing});
  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 12),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(title,
            style: const TextStyle(color: kCryptoText, fontSize: 15, fontWeight: FontWeight.w700)),
        if (trailing != null) trailing!,
      ],
    ),
  );
}
