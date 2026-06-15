<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningInstructorEarning extends Model
{
    protected $table = 'el_instructor_earnings';
    protected $fillable = [
        'instructor_id','enrollment_id','amount','commission_amount','net_amount','status','released_at',
    ];
    protected $casts = [
        'amount'            => 'float',
        'commission_amount' => 'float',
        'net_amount'        => 'float',
        'released_at'       => 'datetime',
    ];

    public function instructor() { return $this->belongsTo(ELearningInstructor::class, 'instructor_id'); }
    public function enrollment() { return $this->belongsTo(ELearningEnrollment::class, 'enrollment_id'); }
}
