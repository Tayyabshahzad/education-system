<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

class Batch extends Model
{
    protected $fillable = [
        'name', 'teacher_id', 'start_date', 'days', 'class_time', 'duration_minutes',
        'meeting_link', 'description', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('assigned_by')->withTimestamps();
    }

    public function scheduleLabel(): string
    {
        $parts = array_filter([
            $this->days,
            $this->class_time ? Carbon::parse($this->class_time)->format('g:i A') : null,
            $this->duration_minutes ? $this->duration_minutes.' min' : null,
        ]);

        return $parts ? implode(' · ', $parts) : 'Schedule not set';
    }
}
