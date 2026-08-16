<?php

namespace App\Models\HR;

use App\Models\Module;
use Illuminate\Database\Eloquent\Model;

class ModulePerformanceMetric extends Model
{
    protected $table = 'module_performance_metrics';

    protected $fillable = [
        'module_id', 'name', 'slug', 'description',
        'unit', 'target_value', 'weight',
        'higher_is_better', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'target_value'      => 'decimal:2',
        'weight'            => 'integer',
        'higher_is_better'  => 'boolean',
        'is_active'         => 'boolean',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function measurements()
    {
        return $this->hasMany(EmployeeModuleMetric::class, 'metric_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Compute 0–100 score from an actual value given the target. */
    public function computeScore(float $actual): float
    {
        if ($this->target_value == 0) return 0;

        if ($this->higher_is_better) {
            return min(100, round(($actual / $this->target_value) * 100, 2));
        } else {
            // Lower is better (e.g. complaint count): perfect = 0, target = max threshold
            $pct = $actual / $this->target_value;
            return max(0, round((1 - $pct) * 100, 2));
        }
    }

    public function getUnitLabelAttribute(): string
    {
        return match($this->unit) {
            'count'   => '#',
            'percent' => '%',
            'hours'   => 'h',
            'minutes' => 'min',
            'score'   => '/100',
            'rating'  => '/5',
            default   => '',
        };
    }

    // ── Per-module metric definitions ─────────────────────────────────────────

    public static function definitionsFor(string $moduleSlug): array
    {
        return static::METRICS[$moduleSlug] ?? [];
    }

    public const METRICS = [
        'efood' => [
            ['name'=>'Orders Handled',          'slug'=>'orders_handled',         'unit'=>'count',   'target'=>200, 'weight'=>25, 'hib'=>true,  'desc'=>'Total orders processed per month'],
            ['name'=>'Vendor Response Rate',     'slug'=>'vendor_response_rate',   'unit'=>'percent', 'target'=>95,  'weight'=>20, 'hib'=>true,  'desc'=>'% of vendor inquiries responded within SLA'],
            ['name'=>'Complaint Resolution',     'slug'=>'complaint_resolution',   'unit'=>'percent', 'target'=>90,  'weight'=>20, 'hib'=>true,  'desc'=>'% of complaints resolved within 24h'],
            ['name'=>'SLA Adherence',            'slug'=>'sla_adherence',          'unit'=>'percent', 'target'=>95,  'weight'=>20, 'hib'=>true,  'desc'=>'% of orders delivered within SLA window'],
            ['name'=>'Quality Score',            'slug'=>'quality_score',          'unit'=>'score',   'target'=>100, 'weight'=>15, 'hib'=>true,  'desc'=>'Customer quality rating (0–100)'],
        ],
        'eshop' => [
            ['name'=>'Products Managed',         'slug'=>'products_managed',       'unit'=>'count',   'target'=>500, 'weight'=>20, 'hib'=>true,  'desc'=>'Active products maintained'],
            ['name'=>'Order Processing Rate',    'slug'=>'order_processing_rate',  'unit'=>'percent', 'target'=>98,  'weight'=>25, 'hib'=>true,  'desc'=>'% of orders processed same day'],
            ['name'=>'Customer Satisfaction',    'slug'=>'customer_satisfaction',  'unit'=>'score',   'target'=>100, 'weight'=>25, 'hib'=>true,  'desc'=>'CSAT score (0–100)'],
            ['name'=>'Return Rate',              'slug'=>'return_rate',            'unit'=>'percent', 'target'=>5,   'weight'=>15, 'hib'=>false, 'desc'=>'% of orders returned (lower is better)'],
            ['name'=>'Stock Accuracy',           'slug'=>'stock_accuracy',         'unit'=>'percent', 'target'=>99,  'weight'=>15, 'hib'=>true,  'desc'=>'Inventory accuracy rate'],
        ],
        'eticket' => [
            ['name'=>'Tickets Resolved',         'slug'=>'tickets_resolved',       'unit'=>'count',   'target'=>150, 'weight'=>25, 'hib'=>true,  'desc'=>'Total support tickets closed per month'],
            ['name'=>'First Response Time',      'slug'=>'first_response_time',    'unit'=>'minutes', 'target'=>30,  'weight'=>25, 'hib'=>false, 'desc'=>'Average first response time in minutes'],
            ['name'=>'Resolution SLA',           'slug'=>'resolution_sla',         'unit'=>'percent', 'target'=>90,  'weight'=>20, 'hib'=>true,  'desc'=>'% of tickets resolved within SLA'],
            ['name'=>'Customer Satisfaction',    'slug'=>'customer_satisfaction',  'unit'=>'score',   'target'=>100, 'weight'=>20, 'hib'=>true,  'desc'=>'Post-resolution CSAT (0–100)'],
            ['name'=>'Escalation Rate',          'slug'=>'escalation_rate',        'unit'=>'percent', 'target'=>5,   'weight'=>10, 'hib'=>false, 'desc'=>'% of tickets escalated (lower is better)'],
        ],
        'ehealth' => [
            ['name'=>'Appointments Managed',     'slug'=>'appointments_managed',   'unit'=>'count',   'target'=>120, 'weight'=>20, 'hib'=>true,  'desc'=>'Appointments scheduled and confirmed'],
            ['name'=>'Patient Satisfaction',     'slug'=>'patient_satisfaction',   'unit'=>'score',   'target'=>100, 'weight'=>25, 'hib'=>true,  'desc'=>'Patient satisfaction score (0–100)'],
            ['name'=>'Report Accuracy',          'slug'=>'report_accuracy',        'unit'=>'percent', 'target'=>99,  'weight'=>25, 'hib'=>true,  'desc'=>'% of medical reports without errors'],
            ['name'=>'Response Time',            'slug'=>'response_time',          'unit'=>'minutes', 'target'=>60,  'weight'=>15, 'hib'=>false, 'desc'=>'Average response to patient queries (min)'],
            ['name'=>'Compliance Rate',          'slug'=>'compliance_rate',        'unit'=>'percent', 'target'=>100, 'weight'=>15, 'hib'=>true,  'desc'=>'Regulatory & protocol compliance'],
        ],
        'edata' => [
            ['name'=>'Data Accuracy',            'slug'=>'data_accuracy',          'unit'=>'percent', 'target'=>99,  'weight'=>30, 'hib'=>true,  'desc'=>'% of data entries without errors'],
            ['name'=>'Processing Volume',        'slug'=>'processing_volume',      'unit'=>'count',   'target'=>1000,'weight'=>20, 'hib'=>true,  'desc'=>'Records processed per month'],
            ['name'=>'Report Delivery',          'slug'=>'report_delivery',        'unit'=>'percent', 'target'=>100, 'weight'=>20, 'hib'=>true,  'desc'=>'% of reports delivered on schedule'],
            ['name'=>'Quality Score',            'slug'=>'quality_score',          'unit'=>'score',   'target'=>100, 'weight'=>20, 'hib'=>true,  'desc'=>'Overall data quality score'],
            ['name'=>'Compliance Rate',          'slug'=>'compliance_rate',        'unit'=>'percent', 'target'=>100, 'weight'=>10, 'hib'=>true,  'desc'=>'Data governance compliance'],
        ],
        'eparcel' => [
            ['name'=>'Parcels Processed',        'slug'=>'parcels_processed',      'unit'=>'count',   'target'=>300, 'weight'=>25, 'hib'=>true,  'desc'=>'Total parcels handled per month'],
            ['name'=>'Dispatch Accuracy',        'slug'=>'dispatch_accuracy',      'unit'=>'percent', 'target'=>99,  'weight'=>25, 'hib'=>true,  'desc'=>'% of parcels dispatched correctly'],
            ['name'=>'Delivery SLA',             'slug'=>'delivery_sla',           'unit'=>'percent', 'target'=>95,  'weight'=>25, 'hib'=>true,  'desc'=>'% of deliveries within promised window'],
            ['name'=>'Claims Resolution',        'slug'=>'claims_resolution',      'unit'=>'percent', 'target'=>90,  'weight'=>15, 'hib'=>true,  'desc'=>'% of damage/loss claims resolved < 5 days'],
            ['name'=>'Route Efficiency',         'slug'=>'route_efficiency',       'unit'=>'percent', 'target'=>90,  'weight'=>10, 'hib'=>true,  'desc'=>'On-time pickup rate'],
        ],
        'erent' => [
            ['name'=>'Listings Processed',       'slug'=>'listings_processed',     'unit'=>'count',   'target'=>50,  'weight'=>20, 'hib'=>true,  'desc'=>'New property listings verified and published'],
            ['name'=>'Property Verification',    'slug'=>'property_verification',  'unit'=>'hours',   'target'=>24,  'weight'=>25, 'hib'=>false, 'desc'=>'Average hours to verify a new listing'],
            ['name'=>'Agent Response Time',      'slug'=>'agent_response_time',    'unit'=>'minutes', 'target'=>60,  'weight'=>20, 'hib'=>false, 'desc'=>'Average response time to tenant inquiries'],
            ['name'=>'Customer Satisfaction',    'slug'=>'customer_satisfaction',  'unit'=>'score',   'target'=>100, 'weight'=>25, 'hib'=>true,  'desc'=>'Tenant & landlord satisfaction score'],
            ['name'=>'Booking Conversion Rate',  'slug'=>'booking_conversion',     'unit'=>'percent', 'target'=>30,  'weight'=>10, 'hib'=>true,  'desc'=>'% of inquiries converted to bookings'],
        ],
        'emoving' => [
            ['name'=>'Jobs Completed',           'slug'=>'jobs_completed',         'unit'=>'count',   'target'=>40,  'weight'=>25, 'hib'=>true,  'desc'=>'Moving jobs executed per month'],
            ['name'=>'Damage Claims Rate',       'slug'=>'damage_claims_rate',     'unit'=>'percent', 'target'=>2,   'weight'=>25, 'hib'=>false, 'desc'=>'% of jobs resulting in damage claims'],
            ['name'=>'Customer Satisfaction',    'slug'=>'customer_satisfaction',  'unit'=>'score',   'target'=>100, 'weight'=>25, 'hib'=>true,  'desc'=>'Post-move satisfaction score'],
            ['name'=>'Time Adherence',           'slug'=>'time_adherence',         'unit'=>'percent', 'target'=>90,  'weight'=>15, 'hib'=>true,  'desc'=>'% of jobs completed on schedule'],
            ['name'=>'Team Efficiency',          'slug'=>'team_efficiency',        'unit'=>'percent', 'target'=>85,  'weight'=>10, 'hib'=>true,  'desc'=>'Jobs completed within estimated hours'],
        ],
        'ewholesale' => [
            ['name'=>'Orders Processed',         'slug'=>'orders_processed',       'unit'=>'count',   'target'=>100, 'weight'=>20, 'hib'=>true,  'desc'=>'Wholesale orders handled per month'],
            ['name'=>'Vendor Relationship Score','slug'=>'vendor_relations',       'unit'=>'score',   'target'=>100, 'weight'=>25, 'hib'=>true,  'desc'=>'Vendor relationship quality score'],
            ['name'=>'Payment Accuracy',         'slug'=>'payment_accuracy',       'unit'=>'percent', 'target'=>100, 'weight'=>25, 'hib'=>true,  'desc'=>'% of invoices processed without errors'],
            ['name'=>'Dispatch SLA',             'slug'=>'dispatch_sla',           'unit'=>'percent', 'target'=>95,  'weight'=>15, 'hib'=>true,  'desc'=>'% of wholesale orders dispatched on SLA'],
            ['name'=>'Volume Handled (USD)',     'slug'=>'volume_handled',         'unit'=>'count',   'target'=>50000,'weight'=>15,'hib'=>true,  'desc'=>'Total transaction volume (USD) per month'],
        ],
        'egrocery' => [
            ['name'=>'Orders Picked',            'slug'=>'orders_picked',          'unit'=>'count',   'target'=>400, 'weight'=>25, 'hib'=>true,  'desc'=>'Grocery orders picked per month'],
            ['name'=>'Pick Accuracy',            'slug'=>'pick_accuracy',          'unit'=>'percent', 'target'=>99,  'weight'=>25, 'hib'=>true,  'desc'=>'% of orders picked without substitutions'],
            ['name'=>'Delivery SLA',             'slug'=>'delivery_sla',           'unit'=>'percent', 'target'=>95,  'weight'=>20, 'hib'=>true,  'desc'=>'% of deliveries within promised slot'],
            ['name'=>'Stock Management',         'slug'=>'stock_management',       'unit'=>'percent', 'target'=>98,  'weight'=>15, 'hib'=>true,  'desc'=>'Freshness compliance & waste rate'],
            ['name'=>'Customer Rating',          'slug'=>'customer_rating',        'unit'=>'rating',  'target'=>5,   'weight'=>15, 'hib'=>true,  'desc'=>'Average customer star rating (1–5)'],
        ],
        'eexchange' => [
            ['name'=>'Transactions Processed',   'slug'=>'transactions_processed', 'unit'=>'count',   'target'=>500, 'weight'=>25, 'hib'=>true,  'desc'=>'Exchange transactions handled per month'],
            ['name'=>'Processing Accuracy',      'slug'=>'processing_accuracy',    'unit'=>'percent', 'target'=>100, 'weight'=>25, 'hib'=>true,  'desc'=>'% of transactions processed without error'],
            ['name'=>'Customer Satisfaction',    'slug'=>'customer_satisfaction',  'unit'=>'score',   'target'=>100, 'weight'=>20, 'hib'=>true,  'desc'=>'Exchange customer satisfaction score'],
            ['name'=>'Compliance Rate',          'slug'=>'compliance_rate',        'unit'=>'percent', 'target'=>100, 'weight'=>20, 'hib'=>true,  'desc'=>'Regulatory compliance adherence'],
            ['name'=>'Processing Speed',         'slug'=>'processing_speed',       'unit'=>'minutes', 'target'=>5,   'weight'=>10, 'hib'=>false, 'desc'=>'Average transaction processing time (min)'],
        ],
        'elaundry' => [
            ['name'=>'Orders Completed',         'slug'=>'orders_completed',       'unit'=>'count',   'target'=>200, 'weight'=>25, 'hib'=>true,  'desc'=>'Laundry orders fulfilled per month'],
            ['name'=>'Pickup Accuracy',          'slug'=>'pickup_accuracy',        'unit'=>'percent', 'target'=>98,  'weight'=>20, 'hib'=>true,  'desc'=>'% of pickups on scheduled time'],
            ['name'=>'Delivery SLA',             'slug'=>'delivery_sla',           'unit'=>'percent', 'target'=>95,  'weight'=>25, 'hib'=>true,  'desc'=>'% of orders delivered on promised date'],
            ['name'=>'Quality Rating',           'slug'=>'quality_rating',         'unit'=>'rating',  'target'=>5,   'weight'=>20, 'hib'=>true,  'desc'=>'Customer quality rating (1–5)'],
            ['name'=>'Customer Satisfaction',    'slug'=>'customer_satisfaction',  'unit'=>'score',   'target'=>100, 'weight'=>10, 'hib'=>true,  'desc'=>'Overall CSAT score'],
        ],
        'elearning' => [
            ['name'=>'Courses Delivered',        'slug'=>'courses_delivered',      'unit'=>'count',   'target'=>20,  'weight'=>20, 'hib'=>true,  'desc'=>'Courses facilitated per month'],
            ['name'=>'Completion Rate',          'slug'=>'completion_rate',        'unit'=>'percent', 'target'=>80,  'weight'=>25, 'hib'=>true,  'desc'=>'% of enrolled students completing courses'],
            ['name'=>'Student Satisfaction',     'slug'=>'student_satisfaction',   'unit'=>'score',   'target'=>100, 'weight'=>25, 'hib'=>true,  'desc'=>'Student satisfaction score (0–100)'],
            ['name'=>'Content Quality Score',    'slug'=>'content_quality',        'unit'=>'score',   'target'=>100, 'weight'=>20, 'hib'=>true,  'desc'=>'Content quality review score'],
            ['name'=>'Attendance Rate',          'slug'=>'attendance_rate',        'unit'=>'percent', 'target'=>85,  'weight'=>10, 'hib'=>true,  'desc'=>'% of sessions with full attendance'],
        ],
    ];
}
