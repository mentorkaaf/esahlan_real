<?php
namespace App\Http\Controllers\Api\ELearning;

use App\Http\Controllers\Controller;
use App\Models\ELearningCourse;
use App\Models\ELearningEnrollment;
use App\Models\ELearningInstructorEarning;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ELearningPaymentController extends Controller
{
    public function initiatePurchase(Request $request)
    {
        $request->validate(['course_id' => 'required|integer|exists:el_courses,id']);
        $userId = auth()->id();

        $course = ELearningCourse::where('id', $request->course_id)
            ->where('status', 'published')
            ->firstOrFail();

        if ($course->is_free) {
            return response()->json(['status' => 'error', 'message' => 'This course is free, use enroll endpoint'], 422);
        }

        $existing = ELearningEnrollment::where('user_id', $userId)
            ->where('course_id', $course->id)
            ->first();
        if ($existing) {
            return response()->json(['status' => 'error', 'message' => 'Already enrolled in this course'], 422);
        }

        $price = $course->effective_price;

        // Check wallet balance
        $wallet = Wallet::where('owner_id', $userId)->where('owner_type', 'App\\Models\\User')->first();
        if (!$wallet || $wallet->balance < $price) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Insufficient wallet balance',
                'data'    => [
                    'required'  => $price,
                    'balance'   => $wallet?->balance ?? 0,
                    'shortfall' => $price - ($wallet?->balance ?? 0),
                ],
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'course_id'    => $course->id,
                'course_title' => $course->title,
                'price'        => $price,
                'currency'     => 'USD',
                'wallet_balance' => $wallet->balance,
                'payment_method' => 'wallet',
            ],
        ]);
    }

    public function verifyPurchase(Request $request)
    {
        $request->validate([
            'course_id'         => 'required|integer|exists:el_courses,id',
            'payment_method'    => 'nullable|in:wallet,waafi',
            'payment_reference' => 'nullable|string',
        ]);
        $userId = auth()->id();
        $paymentMethod = $request->input('payment_method', 'wallet');

        $course = ELearningCourse::where('id', $request->course_id)
            ->where('status', 'published')
            ->firstOrFail();

        $existing = ELearningEnrollment::where('user_id', $userId)
            ->where('course_id', $course->id)
            ->first();
        if ($existing) {
            return response()->json(['status' => 'error', 'message' => 'Already enrolled'], 422);
        }

        $price = $course->effective_price;

        DB::beginTransaction();
        try {
            if ($paymentMethod === 'waafi') {
                // Verify Waafi transaction was successful
                $ref = $request->input('payment_reference');
                if (!$ref) {
                    return response()->json(['status' => 'error', 'message' => 'Payment reference required for Waafi'], 422);
                }
                $ptx = DB::table('payment_transactions')
                    ->where('reference', $ref)
                    ->where('user_id', $userId)
                    ->where('status', 'success')
                    ->first();
                if (!$ptx) {
                    return response()->json(['status' => 'error', 'message' => 'Waafi payment not confirmed yet. Please wait and try again.'], 422);
                }
                if ((float)$ptx->amount < $price) {
                    return response()->json(['status' => 'error', 'message' => 'Waafi payment amount does not match course price'], 422);
                }
                // Mark transaction as used
                DB::table('payment_transactions')->where('id', $ptx->id)->update(['status' => 'used', 'updated_at' => now()]);
            } else {
                // Wallet payment
                $wallet = Wallet::where('owner_id', $userId)
                    ->where('owner_type', 'App\\Models\\User')
                    ->firstOrFail();
                $wallet->debit($price, 'Course purchase: ' . $course->title, 'elearning_course', $course->id);
            }

            // Create enrollment
            $enrollment = ELearningEnrollment::create([
                'user_id'    => $userId,
                'course_id'  => $course->id,
                'amount_paid'=> $price,
                'status'     => 'active',
            ]);

            // Update course student count
            $course->increment('total_students');

            // Create instructor earning record
            $commissionAmount = round($price * ($course->commission_rate / 100), 2);
            $netAmount        = $price - $commissionAmount;

            if ($course->instructor_id) {
                ELearningInstructorEarning::create([
                    'instructor_id'     => $course->instructor_id,
                    'enrollment_id'     => $enrollment->id,
                    'amount'            => $price,
                    'commission_amount' => $commissionAmount,
                    'net_amount'        => $netAmount,
                    'status'            => 'pending',
                ]);

                $course->instructor->increment('total_students');
                $course->instructor->increment('total_earnings', $netAmount);
            }

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Purchase successful. You are now enrolled!',
                'data'    => [
                    'enrollment_id' => $enrollment->id,
                    'course_slug'   => $course->slug,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Purchase failed: ' . $e->getMessage()], 500);
        }
    }
}
