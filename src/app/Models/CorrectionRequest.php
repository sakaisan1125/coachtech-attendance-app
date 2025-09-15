<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CorrectionRequest extends Model {
    use HasFactory;
    protected $fillable = [
        'attendance_id','requested_by','status',
        'requested_clock_in_at','requested_clock_out_at','requested_notes',
        'approved_by','approved_at','rejected_reason'
    ];

    protected $casts = [
        'requested_clock_in_at' => 'datetime',
        'requested_clock_out_at' => 'datetime',
    ];

    public function attendance(){ return $this->belongsTo(Attendance::class); }
    public function requester(){ return $this->belongsTo(User::class,'requested_by'); }
    public function approver(){ return $this->belongsTo(User::class,'approved_by'); }
    public function breaks(){ return $this->hasMany(CorrectionRequestBreak::class); }
}
