<?php

namespace Platform\Radar\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** Eine abonnierte Quelle (RSS/Atom). `note` hält fest, wofür die Quelle da ist – für den Leser, nicht für eine Auswertung. */
class RadarSource extends Model
{
    use SoftDeletes;

    public const MORPH_ALIAS = 'radar_source';

    public const TYPES = ['rss'];

    protected $table = 'radar_sources';

    protected $fillable = [
        'uuid', 'team_id', 'user_id', 'name', 'source_type', 'url', 'topic', 'cluster', 'note',
        'pull_interval_hours', 'is_active', 'last_pulled_at', 'last_error', 'error_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'pull_interval_hours' => 'integer',
        'error_count' => 'integer',
        'last_pulled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $s) {
            $s->uuid ??= (string) Str::uuid();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(RadarItem::class, 'source_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function isDue(?\DateTimeInterface $now = null): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if (! $this->last_pulled_at) {
            return true;
        }

        return $this->last_pulled_at->copy()->addHours(max(1, $this->pull_interval_hours)) <= ($now ?? now());
    }
}
