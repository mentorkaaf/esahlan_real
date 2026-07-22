import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:fluttertoast/fluttertoast.dart';
import 'crypto_theme.dart';
import 'crypto_providers.dart';
import 'crypto_widgets.dart';

// ── BIP39 word list subset (256 words — enough for display, real exchange is custodial) ──
const _words = [
  'abandon','ability','able','about','above','absent','absorb','abstract',
  'absurd','abuse','access','accident','account','accuse','achieve','acid',
  'acoustic','acquire','across','act','action','actor','actress','actual',
  'adapt','add','addict','address','adjust','admit','adult','advance',
  'advice','aerobic','afford','afraid','again','agent','agree','ahead',
  'aim','air','airport','aisle','alarm','album','alcohol','alert',
  'alien','all','alley','allow','almost','alone','alpha','already',
  'also','alter','always','amateur','amazing','among','amount','amused',
  'analyst','anchor','ancient','anger','angle','angry','animal','ankle',
  'announce','annual','another','answer','antenna','antique','anxiety','apart',
  'april','arch','arctic','area','arena','argue','arm','armor',
  'army','around','arrange','arrest','arrive','arrow','art','artifact',
  'artist','artwork','ask','aspect','assault','asset','assist','assume',
  'asthma','athlete','atom','attack','attend','attitude','attract','auction',
  'audit','august','aunt','author','auto','autumn','average','avocado',
  'aware','away','awesome','awful','awkward','axis','baby','balance',
  'bamboo','banana','banner','barely','bargain','barrel','base','basic',
  'basket','battle','beach','bean','beauty','become','beef','before',
  'begin','behave','behind','believe','below','bench','benefit','best',
  'betray','better','between','beyond','bicycle','bid','bike','bind',
  'biology','bird','birth','bitter','black','blade','blame','blanket',
  'blast','bleak','bless','blind','blood','blossom','blow','blue',
  'blur','board','boat','body','boil','bomb','bone','bonus',
  'book','boost','border','boring','borrow','boss','bottom','bounce',
  'brain','brand','brave','breeze','brick','bridge','brief','bright',
  'broken','bronze','broom','brother','brown','brush','bubble','buddy',
  'budget','buffalo','build','bulb','bulk','bullet','bundle','bunker',
  'burden','burger','burst','busy','butter','buyer','buzz','cabbage',
  'cabin','cable','cactus','cage','cake','calm','camera','camp',
  'canal','cancel','candy','cannon','canvas','canyon','capable','capital',
  'captain','carbon','card','cargo','carpet','carry','cart','case',
  'cash','castle','casual','catalog','catch','cave','ceiling','celery',
  'cement','census','century','cereal','certain','chair','chaos','chapter',
  'charge','chase','cheap','check','cheese','chest','chicken','chief',
  'child','chimney','choice','choose','chronic','chuckle','chunk','cigar',
  'cinnamon','circle','citizen','city','civil','claim','clamp','clarify',
  'claw','clay','clean','clerk','clever','cliff','climb','clinic',
  'clip','clock','clog','close','cloth','cloud','clump','cluster',
  'coil','coin','collect','color','column','combine','come','comfort',
  'comic','common','company','concert','conduct','confirm','congress','connect',
  'consider','control','convince','cook','cool','copper','copy','coral',
  'core','corn','correct','cost','cotton','couch','country','couple',
  'course','cousin','cover','coyote','crack','cradle','craft','crane',
  'crash','crazy','cream','credit','creek','crew','cricket','crime',
  'crisp','critic','cross','crouch','crowd','crucial','cruel','cruise',
  'crumble','crunch','crush','cry','crystal','cube','culture','cup',
  'cupboard','curious','current','curtain','curve','cushion','cute','cycle',
  'damage','damp','dance','danger','daring','dash','daughter','dawn',
  'decade','december','decide','decline','decorate','decrease','deer','defense',
  'define','defy','degree','delay','deliver','demand','demise','denial',
  'dentist','deny','depart','depend','describe','desert','design','desk',
  'despair','destroy','detail','detect','develop','device','devote','diamond',
  'diary','dice','diesel','diet','differ','digital','dignity','dilemma',
  'dinner','dinosaur','direct','disagree','discover','disease','dish','dismiss',
  'display','distance','divert','divide','divorce','dizzy','doctor','domain',
];

List<String> _generateMnemonic() {
  final rng = Random.secure();
  final indices = List.generate(12, (_) => rng.nextInt(_words.length));
  return indices.map((i) => _words[i]).toList();
}

String _phraseHash(List<String> words) {
  // Simple djb2-style hash — sufficient for backup identification (not crypto key derivation)
  final joined = words.join(' ');
  var h = 5381;
  for (final c in joined.codeUnits) {
    h = ((h << 5) + h) ^ c;
  }
  return h.toUnsigned(32).toRadixString(16).padLeft(8, '0') + words.length.toString();
}

// ── Entry point — checks wallet then shows onboarding or main content ─────────

class CryptoWalletGate extends ConsumerWidget {
  const CryptoWalletGate({super.key, required this.child});
  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final checkAsync = ref.watch(cryptoHasWalletProvider);
    return checkAsync.when(
      loading: () => const Scaffold(
        body: Center(child: CircularProgressIndicator(color: kCryptoPrimary)),
      ),
      error: (_, __) => child, // fail open — don't block on error
      data: (hasWallet) {
        if (hasWallet) return child;
        return CryptoOnboardingScreen(onDone: () => ref.invalidate(cryptoHasWalletProvider));
      },
    );
  }
}

// ── Onboarding home — New Wallet / Open Existing ──────────────────────────────

class CryptoOnboardingScreen extends StatelessWidget {
  const CryptoOnboardingScreen({super.key, required this.onDone});
  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 28),
          child: Column(
            children: [
              const Spacer(flex: 2),
              // Logo
              Container(
                width: 100, height: 100,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: const LinearGradient(
                    colors: [kCryptoPrimary, Color(0xFF00BFA5)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  boxShadow: [BoxShadow(color: kCryptoPrimary.withAlpha(80), blurRadius: 30)],
                ),
                child: const Icon(Icons.account_balance_wallet_rounded,
                    color: Colors.white, size: 50),
              ),
              const SizedBox(height: 24),
              Text('eSahlan Crypto', style: TextStyle(
                color: cTx(context), fontSize: 28, fontWeight: FontWeight.w900)),
              const SizedBox(height: 8),
              Text('Secure, simple, and powerful\ncryptocurrency wallet',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: cMt(context), fontSize: 14, height: 1.6)),
              const Spacer(flex: 3),
              CryptoPrimaryButton(
                label: 'New Wallet',
                color: kCryptoPrimary,
                onPressed: () => Navigator.of(context).push(MaterialPageRoute(
                    builder: (_) => _CreateWalletFlow(onDone: onDone))),
              ),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: () => Navigator.of(context).push(MaterialPageRoute(
                    builder: (_) => _ImportWalletScreen(onDone: onDone))),
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                  side: const BorderSide(color: kCryptoPrimary),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: const Text('Open Existing Wallet',
                    style: TextStyle(color: kCryptoPrimary, fontWeight: FontWeight.w700)),
              ),
              const SizedBox(height: 20),
              Text('By continuing, you agree to our Terms of Service and Privacy Policy',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: cMt(context), fontSize: 11)),
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }
}

// ── Create wallet flow: security tips → mnemonic → verify ─────────────────────

class _CreateWalletFlow extends StatefulWidget {
  const _CreateWalletFlow({required this.onDone});
  final VoidCallback onDone;

  @override
  State<_CreateWalletFlow> createState() => _CreateWalletFlowState();
}

class _CreateWalletFlowState extends State<_CreateWalletFlow> {
  int _step = 0; // 0=tips, 1=phrase, 2=verify
  late final List<String> _mnemonic = _generateMnemonic();
  bool _savedCheck1 = false;
  bool _savedCheck2 = false;

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: _step == 0,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && _step > 0) setState(() => _step--);
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(_step == 0 ? 'Create New Wallet'
              : _step == 1 ? 'Recovery Phrase'
              : 'Verify Phrase'),
          leading: IconButton(
            icon: const Icon(Icons.arrow_back),
            onPressed: () => _step == 0 ? Navigator.of(context).pop() : setState(() => _step--),
          ),
        ),
        body: IndexedStack(
          index: _step,
          children: [
            _SecurityTipsPage(
              check1: _savedCheck1,
              check2: _savedCheck2,
              onCheck1: (v) => setState(() => _savedCheck1 = v),
              onCheck2: (v) => setState(() => _savedCheck2 = v),
              onNext: () => setState(() => _step = 1),
            ),
            _ShowPhrasePage(
              mnemonic: _mnemonic,
              check1: _savedCheck1,
              check2: _savedCheck2,
              onCheck1: (v) => setState(() => _savedCheck1 = v),
              onCheck2: (v) => setState(() => _savedCheck2 = v),
              onNext: () => setState(() => _step = 2),
            ),
            _VerifyPhrasePage(
              mnemonic: _mnemonic,
              onDone: widget.onDone,
            ),
          ],
        ),
      ),
    );
  }
}

// ── Step 0: Security tips ─────────────────────────────────────────────────────

class _SecurityTipsPage extends StatelessWidget {
  const _SecurityTipsPage({
    required this.check1, required this.check2,
    required this.onCheck1, required this.onCheck2, required this.onNext,
  });
  final bool check1, check2;
  final ValueChanged<bool> onCheck1, onCheck2;
  final VoidCallback onNext;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _InfoBox(
            icon: Icons.warning_amber_rounded,
            iconColor: const Color(0xFFD97706),
            title: 'Keep Your Recovery Phrase Secure',
            body: 'The next step will generate a 12-word recovery phrase that provides access to your wallet. This is your wallet\'s backup.',
          ),
          const SizedBox(height: 16),
          _InfoBox(
            icon: Icons.lock_rounded,
            iconColor: kCryptoPrimary,
            title: 'Important Security Tips',
            body: '1. Never share your recovery phrase with anyone.\n2. Store it in a secure offline location.\n3. We cannot recover your wallet if you lose your recovery phrase.',
          ),
          const Spacer(),
          CryptoPrimaryButton(
            label: 'Create',
            color: kCryptoPrimary,
            onPressed: onNext,
          ),
        ],
      ),
    );
  }
}

// ── Step 1: Show phrase ───────────────────────────────────────────────────────

class _ShowPhrasePage extends StatelessWidget {
  const _ShowPhrasePage({
    required this.mnemonic,
    required this.check1, required this.check2,
    required this.onCheck1, required this.onCheck2, required this.onNext,
  });
  final List<String> mnemonic;
  final bool check1, check2;
  final ValueChanged<bool> onCheck1, onCheck2;
  final VoidCallback onNext;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Your Recovery Phrase', style: TextStyle(
              color: cTx(context), fontSize: 22, fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          Text('Write down these 12 words in order and keep them in a secure location. You\'ll need them to recover your wallet.',
              style: TextStyle(color: cMt(context), fontSize: 13, height: 1.5)),
          const SizedBox(height: 20),
          // Phrase box
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: cCard(context),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: cBd(context)),
            ),
            child: Text(
              mnemonic.join('  '),
              textAlign: TextAlign.center,
              style: TextStyle(color: cTx(context), fontSize: 15, height: 1.8,
                  fontWeight: FontWeight.w600, letterSpacing: 0.5),
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              icon: const Icon(Icons.copy, size: 16, color: kCryptoPrimary),
              label: const Text('Copy', style: TextStyle(color: kCryptoPrimary)),
              onPressed: () {
                Clipboard.setData(ClipboardData(text: mnemonic.join(' ')));
                Fluttertoast.showToast(msg: 'Copied! Store it safely.');
              },
              style: OutlinedButton.styleFrom(
                side: const BorderSide(color: kCryptoPrimary),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
            ),
          ),
          const SizedBox(height: 16),
          // Warning
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFD97706).withAlpha(20),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFD97706).withAlpha(60)),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.warning_amber_rounded, color: Color(0xFFD97706), size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Warning', style: TextStyle(
                          color: Color(0xFFD97706), fontWeight: FontWeight.w700, fontSize: 13)),
                      const SizedBox(height: 4),
                      Text(
                        '• Anyone with access to your recovery phrase has access to your funds\n'
                        '• Never share your recovery phrase with anyone\n'
                        '• Keep it offline, secure, and private',
                        style: TextStyle(color: cMt(context), fontSize: 12, height: 1.5),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          _CheckRow(
            label: 'I have written down and stored my recovery phrase securely',
            value: check1,
            onChanged: onCheck1,
          ),
          const SizedBox(height: 8),
          _CheckRow(
            label: 'I understand that a lost recovery phrase cannot be recovered',
            value: check2,
            onChanged: onCheck2,
          ),
          const SizedBox(height: 24),
          CryptoPrimaryButton(
            label: 'Continue',
            color: kCryptoPrimary,
            onPressed: (check1 && check2) ? onNext : null,
          ),
        ],
      ),
    );
  }
}

// ── Step 2: Verify phrase ─────────────────────────────────────────────────────

class _VerifyPhrasePage extends ConsumerStatefulWidget {
  const _VerifyPhrasePage({required this.mnemonic, required this.onDone});
  final List<String> mnemonic;
  final VoidCallback onDone;

  @override
  ConsumerState<_VerifyPhrasePage> createState() => _VerifyPhrasePageState();
}

class _VerifyPhrasePageState extends ConsumerState<_VerifyPhrasePage> {
  late final List<_QuizItem> _quiz;
  final _nameCtrl = TextEditingController();
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    // Pick 3 random word indices to verify
    final rng = Random.secure();
    final indices = <int>{};
    while (indices.length < 3) {
      indices.add(rng.nextInt(12));
    }
    _quiz = indices.map((i) => _QuizItem(index: i, controller: TextEditingController())).toList()
      ..sort((a, b) => a.index.compareTo(b.index));
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    for (final q in _quiz) q.controller.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    for (final q in _quiz) {
      if (q.controller.text.trim().toLowerCase() != widget.mnemonic[q.index]) {
        Fluttertoast.showToast(msg: 'Word #${q.index + 1} is incorrect. Try again.');
        return;
      }
    }
    setState(() => _loading = true);
    try {
      final repo = ref.read(cryptoRepositoryProvider);
      await repo.setupWallet(phraseHash: _phraseHash(widget.mnemonic));
      ref.invalidate(cryptoHasWalletProvider);
      ref.invalidate(cryptoWalletProvider);
      Fluttertoast.showToast(msg: '✅ Wallet created successfully!');
      widget.onDone();
    } catch (e) {
      Fluttertoast.showToast(msg: e.toString().replaceAll('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Enter your name', style: TextStyle(
              color: cTx(context), fontSize: 16, fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          TextField(
            controller: _nameCtrl,
            style: TextStyle(color: cTx(context)),
            decoration: InputDecoration(
              hintText: 'Name',
              hintStyle: TextStyle(color: cMt(context)),
              border: UnderlineInputBorder(borderSide: BorderSide(color: cBd(context))),
              enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: cBd(context))),
            ),
          ),
          const SizedBox(height: 24),
          Text('Recovery Phrase Quiz', style: TextStyle(
              color: cTx(context), fontSize: 16, fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          Text('Let\'s make sure you\'ve written down your Recovery phrase correctly',
              style: TextStyle(color: cMt(context), fontSize: 12)),
          const SizedBox(height: 16),
          ..._quiz.map((q) => Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: TextField(
              controller: q.controller,
              style: TextStyle(color: cTx(context)),
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                labelText: 'Enter word #${q.index + 1} of Recovery Phrase',
                labelStyle: TextStyle(color: cMt(context), fontSize: 12),
                border: UnderlineInputBorder(borderSide: BorderSide(color: cBd(context))),
                enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: cBd(context))),
              ),
            ),
          )),
          const SizedBox(height: 24),
          CryptoPrimaryButton(
            label: 'Verify',
            color: kCryptoPrimary,
            onPressed: _loading ? null : _verify,
            isLoading: _loading,
          ),
        ],
      ),
    );
  }
}

class _QuizItem {
  _QuizItem({required this.index, required this.controller});
  final int index;
  final TextEditingController controller;
}

// ── Import existing wallet ────────────────────────────────────────────────────

class _ImportWalletScreen extends ConsumerStatefulWidget {
  const _ImportWalletScreen({required this.onDone});
  final VoidCallback onDone;

  @override
  ConsumerState<_ImportWalletScreen> createState() => _ImportWalletScreenState();
}

class _ImportWalletScreenState extends ConsumerState<_ImportWalletScreen> {
  final _phraseCtrl = TextEditingController();
  bool _loading = false;

  @override
  void dispose() {
    _phraseCtrl.dispose();
    super.dispose();
  }

  Future<void> _import() async {
    final words = _phraseCtrl.text.trim().split(RegExp(r'\s+'));
    if (words.length != 12) {
      Fluttertoast.showToast(msg: 'Please enter all 12 words of your recovery phrase');
      return;
    }
    setState(() => _loading = true);
    try {
      final repo = ref.read(cryptoRepositoryProvider);
      await repo.setupWallet(phraseHash: _phraseHash(words));
      ref.invalidate(cryptoHasWalletProvider);
      ref.invalidate(cryptoWalletProvider);
      Fluttertoast.showToast(msg: '✅ Wallet imported successfully!');
      widget.onDone();
    } catch (e) {
      Fluttertoast.showToast(msg: e.toString().replaceAll('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Open Existing Wallet')),
      body: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Recovery Phrase', style: TextStyle(
                color: cTx(context), fontSize: 20, fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            Text('Enter your 12-word recovery phrase to restore your wallet.',
                style: TextStyle(color: cMt(context), fontSize: 13, height: 1.5)),
            const SizedBox(height: 24),
            TextField(
              controller: _phraseCtrl,
              maxLines: 4,
              style: TextStyle(color: cTx(context), fontSize: 14, height: 1.6),
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                hintText: 'word1 word2 word3 ...',
                hintStyle: TextStyle(color: cMt(context)),
                filled: true,
                fillColor: cCard(context),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: cBd(context)),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: cBd(context)),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: kCryptoPrimary),
                ),
                contentPadding: const EdgeInsets.all(14),
              ),
            ),
            const Spacer(),
            CryptoPrimaryButton(
              label: 'Import Wallet',
              color: kCryptoPrimary,
              onPressed: _loading ? null : _import,
              isLoading: _loading,
            ),
          ],
        ),
      ),
    );
  }
}

// ── Shared helpers ────────────────────────────────────────────────────────────

class _InfoBox extends StatelessWidget {
  const _InfoBox({required this.icon, required this.iconColor, required this.title, required this.body});
  final IconData icon;
  final Color iconColor;
  final String title, body;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: cCard(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: cBd(context)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 36, height: 36,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: iconColor.withAlpha(30),
            ),
            child: Icon(icon, color: iconColor, size: 18),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: TextStyle(
                    color: cTx(context), fontWeight: FontWeight.w700, fontSize: 13)),
                const SizedBox(height: 6),
                Text(body, style: TextStyle(
                    color: cMt(context), fontSize: 12, height: 1.5)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CheckRow extends StatelessWidget {
  const _CheckRow({required this.label, required this.value, required this.onChanged});
  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => onChanged(!value),
      child: Row(
        children: [
          AnimatedContainer(
            duration: const Duration(milliseconds: 150),
            width: 22, height: 22,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: value ? kCryptoPrimary : Colors.transparent,
              border: Border.all(
                color: value ? kCryptoPrimary : cMt(context),
                width: 2,
              ),
            ),
            child: value
                ? const Icon(Icons.check, size: 13, color: Colors.white)
                : null,
          ),
          const SizedBox(width: 10),
          Expanded(child: Text(label, style: TextStyle(color: cTx(context), fontSize: 12))),
        ],
      ),
    );
  }
}
