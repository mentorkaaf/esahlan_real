// Shared widgets used across all module screens
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../core/theme/app_theme.dart';

// ── App Bar ──────────────────────────────────────────────────────────────────

AppBar moduleAppBar(BuildContext context, String module) => AppBar(
  backgroundColor: Colors.white,
  elevation: 0,
  surfaceTintColor: Colors.transparent,
  leading: IconButton(
    icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: AppColors.secondary),
    onPressed: () => context.pop(),
  ),
  title: Row(children: [
    RichText(text: const TextSpan(children: [
      TextSpan(text: 'e-', style: TextStyle(color: AppColors.primary, fontSize: 18, fontWeight: FontWeight.w900, fontFamily: 'Cairo')),
      TextSpan(text: 'Sahlan', style: TextStyle(color: AppColors.secondary, fontSize: 18, fontWeight: FontWeight.w900, fontFamily: 'Cairo')),
    ])),
    const SizedBox(width: 8),
    Text('| $module', style: const TextStyle(color: AppColors.textGrey, fontSize: 15, fontWeight: FontWeight.w500)),
  ]),
  actions: [
    IconButton(icon: const Icon(Icons.notifications_outlined, color: AppColors.secondary), onPressed: () {}),
  ],
);

// ── Search Bar ────────────────────────────────────────────────────────────────

class ModuleSearchBar extends StatelessWidget {
  final String hint;
  const ModuleSearchBar({super.key, required this.hint});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      height: 46,
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.divider),
      ),
      child: Row(children: [
        const SizedBox(width: 12),
        const Icon(Icons.search_rounded, color: AppColors.textGrey, size: 20),
        const SizedBox(width: 8),
        Expanded(child: Text(hint, style: const TextStyle(color: AppColors.textLight, fontSize: 13))),
      ]),
    );
  }
}

// ── Section Header ────────────────────────────────────────────────────────────

class ModuleSectionHeader extends StatelessWidget {
  final String title;
  final String? actionLabel;
  final VoidCallback? onTap;
  const ModuleSectionHeader({super.key, required this.title, this.actionLabel, this.onTap});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: AppColors.secondary)),
          if (actionLabel != null)
            GestureDetector(
              onTap: onTap,
              child: Text(actionLabel!, style: const TextStyle(fontSize: 13, color: AppColors.primary, fontWeight: FontWeight.w600)),
            ),
        ],
      ),
    );
  }
}

// ── Form Card ─────────────────────────────────────────────────────────────────

class ModuleFormCard extends StatelessWidget {
  final String title;
  final Widget child;
  const ModuleFormCard({super.key, required this.title, required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: AppColors.secondary)),
          const SizedBox(height: 14),
          child,
        ],
      ),
    );
  }
}

// ── Dropdown Field ────────────────────────────────────────────────────────────

class ModuleDropdownField extends StatelessWidget {
  final String label;
  final String? value;
  final List<String> items;
  final ValueChanged<String?> onChanged;

  const ModuleDropdownField({
    super.key,
    required this.label,
    this.value,
    required this.items,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.secondary)),
        const SizedBox(height: 6),
        DropdownButtonFormField<String>(
          value: value,
          onChanged: onChanged,
          items: items.map((s) => DropdownMenuItem(value: s, child: Text(s))).toList(),
          style: const TextStyle(fontSize: 14, color: AppColors.secondary, fontFamily: 'Cairo'),
          decoration: InputDecoration(
            filled: true,
            fillColor: AppColors.surface,
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.divider)),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.divider)),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.primary)),
          ),
          dropdownColor: Colors.white,
          icon: const Icon(Icons.keyboard_arrow_down_rounded, color: AppColors.secondary),
        ),
      ],
    );
  }
}

// ── Step Indicator ────────────────────────────────────────────────────────────

class ModuleStepIndicator extends StatelessWidget {
  final int current;
  final int total;
  const ModuleStepIndicator({super.key, required this.current, required this.total});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(
        children: List.generate(total, (i) {
          final done   = i <= current;
          final active = i == current;
          return Expanded(
            child: Row(
              children: [
                AnimatedContainer(
                  duration: const Duration(milliseconds: 250),
                  width: active ? 32 : 28,
                  height: active ? 32 : 28,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: done ? AppColors.primary : AppColors.surface,
                    border: Border.all(color: done ? AppColors.primary : AppColors.divider, width: 2),
                  ),
                  child: Center(
                    child: Text('${i + 1}', style: TextStyle(
                      fontSize: 12, fontWeight: FontWeight.w800,
                      color: done ? Colors.white : AppColors.textGrey)),
                  ),
                ),
                if (i < total - 1)
                  Expanded(child: Container(height: 2,
                    color: i < current ? AppColors.primary : AppColors.divider)),
              ],
            ),
          );
        }),
      ),
    );
  }
}
