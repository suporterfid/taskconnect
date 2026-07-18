<?php

namespace App\Infrastructure\Persistence\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait SoftArchivable
{
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function archive(): bool
    {
        return $this->update(['archived_at' => now()]);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
