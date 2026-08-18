import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../ui/eg_theme.dart';
import '../../ui/eg_widgets.dart';
import '../providers/egrocery_providers.dart';

class EGSearchScreen extends ConsumerStatefulWidget {
  const EGSearchScreen({super.key});

  @override
  ConsumerState<EGSearchScreen> createState() => _EGSearchScreenState();
}

class _EGSearchScreenState extends ConsumerState<EGSearchScreen> {
  final _ctrl = TextEditingController();
  Timer? _debounce;
  String _query = '';
  List<String> _recentSearches = [];
  bool _showResults = false;

  @override
  void initState() {
    super.initState();
    _loadRecent();
  }

  @override
  void dispose() {
    _ctrl.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  Future<void> _loadRecent() async {
    final prefs = await SharedPreferences.getInstance();
    setState(() => _recentSearches = prefs.getStringList('eg_recent_searches') ?? []);
  }

  Future<void> _saveRecent(String q) async {
    final updated = [q, ..._recentSearches.where((s) => s != q)].take(8).toList();
    setState(() => _recentSearches = updated);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setStringList('eg_recent_searches', updated);
  }

  void _onSearch(String q) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), () {
      setState(() { _query = q.trim(); _showResults = _query.isNotEmpty; });
    });
  }

  void _submitSearch(String q) {
    if (q.trim().isEmpty) return;
    _saveRecent(q.trim());
    context.push('/egrocery/products?search=${Uri.encodeComponent(q.trim())}&title=Results for "$q"');
  }

  @override
  Widget build(BuildContext context) {
    final suggestsAsync = _query.length >= 2 ? ref.watch(egSuggestProvider(_query)) : null;

    return Scaffold(
      backgroundColor: EGTheme.bg,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: TextField(
          controller: _ctrl,
          autofocus: true,
          onChanged: _onSearch,
          onSubmitted: _submitSearch,
          decoration: const InputDecoration(
            hintText: 'Search groceries...',
            hintStyle: TextStyle(color: EGTheme.textGrey),
            border: InputBorder.none,
          ),
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
        ),
        actions: [
          if (_ctrl.text.isNotEmpty)
            IconButton(
              icon: const Icon(Icons.close, color: EGTheme.textGrey),
              onPressed: () { _ctrl.clear(); setState(() { _query = ''; _showResults = false; }); },
            ),
        ],
      ),
      body: !_showResults
          ? _RecentSearches(searches: _recentSearches, onTap: (q) {
              _ctrl.text = q;
              _onSearch(q);
            })
          : suggestsAsync == null
              ? const SizedBox()
              : suggestsAsync.when(
                  loading: () => const Center(child: CircularProgressIndicator(color: EGTheme.orange)),
                  error: (_, __) => const SizedBox(),
                  data: (items) => items.isEmpty
                      ? EGEmptyState(emoji: '🔍', title: 'No results for "$_query"')
                      : ListView.builder(
                          itemCount: items.length,
                          itemBuilder: (_, i) {
                            final item = items[i] as Map;
                            final isProduct = item['type'] == 'product';
                            return ListTile(
                              leading: Icon(isProduct ? Icons.shopping_bag_outlined : Icons.category_outlined, color: EGTheme.orange),
                              title: Text(item['label'] ?? '', style: const TextStyle(fontWeight: FontWeight.w600)),
                              subtitle: Text(isProduct ? 'Product' : 'Category', style: EGTheme.caption),
                              onTap: () {
                                _saveRecent(item['label'] ?? '');
                                if (isProduct) {
                                  context.push('/egrocery/product/${item['slug']}');
                                } else {
                                  context.push('/egrocery/products?category=${item['id']}&title=${item['label']}');
                                }
                              },
                            );
                          },
                        ),
                ),
    );
  }
}

class _RecentSearches extends StatelessWidget {
  final List<String> searches;
  final ValueChanged<String> onTap;
  const _RecentSearches({required this.searches, required this.onTap});

  @override
  Widget build(BuildContext context) => searches.isEmpty
      ? EGEmptyState(emoji: '🔍', title: 'Search for groceries', subtitle: 'Find your favorite products')
      : Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Padding(
            padding: EdgeInsets.fromLTRB(16, 16, 16, 8),
            child: Text('Recent Searches', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: EGTheme.textGrey)),
          ),
          ...searches.map((s) => ListTile(
                leading: const Icon(Icons.history, color: EGTheme.textGrey),
                title: Text(s, style: const TextStyle(fontWeight: FontWeight.w600)),
                onTap: () => onTap(s),
              )),
        ]);
}
