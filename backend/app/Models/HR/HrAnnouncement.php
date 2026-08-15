<?php

namespace App\Models\HR;

use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Model;

class HrAnnouncement extends Model
{
    use HrAuditable;

    protected $table = 'hr_announcements';

    protected $fillable = ['title','body','audience','department_id','created_by','published_at'];

    protected $casts = ['published_at' => 'datetime'];

    public function creator()    { return $this->belongsTo(HrStaff::class, 'created_by'); }
    public function department() { return $this->belongsTo(HrDepartment::class); }

    public function isPublished(): bool { return $this->published_at && $this->published_at->isPast(); }
}
