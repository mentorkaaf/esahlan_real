import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/l10n/app_strings.dart';
import '../providers/home_provider.dart';

/// Shared by the home page and the results page.
class HomeSearchField extends StatefulWidget {
  final String initialQuery;
  final ValueChanged<String> onSearch;
  const HomeSearchField({
    super.key,
    this.initialQuery = '',
    required this.onSearch,
  });

  @override
  State<HomeSearchField> createState() => _HomeSearchFieldState();
}

class _HomeSearchFieldState extends State<HomeSearchField> {
  late final TextEditingController _controller =
      TextEditingController(text: widget.initialQuery);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _submit() {
    FocusScope.of(context).unfocus();
    widget.onSearch(_controller.text.trim());
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return TextField(
      controller: _controller,
      textInputAction: TextInputAction.search,
      onSubmitted: (_) => _submit(),
      style: const TextStyle(fontSize: 13),
      decoration: InputDecoration(
        hintText: l.searchHint,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
        suffixIcon: IconButton(
          tooltip: l.search,
          icon: const Icon(Icons.search_rounded),
          onPressed: _submit,
        ),
      ),
    );
  }
}

class HomeSearchScreen extends ConsumerStatefulWidget {
  final String initialQuery;
  const HomeSearchScreen({super.key, this.initialQuery = ''});

  @override
  ConsumerState<HomeSearchScreen> createState() => _HomeSearchScreenState();
}

class _HomeSearchScreenState extends ConsumerState<HomeSearchScreen> {
  late String _query = widget.initialQuery.trim();

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(l.search)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: HomeSearchField(
              initialQuery: _query,
              onSearch: (query) {
                if (query == _query && query.runes.length >= 2) {
                  ref.invalidate(homeSearchProvider(query));
                }
                setState(() => _query = query);
              },
            ),
          ),
          Expanded(
            child: _query.runes.length < 2
                ? Center(child: Text(l.tr('searchMinLength')))
                : ref.watch(homeSearchProvider(_query)).when(
                      loading: () =>
                          const Center(child: CircularProgressIndicator()),
                      error: (_, __) => Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(l.somethingWentWrong),
                            TextButton(
                              onPressed: () =>
                                  ref.invalidate(homeSearchProvider(_query)),
                              child: Text(l.retry),
                            ),
                          ],
                        ),
                      ),
                      data: (data) {
                        final vendors = (data['vendors'] as List? ?? [])
                            .cast<Map<String, dynamic>>();
                        final products = (data['products'] as List? ?? [])
                            .cast<Map<String, dynamic>>();
                        if (vendors.isEmpty && products.isEmpty) {
                          return Center(child: Text(l.noResults));
                        }
                        return ListView(
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          children: [
                            if (vendors.isNotEmpty)
                              _heading(l.tr('searchVendors')),
                            for (final vendor in vendors)
                              ListTile(
                                leading: const Icon(Icons.storefront_outlined),
                                title: Text(vendor['name']?.toString() ?? ''),
                                subtitle: Text(
                                    vendor['module_slug']?.toString() ?? ''),
                                trailing: const Icon(Icons.chevron_right),
                                onTap: () => context.push(_vendorPath(vendor)),
                              ),
                            if (products.isNotEmpty) _heading(l.products),
                            for (final product in products)
                              ListTile(
                                leading:
                                    const Icon(Icons.shopping_bag_outlined),
                                title: Text(product['name']?.toString() ?? ''),
                                subtitle: Text(
                                  product['vendor']?['name']?.toString() ?? '',
                                ),
                                trailing: const Icon(Icons.chevron_right),
                                onTap: () {
                                  final vendor = Map<String, dynamic>.from(
                                    product['vendor'] as Map? ?? {},
                                  );
                                  vendor['id'] ??= product['vendor_id'];
                                  // Core catalog products belong to these vendors,
                                  // not the separate module product catalogs.
                                  context.push(_vendorPath(vendor));
                                },
                              ),
                          ],
                        );
                      },
                    ),
          ),
        ],
      ),
    );
  }

  Widget _heading(String title) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Text(title, style: Theme.of(context).textTheme.titleMedium),
      );

  String _vendorPath(Map<String, dynamic> vendor) =>
      (vendor['module_slug'] == 'efood' ? '/efood/restaurant/' : '/vendor/') +
      vendor['id'].toString();
}
