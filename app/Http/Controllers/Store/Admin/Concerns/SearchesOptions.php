<?php

namespace App\Http\Controllers\Store\Admin\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What every typeahead endpoint behind `StoreSearchSelect.vue` shares
 * (.ai/rules/store-search-select.md): the search term, a LIKE that treats `%`
 * and `_` as the characters they are, ordering that puts names starting with
 * the term first, and the `{ data, meta }` page the component reads. The
 * endpoints keep their own route, permission and query; only these mechanics
 * are shared. Typeahead pickers are not tables, so they are exceptions to
 * newest first (.ai/rules/store-list-default-order.md).
 */
trait SearchesOptions
{
    /** The most rows one page of a typeahead carries. */
    private const int OPTIONS_PER_PAGE = 20;

    /** More words than this in a term are ignored. */
    private const int MAX_SEARCH_WORDS = 5;

    protected function searchTerm(Request $request): string
    {
        return trim($request->string('q')->toString());
    }

    /** `%term%` with the LIKE wildcards of the term escaped (matched with `ESCAPE '\'`). */
    protected function likePattern(string $term, bool $prefixOnly = false): string
    {
        return ($prefixOnly ? '' : '%').addcslashes($term, '\\%_').'%';
    }

    /**
     * Keeps the rows where every word of the term is found in at least one of
     * the columns: "basmati 5kg" finds "Basmati Rice 5kg" and a serial alike.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     */
    protected function whereWordsMatch(Builder $query, array $columns, string $term): void
    {
        $grammar = $query->getQuery()->getGrammar();

        foreach ($this->searchWords($term) as $word) {
            $pattern = $this->likePattern($word);
            $query->where(function (Builder $group) use ($columns, $grammar, $pattern): void {
                foreach ($columns as $column) {
                    $group->orWhereRaw($grammar->wrap($column).' like ? escape ?', [$pattern, '\\']);
                }
            });
        }
    }

    /**
     * Like {@see self::whereWordsMatch()} on the model's own `name`, where a word
     * may instead be found in the name's translation into the active language.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    protected function whereTranslatedNameMatches(Builder $query, string $relation, string $term): void
    {
        $grammar = $query->getQuery()->getGrammar();
        $name = $grammar->wrap($query->getModel()->qualifyColumn('name'));
        $translated = $grammar->wrap($query->getModel()->{$relation}()->getRelated()->qualifyColumn('name'));

        foreach ($this->searchWords($term) as $word) {
            $pattern = $this->likePattern($word);
            $query->where(fn (Builder $group) => $group
                ->whereRaw($name.' like ? escape ?', [$pattern, '\\'])
                ->orWhereHas($relation, fn (Builder $translation) => $translation
                    ->where('lang', app()->getLocale())
                    ->whereRaw($translated.' like ? escape ?', [$pattern, '\\'])));
        }
    }

    /**
     * Rows whose `$column` starts with the term first, then by `$column`, then by key
     * (both descending for a `desc` direction, as order codes read newest first).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  'asc'|'desc'  $direction
     */
    protected function orderByRelevance(Builder $query, string $column, string $term, string $direction = 'asc'): void
    {
        if ($term !== '') {
            $query->orderByRaw('case when '.$query->getQuery()->getGrammar()->wrap($column).' like ? escape ? then 0 else 1 end', [$this->likePattern($term, true), '\\']);
        }

        $query->orderBy($column, $direction)->orderBy($query->getModel()->getQualifiedKeyName(), $direction);
    }

    /** @return list<string> */
    private function searchWords(string $term): array
    {
        return array_slice(preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, self::MAX_SEARCH_WORDS);
    }

    /**
     * One page of options in the shape the component reads.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): array<string, mixed>  $option
     */
    protected function optionsPage(Builder $query, callable $option): JsonResponse
    {
        $page = $query->paginate(self::OPTIONS_PER_PAGE)->withQueryString();

        return response()->json($this->paginated(JsonResource::collection($page->through($option))));
    }
}
