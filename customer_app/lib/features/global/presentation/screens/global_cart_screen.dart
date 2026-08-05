import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/global_provider.dart';

class GlobalCartScreen extends ConsumerWidget {
  const GlobalCartScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cartAsync = ref.watch(globalCartProvider);
    final auth = ref.watch(globalAuthProvider).valueOrNull;

    if (auth == null) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('My Cart'),
          backgroundColor: const Color(0xFF1A1A2E),
          foregroundColor: Colors.white,
        ),
        body: Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.shopping_cart_outlined,
                size: 64, color: Colors.grey),
            const SizedBox(height: 16),
            const Text('Sign in to view your cart',
                style: TextStyle(fontSize: 16, color: Colors.grey)),
            const SizedBox(height: 20),
            ElevatedButton(
              onPressed: () => context.push('/global/auth'),
              style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF1A1A2E),
                  foregroundColor: Colors.white),
              child: const Text('Sign In'),
            ),
          ]),
        ),
      );
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF0F2F5),
      appBar: AppBar(
        title: cartAsync.when(
          data: (cart) => Text('My Cart (${cart.count})',
              style: const TextStyle(fontWeight: FontWeight.w700)),
          loading: () => const Text('My Cart'),
          error: (_, __) => const Text('My Cart'),
        ),
        backgroundColor: const Color(0xFF1A1A2E),
        foregroundColor: Colors.white,
        actions: [
          if (cartAsync.valueOrNull?.items.isNotEmpty == true)
            TextButton(
              onPressed: () async {
                final ok = await showDialog<bool>(
                  context: context,
                  builder: (_) => AlertDialog(
                    title: const Text('Clear cart?'),
                    actions: [
                      TextButton(
                          onPressed: () => Navigator.pop(context, false),
                          child: const Text('Cancel')),
                      ElevatedButton(
                          onPressed: () => Navigator.pop(context, true),
                          child: const Text('Clear')),
                    ],
                  ),
                );
                if (ok == true) {
                  ref.read(globalCartProvider.notifier).clear();
                }
              },
              child: const Text('Clear',
                  style: TextStyle(color: Colors.white70, fontSize: 13)),
            ),
        ],
      ),
      body: cartAsync.when(
        data: (cart) {
          if (cart.items.isEmpty) {
            return Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.shopping_cart_outlined,
                    size: 72, color: Colors.grey),
                const SizedBox(height: 16),
                const Text('Your cart is empty',
                    style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w600,
                        color: Colors.grey)),
                const SizedBox(height: 8),
                const Text('Add some products to get started',
                    style: TextStyle(color: Colors.grey, fontSize: 13)),
                const SizedBox(height: 24),
                ElevatedButton.icon(
                  onPressed: () => context.go('/global'),
                  icon: const Icon(Icons.shopping_bag_outlined),
                  label: const Text('Start Shopping'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF1A1A2E),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(
                        horizontal: 24, vertical: 12),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12)),
                  ),
                ),
              ]),
            );
          }

          return Column(children: [
            Expanded(
              child: ListView.builder(
                padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
                itemCount: cart.items.length,
                itemBuilder: (ctx, i) {
                  final item = cart.items[i];
                  return Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Row(children: [
                        // Thumbnail
                        ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: item.thumbnail != null
                              ? Image.network(item.thumbnail!,
                                  width: 72,
                                  height: 72,
                                  fit: BoxFit.cover,
                                  errorBuilder: (_, __, ___) =>
                                      Container(
                                          width: 72,
                                          height: 72,
                                          color: Colors.grey.shade100,
                                          child: const Icon(
                                              Icons.image_outlined,
                                              color: Colors.grey)))
                              : Container(
                                  width: 72,
                                  height: 72,
                                  color: Colors.grey.shade100,
                                  child: const Icon(Icons.image_outlined,
                                      color: Colors.grey)),
                        ),
                        const SizedBox(width: 12),

                        // Info
                        Expanded(
                          child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                            Text(item.name,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600)),
                            if (item.variant != null) ...[
                              const SizedBox(height: 2),
                              Text(item.variant!,
                                  style: TextStyle(
                                      fontSize: 11,
                                      color: Colors.grey.shade500)),
                            ],
                            const SizedBox(height: 8),
                            Row(
                              mainAxisAlignment:
                                  MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                    '\$${item.subtotal.toStringAsFixed(2)}',
                                    style: const TextStyle(
                                        fontSize: 15,
                                        fontWeight: FontWeight.w800,
                                        color: Color(0xFF1A1A2E))),
                                Row(children: [
                                  _SmallQtyBtn(
                                    icon: Icons.remove,
                                    onTap: () {
                                      if (item.quantity > 1) {
                                        ref
                                            .read(globalCartProvider
                                                .notifier)
                                            .updateItem(item.id,
                                                item.quantity - 1);
                                      } else {
                                        ref
                                            .read(globalCartProvider
                                                .notifier)
                                            .remove(item.id);
                                      }
                                    },
                                  ),
                                  Padding(
                                    padding: const EdgeInsets.symmetric(
                                        horizontal: 12),
                                    child: Text('${item.quantity}',
                                        style: const TextStyle(
                                            fontWeight: FontWeight.w700,
                                            fontSize: 14)),
                                  ),
                                  _SmallQtyBtn(
                                    icon: Icons.add,
                                    onTap: () => ref
                                        .read(globalCartProvider.notifier)
                                        .updateItem(item.id, item.quantity + 1),
                                  ),
                                ]),
                              ],
                            ),
                          ]),
                        ),

                        // Delete
                        IconButton(
                          icon: const Icon(Icons.delete_outline,
                              color: Colors.red, size: 20),
                          onPressed: () => ref
                              .read(globalCartProvider.notifier)
                              .remove(item.id),
                        ),
                      ]),
                    ),
                  );
                },
              ),
            ),

            // Summary + Checkout
            Container(
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                      color: Colors.black.withOpacity(0.06),
                      blurRadius: 10,
                      offset: const Offset(0, -3))
                ],
              ),
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
              child: Column(children: [
                Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                  const Text('Subtotal',
                      style: TextStyle(
                          fontSize: 14, color: Colors.grey)),
                  Text('\$${cart.subtotal.toStringAsFixed(2)}',
                      style: const TextStyle(
                          fontSize: 14, fontWeight: FontWeight.w600)),
                ]),
                const SizedBox(height: 6),
                Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: const [
                  Text('Shipping',
                      style: TextStyle(fontSize: 14, color: Colors.grey)),
                  Text('Calculated at checkout',
                      style:
                          TextStyle(fontSize: 12, color: Colors.grey)),
                ]),
                const Divider(height: 20),
                Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                  const Text('Total',
                      style: TextStyle(
                          fontSize: 16, fontWeight: FontWeight.w800)),
                  Text('\$${cart.subtotal.toStringAsFixed(2)}+',
                      style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                          color: Color(0xFF1A1A2E))),
                ]),
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  height: 50,
                  child: ElevatedButton(
                    onPressed: () => context.push('/global/checkout'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFFF59E0B),
                      foregroundColor: const Color(0xFF1A1A2E),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text('Proceed to Checkout →',
                        style: TextStyle(
                            fontWeight: FontWeight.w800, fontSize: 15)),
                  ),
                ),
              ]),
            ),
          ]);
        },
        loading: () =>
            const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(e.toString())),
      ),
    );
  }
}

class _SmallQtyBtn extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  const _SmallQtyBtn({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 28,
        height: 28,
        decoration: BoxDecoration(
          color: const Color(0xFFF0F2F5),
          borderRadius: BorderRadius.circular(6),
        ),
        child: Icon(icon, size: 14, color: const Color(0xFF1A1A2E)),
      ),
    );
  }
}
