import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// Privacy Policy screen — required for GDPR (EU) and Stripe live mode
class GlobalPrivacyScreen extends StatelessWidget {
  const GlobalPrivacyScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return _LegalScreen(
      title: 'Privacy Policy',
      lastUpdated: 'August 2026',
      sections: const [
        _LegalSection('1. Introduction',
            'eSahlan Global ("we", "our", "us") respects your privacy and is committed to protecting your personal data. This Privacy Policy explains how we collect, use, and safeguard your information when you shop on eSahlan Global (global.esahlan.com).'),
        _LegalSection('2. Data We Collect', '''
• **Account data**: Name, email address, country
• **Order data**: Shipping address, order history, items purchased
• **Payment data**: We do NOT store card details. Payments are processed by Stripe and PayPal in compliance with PCI-DSS
• **Usage data**: Pages visited, products viewed (for personalization)
• **Device data**: IP address, browser type, operating system'''),
        _LegalSection('3. How We Use Your Data', '''
• To process and fulfill your orders
• To send order confirmation and shipping update emails
• To improve our product catalog and recommendations
• To prevent fraud and ensure platform security
• To comply with legal obligations'''),
        _LegalSection('4. Legal Basis (GDPR)',
            'For users in the European Economic Area (EEA), we process personal data under the following legal bases:\n• **Contract**: To fulfill your orders\n• **Legitimate interests**: Fraud prevention, service improvement\n• **Consent**: Marketing communications (you may opt out at any time)'),
        _LegalSection('5. Data Sharing',
            'We share your data with:\n• **Stripe** (payment processing) — see stripe.com/privacy\n• **PayPal** (payment processing) — see paypal.com/privacy\n• **Shipping carriers** (DHL, FedEx, USPS, etc.) — to deliver your orders\n\nWe do NOT sell your personal data to third parties.'),
        _LegalSection('6. Data Retention',
            'We retain your personal data for as long as your account is active or as needed to fulfill orders and comply with legal obligations (typically 7 years for financial records).'),
        _LegalSection('7. Your Rights (GDPR / CCPA)',
            'You have the right to:\n• **Access** the personal data we hold about you\n• **Correct** inaccurate data\n• **Delete** your account and data\n• **Object** to processing\n• **Data portability**\n\nTo exercise your rights, email: privacy@esahlan.com'),
        _LegalSection('8. Cookies',
            'We use essential cookies only (authentication session). We do not use advertising or tracking cookies. You may disable cookies in your browser settings.'),
        _LegalSection('9. Security',
            'We implement industry-standard security measures including HTTPS encryption, secure servers, and access controls. Payment data is handled exclusively by PCI-DSS compliant processors.'),
        _LegalSection('10. Contact Us',
            'For privacy-related inquiries:\nEmail: privacy@esahlan.com\nAddress: eSahlan Global, Remote-first company'),
      ],
    );
  }
}

/// Terms of Service screen
class GlobalTermsScreen extends StatelessWidget {
  const GlobalTermsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return _LegalScreen(
      title: 'Terms of Service',
      lastUpdated: 'August 2026',
      sections: const [
        _LegalSection('1. Acceptance',
            'By accessing or using eSahlan Global (global.esahlan.com), you agree to be bound by these Terms of Service. If you do not agree, please do not use our service.'),
        _LegalSection('2. Eligibility',
            'You must be at least 18 years old to use eSahlan Global. By using the service, you represent that you meet this requirement.'),
        _LegalSection('3. Orders & Payment', '''
• All prices are in USD unless stated otherwise
• We accept Stripe (Visa, Mastercard, Amex, Apple Pay, Google Pay) and PayPal
• Orders are confirmed once payment is successfully processed
• We reserve the right to cancel orders in cases of fraud, pricing errors, or stock issues
• You will receive an email confirmation for every successful order'''),
        _LegalSection('4. Shipping', '''
• We ship internationally from our warehouse or dropshipping partners
• Estimated delivery times are provided at checkout but are not guaranteed
• Customs fees, duties, and taxes for international shipments are the buyer's responsibility
• Tracking information is provided when your order ships'''),
        _LegalSection('5. Returns & Refunds', '''
• Items may be returned within 30 days of delivery in original condition
• Digital products and perishables are non-refundable
• To initiate a return, contact support@esahlan.com with your order number
• Refunds are processed within 5-10 business days to your original payment method'''),
        _LegalSection('6. Prohibited Uses', '''
You may not use eSahlan Global to:
• Purchase products for resale in bulk without prior written consent
• Engage in fraudulent transactions
• Violate any applicable laws or regulations
• Attempt to harm, disrupt, or gain unauthorized access to our systems'''),
        _LegalSection('7. Intellectual Property',
            'All content on eSahlan Global, including logos, text, and images, is owned by eSahlan or licensed to us. You may not reproduce or distribute our content without permission.'),
        _LegalSection('8. Limitation of Liability',
            'To the maximum extent permitted by law, eSahlan Global is not liable for indirect, incidental, or consequential damages arising from your use of the service. Our total liability shall not exceed the amount you paid for the relevant order.'),
        _LegalSection('9. Governing Law',
            'These Terms are governed by applicable international e-commerce law. Disputes shall first be addressed through customer support (support@esahlan.com).'),
        _LegalSection('10. Changes',
            'We may update these Terms at any time. Continued use of eSahlan Global after changes constitutes acceptance of the new Terms.'),
        _LegalSection('11. Contact',
            'Questions about these Terms?\nEmail: support@esahlan.com'),
      ],
    );
  }
}

// ── Shared layout ─────────────────────────────────────────────────────────────

class _LegalScreen extends StatelessWidget {
  final String title;
  final String lastUpdated;
  final List<_LegalSection> sections;

  const _LegalScreen({
    required this.title,
    required this.lastUpdated,
    required this.sections,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: const Color(0xFF1A1A2E),
        foregroundColor: Colors.white,
        elevation: 0,
        title: Text(title,
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20),
          onPressed: () => context.pop(),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Last updated: $lastUpdated',
                style: TextStyle(color: Colors.grey.shade500, fontSize: 12)),
            const SizedBox(height: 20),
            ...sections.map((s) => _SectionWidget(section: s)),
            const SizedBox(height: 40),
          ],
        ),
      ),
    );
  }
}

class _SectionWidget extends StatelessWidget {
  final _LegalSection section;
  const _SectionWidget({required this.section});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(section.heading,
              style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF1A1A2E))),
          const SizedBox(height: 6),
          Text(
            section.body,
            style: TextStyle(
                fontSize: 13,
                color: Colors.grey.shade700,
                height: 1.65),
          ),
        ],
      ),
    );
  }
}

class _LegalSection {
  final String heading;
  final String body;
  const _LegalSection(this.heading, this.body);
}

/// Cookie consent banner — shown once to new visitors
class GlobalGdprBanner extends StatelessWidget {
  final VoidCallback onAccept;
  final VoidCallback onViewPolicy;

  const GlobalGdprBanner({
    super.key,
    required this.onAccept,
    required this.onViewPolicy,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.all(12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFF1A1A2E),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.25),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('🍪 Cookie Notice',
              style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 14)),
          const SizedBox(height: 8),
          Text(
            'We use essential cookies to keep you signed in and process orders. We do not use advertising cookies.',
            style: TextStyle(
                color: Colors.white.withValues(alpha: 0.75),
                fontSize: 12,
                height: 1.5),
          ),
          const SizedBox(height: 14),
          Row(children: [
            TextButton(
              onPressed: onViewPolicy,
              style: TextButton.styleFrom(
                  padding: EdgeInsets.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap),
              child: Text('Privacy Policy',
                  style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.6),
                      fontSize: 12,
                      decoration: TextDecoration.underline,
                      decorationColor: Colors.white.withValues(alpha: 0.4))),
            ),
            const Spacer(),
            SizedBox(
              height: 36,
              child: ElevatedButton(
                onPressed: onAccept,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFF59E0B),
                  foregroundColor: const Color(0xFF1A1A2E),
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10)),
                ),
                child: const Text('Got it',
                    style: TextStyle(
                        fontWeight: FontWeight.w800, fontSize: 13)),
              ),
            ),
          ]),
        ],
      ),
    );
  }
}
