<?php

namespace Platform\Radar\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Ein Eintrag aus einer Quelle, roh: Titel, Link, Auszug, Datum. Keine Zusammenfassung, keine Bewertung. */
class RadarItem extends Model
{
    public const MORPH_ALIAS = 'radar_item';

    protected $table = 'radar_items';

    protected $fillable = [
        'uuid', 'team_id', 'source_id', 'fingerprint', 'title', 'url', 'excerpt', 'author', 'published_at', 'fetched_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'fetched_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $i) {
            $i->uuid ??= (string) Str::uuid();
        });
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(RadarSource::class, 'source_id')->withTrashed();
    }
}
