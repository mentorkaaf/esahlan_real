import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import '../lib/core/l10n/app_strings.dart';
import '../lib/features/home/presentation/providers/home_provider.dart';
import '../lib/features/home/presentation/screens/home_search_screen.dart';

Widget app(Widget child) => AppLangScope(
      language: 'en',
      child: MaterialApp(home: child),
    );

void main() {
  testWidgets('Home search submits trimmed text by button and keyboard',
      (tester) async {
    final queries = <String>[];
    await tester.pumpWidget(app(Scaffold(
      body: HomeSearchField(onSearch: queries.add),
    )));
    await tester.enterText(find.byType(TextField), '  rice & milk  ');
    await tester.tap(find.byIcon(Icons.search_rounded));
    expect(queries, ['rice & milk']);
    await tester.enterText(find.byType(TextField), ' pizza ');
    await tester.testTextInput.receiveAction(TextInputAction.search);
    expect(queries, ['rice & milk', 'pizza']);
  });

  testWidgets('Short queries do not request search', (tester) async {
    var requests = 0;
    await tester.pumpWidget(ProviderScope(
      overrides: [
        homeSearchProvider.overrideWith((ref, query) async {
          requests++;
          return {};
        }),
      ],
      child: app(const HomeSearchScreen(initialQuery: 'a')),
    ));
    await tester.pumpAndSettle();
    expect(requests, 0);
    expect(find.text('Enter at least 2 characters to search'), findsOneWidget);
  });

  testWidgets('Search displays loading, results and empty responses',
      (tester) async {
    final response = Completer<Map<String, dynamic>>();
    await tester.pumpWidget(ProviderScope(
      overrides: [
        homeSearchProvider.overrideWith((ref, query) {
          if (query == 'rice') return response.future;
          return Future.value({'vendors': [], 'products': []});
        }),
      ],
      child: app(const HomeSearchScreen(initialQuery: 'rice')),
    ));
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
    response.complete({
      'vendors': [
        {'id': 1, 'name': 'Rice Store', 'module_slug': 'eshop'}
      ],
      'products': [
        {'id': 2, 'name': 'Brown rice', 'vendor_id': 1}
      ],
    });
    await tester.pumpAndSettle();
    expect(find.text('Rice Store'), findsOneWidget);
    expect(find.text('Brown rice'), findsOneWidget);
    await tester.enterText(find.byType(TextField), 'missing');
    await tester.tap(find.byIcon(Icons.search_rounded));
    await tester.pumpAndSettle();
    expect(find.text('No results'), findsOneWidget);
    expect(find.text('Brown rice'), findsNothing);
  });

  testWidgets('Failed search can be retried', (tester) async {
    var requests = 0;
    await tester.pumpWidget(ProviderScope(
      overrides: [
        homeSearchProvider.overrideWith((ref, query) async {
          if (++requests == 1) throw Exception('Offline');
          return {'vendors': [], 'products': []};
        }),
      ],
      child: app(const HomeSearchScreen(initialQuery: 'rice')),
    ));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Retry'));
    await tester.pumpAndSettle();
    expect(requests, 2);
    expect(find.text('No results'), findsOneWidget);
  });
}
