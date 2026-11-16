<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Archive a record without deleting it. An archived record keeps its data
 * and its history, but the app hides it from the active lists.
 *
 * The model must have a nullable `archived_at` timestamp column.
 *
 * @mixin Model
 */
trait Archivable
{
    /**
     * Cast `archived_at` to a date for every model that uses this trait.
     */
    public function initializeArchivable(): void
    {
        $this->mergeCasts(['archived_at' => 'datetime']);
    }

    /**
     * Archive the record.
     */
    public function archive(): bool
    {
        return $this->forceFill(['archived_at' => now()])->save();
    }

    /**
     * Restore an archived record to the active list.
     */
    public function unarchive(): bool
    {
        return $this->forceFill(['archived_at' => null])->save();
    }

    /**
     * Determine if the record is archived.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Scope the query to records that are not archived.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('archived_at'));
    }

    /**
     * Scope the query to archived records.
     *
     * @param  Builder<static>  $query
     */
    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull($this->qualifyColumn('archived_at'));
    }
}
