<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The default order of every store back office list: newest first, `created_at`
 * descending, then the primary key descending so rows that share a timestamp
 * (seeded or imported in one go) keep a stable position between pages.
 * See .ai/rules/store-list-default-order.md.
 *
 * Use it as the last ordering step of a list query, after any sort the user
 * chose: `$query->tap(NewestFirst::apply(...))->paginate($perPage)`. It only
 * appends, so a sort the user picked always comes first and wins, and the
 * newest-first columns then act as its tie-breakers. A column the query is
 * already ordered by is not added a second time. The columns are qualified
 * with the model's table, so a joined query does not get an ambiguous
 * `created_at`; a model whose table has no creation timestamp is ordered by
 * its key alone.
 */
final class NewestFirst
{
    /**
     * @param  Builder<covariant Model>  $query
     */
    public static function apply(Builder $query): void
    {
        $model = $query->getModel();
        $ordered = [];
        foreach ($query->getQuery()->orders ?? [] as $order) {
            if (isset($order['column']) && is_string($order['column'])) {
                $ordered[] = Str::afterLast($order['column'], '.');
            }
        }

        $created = $model->getCreatedAtColumn();
        if ($model->usesTimestamps() && $created !== null && ! in_array($created, $ordered, true)) {
            $query->orderBy($model->qualifyColumn($created), 'desc');
        }

        if (! in_array($model->getKeyName(), $ordered, true)) {
            $query->orderBy($model->getQualifiedKeyName(), 'desc');
        }
    }
}
