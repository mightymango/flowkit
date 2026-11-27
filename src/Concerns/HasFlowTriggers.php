<?php

namespace MightyMango\Flowkit\Concerns;

use BackedEnum;
use MightyMango\Flowkit\TriggerDispatcher;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use UnitEnum;

/**
 * Opt a model in to firing flow triggers. Declare which events fire, and
 * which attributes to watch for changes:
 *
 *   protected array $flowTriggers = ['created', 'updated', 'status' => 'changed'];
 *
 * A watched attribute fires a `changed` event when an update changes it,
 * with the old and new values under `changes.<attribute>.from` and `.to`.
 *
 * @mixin Model
 */
trait HasFlowTriggers
{
    public static function bootHasFlowTriggers(): void
    {
        // Read the declared events without instantiating the model mid-boot.
        $declared = (new ReflectionClass(static::class))->getDefaultProperties()['flowTriggers']
            ?? ['created', 'updated'];

        $watched = [];

        foreach ($declared as $key => $value) {
            if (is_string($key)) {
                if ($value === 'changed') {
                    $watched[] = $key;
                }

                continue;
            }

            static::registerModelEvent($value, function (Model $model) use ($value) {
                app(TriggerDispatcher::class)->modelEvent($model, $value);
            });
        }

        if ($watched) {
            static::registerModelEvent('updated', function (Model $model) use ($watched) {
                foreach ($watched as $attribute) {
                    if (! $model->wasChanged($attribute)) {
                        continue;
                    }

                    // During `updated` the original still holds the old value.
                    app(TriggerDispatcher::class)->modelEvent($model, 'changed', [
                        $attribute => [
                            'from' => static::flowValue($model->getOriginal($attribute)),
                            'to' => static::flowValue($model->getAttribute($attribute)),
                        ],
                    ]);
                }
            });
        }
    }

    /**
     * The data a triggered run starts with. Override to limit what flows see.
     */
    public function toFlowPayload(): array
    {
        return $this->attributesToArray();
    }

    /** Reduce cast values (enums, dates) to something a flow can compare. */
    protected static function flowValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof \DateTimeInterface => $value->format(DATE_ATOM),
            default => $value,
        };
    }
}
