<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable {
    use HasFactory;
    protected $fillable = ['name','email','password','role'];
    protected $hidden = ['password','remember_token'];

    public function attendances(){ return $this->hasMany(Attendance::class); }
}
