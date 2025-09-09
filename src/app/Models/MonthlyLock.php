<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MonthlyLock extends Model {
    use HasFactory;
    protected $fillable = ['user_id','year_month','locked','locked_at','locked_by'];

    public function user(){ return $this->belongsTo(User::class); }
    public function locker(){ return $this->belongsTo(User::class,'locked_by'); }
}
