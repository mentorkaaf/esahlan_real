<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningCertificate extends Model
{
    protected $table = 'el_certificates';
    protected $fillable = ['user_id','course_id','enrollment_id','certificate_number','issued_at','pdf_path'];
    protected $casts = ['issued_at' => 'datetime'];

    public function user()       { return $this->belongsTo(User::class); }
    public function course()     { return $this->belongsTo(ELearningCourse::class, 'course_id'); }
    public function enrollment() { return $this->belongsTo(ELearningEnrollment::class, 'enrollment_id'); }
}
