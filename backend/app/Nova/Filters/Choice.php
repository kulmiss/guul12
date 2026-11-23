<?php

namespace App\Nova\Filters;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * A drop-down filter: a name, a stable key (kept in the address and in each staff member's saved
 * filters), the choices (value => label) and how a choice narrows the list. A value that is not one
 * of the choices changes nothing.
 */
final class Choice extends Filter
{
    /** @var array<string, string>|null */
    private ?array $resolved = null;

    /**
     * @param  array<int|string, string>|Closure(): array<int|string, string>  $choices  value => label
     * @param  Closure(Builder, string): mixed  $narrow
     */
    public function __construct(string $name, private readonly string $id, private readonly array|Closure $choices, private readonly Closure $narrow)
    {
        $this->name = $name;
    }

    public function key(): string
    {
        return $this->id;
    }

    /** @return array<string, string> label => value, as Nova wants it */
    public function options(NovaRequest $request): array
    {
        return array_flip($this->choices());
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        $value = is_scalar($value) ? (string) $value : '';
        if (! array_key_exists($value, $this->choices())) {
            return $query;
        }
        ($this->narrow)($query, $value);

        return $query;
    }

    /** @return array<string, string> */
    private function choices(): array
    {
        if ($this->resolved === null) {
            $choices = $this->choices instanceof Closure ? ($this->choices)() : $this->choices;
            $this->resolved = [];
            foreach ($choices as $value => $label) {
                $this->resolved[(string) $value] = $label;
            }
        }

        return $this->resolved;
    }
}
