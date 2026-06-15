<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningWithdrawal extends Model
{
    protected $table = 'el_withdrawals';
    protected $fillable = ['instructor_id','amount','status','notes','processed_at'];
    protected $casts = ['processed_at' => 'datetime', 'amount' => 'float'];

    public function instructor() { return $this->belongsTo(ELearningInstructor::class, 'instructor_id'); }
}
