<?php

namespace App\Actions\Areas;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Save a new priority order for a list of Areas or Projects (ITERA-07).
 * The ids the user sent come first, in their order. The other rows of the
 * list, for example archived ones, keep their order after them. Only the
 * `position` column changes, so deadlines, statuses, and story points stay.
 */
class SavePriorityOrder
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $list  All rows of the list, in their current order.
     * @param  list<int>  $ids
     */
    public function handle(Builder $list, array $ids): void
    {
        DB::transaction(function () use ($list, $ids) {
            $current = (clone $list)->pluck('position', 'id');
            $rest = $current->keys()->reject(fn (int $id) => in_array($id, $ids, true));

            foreach ([...$ids, ...$rest] as $index => $id) {
                if ((int) $current[$id] !== $index + 1) {
                    (clone $list)->getModel()->newQuery()->whereKey($id)->update(['position' => $index + 1]);
                }
            }
        });
    }
}
