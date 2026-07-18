<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class SystemHeartbeat extends Model
{
    protected $fillable = [
        'name',
        'last_seen_at',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function record(string $name, \DateTimeImmutable $seenAt, array $meta = []): self
    {
        return self::query()->updateOrCreate(
            ['name' => $name],
            [
                'last_seen_at' => $seenAt,
                'meta_json' => $meta,
            ],
        );
    }
}
