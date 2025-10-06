<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in_at',
        'clock_out_at',
        'status',
        'notes',
        'locked',
        'locked_at',
    ];

    protected $casts = [
        'work_date'   => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at'=> 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(BreakModel::class, 'attendance_id');
    }

    public function correctionRequests(): HasMany
    {
        return $this->hasMany(CorrectionRequest::class, 'attendance_id');
    }

    public function computeEffectiveBreakSegments(): Collection
    {
        if ($this->relationLoaded('breaks') || $this->breaks()->exists()) {
            $loaded = $this->relationLoaded('breaks') ? $this->breaks : $this->breaks()->get();
            $seg = $loaded
                ->filter(fn($b) => $b->break_start_at && $b->break_end_at && $b->break_end_at > $b->break_start_at)
                ->sortBy('break_start_at')
                ->map(fn($b) => [
                    'start'   => $b->break_start_at->format('H:i'),
                    'end'     => $b->break_end_at->format('H:i'),
                    'minutes' => $b->break_start_at->diffInMinutes($b->break_end_at),
                ])->values();
            if ($seg->isNotEmpty()) {
                return $seg;
            }
        }

        if (!$this->relationLoaded('correctionRequests')) {
            $this->load(['correctionRequests.breaks']);
        }

        $cr = $this->correctionRequests
            ->whereIn('status', ['pending', 'approved'])
            ->sortByDesc('id')
            ->first();

        if ($cr && $cr->relationLoaded('breaks')) {
            $seg = $cr->breaks
                ->filter(fn($b) => $b->requested_break_start_at && $b->requested_break_end_at && $b->requested_break_end_at > $b->requested_break_start_at)
                ->sortBy('requested_break_start_at')
                ->map(fn($b) => [
                    'start'   => $b->requested_break_start_at->format('H:i'),
                    'end'     => $b->requested_break_end_at->format('H:i'),
                    'minutes' => $b->requested_break_start_at->diffInMinutes($b->requested_break_end_at),
                ])->values();
            if ($seg->isNotEmpty()) {
                return $seg;
            }
        }

        return collect();
    }
}
