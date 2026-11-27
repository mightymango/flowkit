<?php

namespace MightyMango\Flowkit\Concerns;

use MightyMango\Flowkit\TriggerDispatcher;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * Opt a model in to firing flow triggers. Declare which events fire:
 *
 *   protected array $flowTriggers = ['created', 'updated'];
 *
 * @mixin Model
 */
trait HasFlowTriggers
{
    public static function bootHasFlowTriggers(): void
    {
        // Read the declared events without instantiating the model mid-boot.
        $events = (new ReflectionClass(static::class))->getDefaultProperties()['flowTriggers']
            ?? ['created', 'updated'];

        foreach ($events as $event) {
            static::registerModelEvent($event, function (Model $model) use ($event) {
                app(TriggerDispatcher::class)->modelEvent($model, $event);
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
}
