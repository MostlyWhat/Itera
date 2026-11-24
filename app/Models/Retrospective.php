<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RetrospectiveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The notes of a Sprint review (ITERA-10): what went well, what blocked the
 * work, and what to change in the next Sprint.
 *
 * @property int $id
 * @property int $sprint_id
 * @property string|null $went_well
 * @property string|null $blockers
 * @property string|null $improve_next
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Sprint $sprint
 */
#[Fillable(['went_well', 'blockers', 'improve_next'])]
class Retrospective extends Model
{
    /** @use HasFactory<RetrospectiveFactory> */
    use HasFactory;

    /**
     * Get the Sprint of the retrospective.
     *
     * @return BelongsTo<Sprint, $this>
     */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }
}
