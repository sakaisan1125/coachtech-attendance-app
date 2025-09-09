<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CorrectionRequestBreak extends Model {
    use HasFactory;
    protected $fillable = ['correction_request_id','index','requested_break_start_at','requested_break_end_at'];

    public function correctionRequest(){ return $this->belongsTo(CorrectionRequest::class); }
}
