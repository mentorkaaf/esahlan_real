<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;

class HrWarning extends Model
{
    protected $table = 'hr_warnings';

    protected $fillable = [
        'case_id','employee_id','issued_by','type','title','body','pdf_path','acknowledged_at',
    ];

    protected $casts = ['acknowledged_at' => 'datetime'];

    public function case_()    { return $this->belongsTo(HrDisciplinaryCase::class, 'case_id'); }
    public function employee() { return $this->belongsTo(HrEmployee::class); }
    public function issuedBy() { return $this->belongsTo(HrStaff::class, 'issued_by'); }
}
