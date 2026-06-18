import 'dart:async';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_theme.dart';
import '../../data/models/elearning_models.dart';
import '../../data/services/elearning_api_service.dart';
import '../providers/elearning_provider.dart';

class QuizScreen extends ConsumerStatefulWidget {
  final int quizId;
  const QuizScreen({super.key, required this.quizId});

  @override
  ConsumerState<QuizScreen> createState() => _QuizScreenState();
}

class _QuizScreenState extends ConsumerState<QuizScreen> {
  final _svc = ELearningApiService.create();
  int _currentIndex = 0;
  final Map<int, String> _answers = {};
  bool _submitting = false;
  QuizResult? _result;
  Timer? _timer;
  int _secondsLeft = 0;
  DateTime? _startTime;

  @override
  void initState() {
    super.initState();
    _startTime = DateTime.now();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _startTimer(int limitMinutes) {
    _secondsLeft = limitMinutes * 60;
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (_secondsLeft <= 0) {
        _timer?.cancel();
        _submit(ref.read(quizProvider(widget.quizId)).value!);
      } else {
        setState(() => _secondsLeft--);
      }
    });
  }

  Future<void> _submit(QuizData quiz) async {
    if (_submitting) return;
    setState(() => _submitting = true);
    _timer?.cancel();
    try {
      final timeTaken = DateTime.now().difference(_startTime!).inSeconds;
      final result = await _svc.submitQuiz(widget.quizId, _answers, timeTaken);
      setState(() { _result = result; _submitting = false; });
    } catch (e) {
      setState(() => _submitting = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString()), backgroundColor: Colors.red),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final quizAsync = ref.watch(quizProvider(widget.quizId));

    return Scaffold(
      
      appBar: AppBar(
        
        elevation: 0,
        title: Text('Quiz', style: TextStyle(color: context.colors.navyText, fontWeight: FontWeight.w800)),
        leading: IconButton(
          icon: Icon(Icons.arrow_back_ios_new_rounded, color: context.colors.navyText),
          onPressed: () => context.pop(),
        ),
      ),
      body: quizAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: AppColors.primary)),
        error: (e, _) => Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          const Icon(Icons.error_outline, size: 48, color: Colors.grey),
          const SizedBox(height: 12),
          Text(e.toString(), textAlign: TextAlign.center),
          const SizedBox(height: 12),
          ElevatedButton(onPressed: () => context.pop(), child: const Text('Go Back')),
        ])),
        data: (quiz) {
          if (_result != null) {
            return _ResultView(result: _result!, quiz: quiz, onRetry: () => setState(() { _result = null; _answers.clear(); _currentIndex = 0; _startTime = DateTime.now(); }));
          }

          // Start timer on first build
          if (quiz.timeLimitMinutes != null && _timer == null) {
            WidgetsBinding.instance.addPostFrameCallback((_) => _startTimer(quiz.timeLimitMinutes!));
          }

          final question = quiz.questions[_currentIndex];
          final answered  = _answers[question.id];

          return Column(
            children: [
              // ── Header ─────────────────────────────────────────────────────
              Container(
                color: Colors.white,
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                      Text(quiz.title, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: context.colors.navyText)),
                      if (quiz.timeLimitMinutes != null)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: _secondsLeft < 60 ? Colors.red[50] : Colors.blue[50],
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Row(children: [
                            Icon(Icons.timer_rounded, size: 14, color: _secondsLeft < 60 ? Colors.red : Colors.blue),
                            const SizedBox(width: 4),
                            Text(
                              '${(_secondsLeft ~/ 60).toString().padLeft(2, '0')}:${(_secondsLeft % 60).toString().padLeft(2, '0')}',
                              style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: _secondsLeft < 60 ? Colors.red : Colors.blue),
                            ),
                          ]),
                        ),
                    ]),
                    const SizedBox(height: 10),
                    Row(children: [
                      Text('Question ${_currentIndex + 1} of ${quiz.questions.length}', style: TextStyle(color: Colors.grey[500], fontSize: 12)),
                      const SizedBox(width: 8),
                      Expanded(
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(4),
                          child: LinearProgressIndicator(
                            value: (_currentIndex + 1) / quiz.questions.length,
                            backgroundColor: Colors.grey[200],
                            valueColor: const AlwaysStoppedAnimation<Color>(AppColors.primary),
                            minHeight: 6,
                          ),
                        ),
                      ),
                    ]),
                  ],
                ),
              ),

              // ── Question ───────────────────────────────────────────────────
              Expanded(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(18),
                        decoration: BoxDecoration(
                          color: context.colors.cardBg,
                          borderRadius: BorderRadius.circular(14),
                          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)],
                        ),
                        child: Text(question.question, style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: context.colors.navyText, height: 1.5)),
                      ),
                      const SizedBox(height: 20),

                      // Options
                      if (question.type == 'true_false') ...[
                        for (final opt in ['True', 'False'])
                          _OptionTile(
                            label: opt,
                            selected: answered == opt,
                            onTap: () => setState(() => _answers[question.id] = opt),
                          ),
                      ] else if (question.type == 'mcq' && question.options.isNotEmpty)
                        ...question.options.asMap().entries.map((entry) {
                          final label = String.fromCharCode(65 + entry.key) + '. ' + entry.value;
                          return _OptionTile(
                            label: label,
                            selected: answered == entry.value,
                            onTap: () => setState(() => _answers[question.id] = entry.value),
                          );
                        })
                      else ...[
                        Container(
                          decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(12)),
                          child: TextField(
                            maxLines: 4,
                            onChanged: (v) => _answers[question.id] = v,
                            decoration: const InputDecoration(
                              hintText: 'Type your answer here…',
                              border: InputBorder.none,
                              contentPadding: EdgeInsets.all(16),
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),

              // ── Navigation ─────────────────────────────────────────────────
              Container(
                color: Colors.white,
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    if (_currentIndex > 0)
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => setState(() => _currentIndex--),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: context.colors.navyText,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                          child: const Text('Previous'),
                        ),
                      ),
                    if (_currentIndex > 0) const SizedBox(width: 12),
                    Expanded(
                      child: FilledButton(
                        onPressed: _submitting ? null : () {
                          if (_currentIndex < quiz.questions.length - 1) {
                            setState(() => _currentIndex++);
                          } else {
                            _submit(quiz);
                          }
                        },
                        style: FilledButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        child: _submitting
                            ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                            : Text(_currentIndex < quiz.questions.length - 1 ? 'Next' : 'Submit Quiz'),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

// ── Option Tile ───────────────────────────────────────────────────────────────
class _OptionTile extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;
  const _OptionTile({required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: selected ? AppColors.primary.withValues(alpha: 0.1) : Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: selected ? AppColors.primary : Colors.grey[300]!),
        ),
        child: Row(children: [
          Container(
            width: 22, height: 22,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: selected ? AppColors.primary : Colors.transparent,
              border: Border.all(color: selected ? AppColors.primary : Colors.grey[400]!),
            ),
            child: selected ? const Icon(Icons.check_rounded, color: Colors.white, size: 14) : null,
          ),
          const SizedBox(width: 12),
          Expanded(child: Text(label, style: TextStyle(fontSize: 14, fontWeight: selected ? FontWeight.w600 : FontWeight.normal, color: selected ? AppColors.primary : AppColors.secondary))),
        ]),
      ),
    );
  }
}

// ── Result View ───────────────────────────────────────────────────────────────
class _ResultView extends StatelessWidget {
  final QuizResult result;
  final QuizData quiz;
  final VoidCallback onRetry;
  const _ResultView({required this.result, required this.quiz, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          const SizedBox(height: 20),
          Icon(
            result.passed ? Icons.check_circle_rounded : Icons.cancel_rounded,
            size: 80,
            color: result.passed ? Colors.green : Colors.red,
          ),
          const SizedBox(height: 16),
          Text(
            result.passed ? 'Quiz Passed!' : 'Quiz Failed',
            style: TextStyle(
              fontSize: 24, fontWeight: FontWeight.w900,
              color: result.passed ? Colors.green : Colors.red,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            '${result.score.toStringAsFixed(1)}% (${result.correctAnswers}/${result.totalQuestions} correct)',
            style: TextStyle(fontSize: 16, color: Colors.grey[600]),
          ),
          const SizedBox(height: 24),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: context.colors.cardBg,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Row(mainAxisAlignment: MainAxisAlignment.spaceAround, children: [
              _ResultStat(label: 'Score', value: '${result.score.toStringAsFixed(1)}%', color: result.passed ? Colors.green : Colors.red),
              _ResultStat(label: 'Pass Mark', value: '${result.passScore}%', color: AppColors.primary),
              _ResultStat(label: 'Attempts', value: '${result.attemptsUsed}/${result.attemptsMax}', color: context.colors.navyText),
            ]),
          ),
          const SizedBox(height: 24),

          // Answer review
          ...result.answers.map((a) {
            final isCorrect = a['is_correct'] == true;
            return Container(
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: context.colors.cardBg,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: isCorrect ? Colors.green[200]! : Colors.red[200]!),
              ),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Icon(isCorrect ? Icons.check_circle_rounded : Icons.cancel_rounded,
                      color: isCorrect ? Colors.green : Colors.red, size: 18),
                  const SizedBox(width: 8),
                  Expanded(child: Text('Your answer: ${a['user_answer'] ?? 'Not answered'}',
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13))),
                ]),
                if (!isCorrect) ...[
                  const SizedBox(height: 4),
                  Text('Correct: ${a['correct_answer']}', style: TextStyle(fontSize: 12, color: Colors.green[700])),
                ],
                if (a['explanation'] != null) ...[
                  const SizedBox(height: 6),
                  Text('Explanation: ${a['explanation']}', style: TextStyle(fontSize: 11, color: Colors.grey[600], fontStyle: FontStyle.italic)),
                ],
              ]),
            );
          }),

          SizedBox(height: 20),
          Row(children: [
            if (!result.passed && result.attemptsUsed < result.attemptsMax)
              Expanded(
                child: OutlinedButton(
                  onPressed: onRetry,
                  style: OutlinedButton.styleFrom(foregroundColor: context.colors.navyText, padding: const EdgeInsets.symmetric(vertical: 14), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                  child: const Text('Retry Quiz'),
                ),
              ),
            if (!result.passed && result.attemptsUsed < result.attemptsMax) const SizedBox(width: 12),
            Expanded(
              child: FilledButton(
                onPressed: () => context.pop(),
                style: FilledButton.styleFrom(backgroundColor: AppColors.primary, padding: const EdgeInsets.symmetric(vertical: 14), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                child: const Text('Back to Lesson'),
              ),
            ),
          ]),
          const SizedBox(height: 80),
        ],
      ),
    );
  }
}

class _ResultStat extends StatelessWidget {
  final String label;
  final String value;
  final Color color;
  const _ResultStat({required this.label, required this.value, required this.color});

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      Text(value, style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: color)),
      Text(label, style: TextStyle(fontSize: 12, color: Colors.grey[500])),
    ]);
  }
}
