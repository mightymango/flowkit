<?php

namespace MightyMango\Flowkit\Fields;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use LogicException;
use MightyMango\Flowkit\Fields\Rules\PlaceholderOr;

/**
 * Pick one record of a model by searching for it; stores its key.
 *
 *   ModelPicker::make('learner_id')->model(Learner::class)->searchUsing(['first_name', 'last_name'])->display('full_name')
 *
 * Only the model and columns the brick declares can be searched. With
 * supportsVariables(), the editor also accepts a single `{{ placeholder }}`.
 */
class ModelPicker extends Field
{
    /** @var class-string<Model>|null */
    protected ?string $model = null;

    /** @var list<string> */
    protected array $searchColumns = [];

    protected string|Closure|null $display = null;

    /** @var (Closure(Builder<Model>): mixed)|null */
    protected ?Closure $scope = null;

    protected int $limit = 10;

    /** @param  class-string<Model>  $model */
    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /** @param  list<string>|string  $columns */
    public function searchUsing(array|string $columns): static
    {
        $this->searchColumns = (array) $columns;

        return $this;
    }

    /** The attribute, or a closure given the model, shown for each record. */
    public function display(string|Closure $display): static
    {
        $this->display = $display;

        return $this;
    }

    /** Narrow the records that can be picked, e.g. to active ones. */
    public function scope(Closure $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function component(): string
    {
        return 'flowkit::field.model-picker';
    }

    protected function baseRules(): array
    {
        $model = $this->newModel();

        return [Rule::exists($model::class, $model->getKeyName())];
    }

    public function getEditorRules(): array
    {
        if (! $this->supportsVariables) {
            return $this->getRules();
        }

        return [$this->required ? 'required' : 'nullable', new PlaceholderOr([...$this->baseRules(), ...$this->rules])];
    }

    /**
     * Records matching a search term, as key => label.
     *
     * @return array<int|string, string>
     */
    public function search(string $term): array
    {
        $term = trim($term);

        if ($term === '' || $this->searchColumns === []) {
            return [];
        }

        // Case-insensitive on every database, with `%` and `_` in the term
        // matched literally.
        $pattern = '%'.addcslashes(mb_strtolower($term), '\\%_').'%';

        return $this->query()
            ->where(function (Builder $query) use ($pattern) {
                $grammar = $query->getQuery()->getGrammar();

                foreach ($this->searchColumns as $column) {
                    $query->orWhereRaw('lower('.$grammar->wrap($column).') like ? escape ?', [$pattern, '\\']);
                }
            })
            ->limit($this->limit)
            ->get()
            ->mapWithKeys(fn (Model $record) => [$record->getKey() => $this->labelOf($record)])
            ->all();
    }

    /** The label of the record a stored key points at, if it exists. */
    public function labelFor(mixed $value): ?string
    {
        if (! is_scalar($value) || $value === '') {
            return null;
        }

        $record = $this->query()->whereKey($value)->first();

        return $record ? $this->labelOf($record) : null;
    }

    public function summarise(mixed $value): ?string
    {
        return $this->labelFor($value) ?? parent::summarise($value);
    }

    public function present(mixed $value): array
    {
        return ['selectedLabel' => $this->labelFor($value)];
    }

    /** @return Builder<Model> */
    private function query(): Builder
    {
        $query = $this->newModel()->newQuery();

        if ($this->scope) {
            ($this->scope)($query);
        }

        return $query;
    }

    private function labelOf(Model $record): string
    {
        return match (true) {
            $this->display instanceof Closure => (string) ($this->display)($record),
            is_string($this->display) => (string) $record->getAttribute($this->display),
            default => (string) $record->getKey(),
        };
    }

    private function newModel(): Model
    {
        if ($this->model === null || ! is_subclass_of($this->model, Model::class)) {
            throw new LogicException("ModelPicker [{$this->name}] needs a model class.");
        }

        return new $this->model;
    }
}
