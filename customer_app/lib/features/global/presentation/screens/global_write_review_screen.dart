import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../data/repositories/global_repository.dart';

class GlobalWriteReviewScreen extends ConsumerStatefulWidget {
  final int productId;
  const GlobalWriteReviewScreen({super.key, required this.productId});

  @override
  ConsumerState<GlobalWriteReviewScreen> createState() =>
      _GlobalWriteReviewScreenState();
}

class _GlobalWriteReviewScreenState
    extends ConsumerState<GlobalWriteReviewScreen> {
  int _rating = 0;
  final _titleCtrl = TextEditingController();
  final _bodyCtrl  = TextEditingController();
  bool _loading = false;

  @override
  void dispose() {
    _titleCtrl.dispose();
    _bodyCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_rating == 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a star rating')),
      );
      return;
    }
    setState(() => _loading = true);
    try {
      await GlobalRepository.instance.submitReview(
        widget.productId,
        _rating,
        _titleCtrl.text.trim(),
        _bodyCtrl.text.trim(),
      );
      if (mounted) context.pop(true);
    } catch (e) {
      if (mounted) {
        setState(() => _loading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(e.toString().replaceFirst('Exception: ', '')),
            backgroundColor: Colors.red.shade700,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF1A1A2E),
        elevation: 0,
        title: const Text('Write a Review',
            style: TextStyle(fontWeight: FontWeight.w800)),
        leading: IconButton(
          icon: const Icon(Icons.close_rounded),
          onPressed: () => context.pop(false),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Rating stars
            const Text('Your Rating *',
                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
            const SizedBox(height: 12),
            Row(
              children: List.generate(5, (i) {
                final star = i + 1;
                return GestureDetector(
                  onTap: () => setState(() => _rating = star),
                  child: Padding(
                    padding: const EdgeInsets.only(right: 6),
                    child: Icon(
                      star <= _rating
                          ? Icons.star_rounded
                          : Icons.star_outline_rounded,
                      size: 40,
                      color: star <= _rating
                          ? const Color(0xFFF59E0B)
                          : Colors.grey.shade300,
                    ),
                  ),
                );
              }),
            ),
            if (_rating > 0) ...[
              const SizedBox(height: 4),
              Text(
                ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'][_rating],
                style: const TextStyle(
                    color: Color(0xFFF59E0B),
                    fontWeight: FontWeight.w700,
                    fontSize: 13),
              ),
            ],
            const SizedBox(height: 24),

            // Title
            const Text('Review Title (optional)',
                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
            const SizedBox(height: 8),
            TextField(
              controller: _titleCtrl,
              maxLength: 100,
              decoration: InputDecoration(
                hintText: 'Summarize your experience',
                hintStyle:
                    TextStyle(color: Colors.grey.shade400, fontSize: 14),
                filled: true,
                fillColor: const Color(0xFFF8F9FA),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide.none,
                ),
                counterText: '',
              ),
            ),
            const SizedBox(height: 16),

            // Body
            const Text('Your Review (optional)',
                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
            const SizedBox(height: 8),
            TextField(
              controller: _bodyCtrl,
              maxLength: 2000,
              maxLines: 5,
              decoration: InputDecoration(
                hintText:
                    'What did you like or dislike? Would you recommend this product?',
                hintStyle:
                    TextStyle(color: Colors.grey.shade400, fontSize: 14),
                filled: true,
                fillColor: const Color(0xFFF8F9FA),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide.none,
                ),
                alignLabelWithHint: true,
                counterText: '',
              ),
            ),
            const SizedBox(height: 32),

            // Submit
            SizedBox(
              width: double.infinity,
              height: 52,
              child: ElevatedButton(
                onPressed: _loading || _rating == 0 ? null : _submit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF1A1A2E),
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12)),
                  elevation: 0,
                ),
                child: _loading
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                            color: Colors.white, strokeWidth: 2))
                    : const Text('Submit Review',
                        style: TextStyle(
                            fontWeight: FontWeight.w800, fontSize: 15)),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
