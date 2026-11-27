<?php

namespace MightyMango\Flowkit\Bricks\Core;

use Cron\CronExpression;
use DateTimeInterface;
use MightyMango\Flowkit\Bricks\Trigger;
use MightyMango\Flowkit\Fields\Select;
use MightyMango\Flowkit\Fields\Text;
use MightyMango\Flowkit\Rules\CronExpression as ValidCron;

/**
 * Starts a run on a cron schedule, e.g. a weekly digest. Needs the host app
 * to schedule `flowkit:tick` every minute.
 */
class Schedule extends Trigger
{
    public static string $key = 'core.schedule';

    public static string $label = 'On a schedule';

    public static string $group = 'Core';

    public static ?string $description = 'Start the flow at set times, using a cron expression.';

    public function fields(): array
    {
        return [
            Text::make('cron')
                ->label('Cron expression')
                ->placeholder('0 9 * * 1')
                ->help('Minute, hour, day of month, month, day of week. "0 9 * * 1" is 9am every Monday.')
                ->rules([new ValidCron])
                ->required(),
            Select::make('timezone')
                ->label('Time zone')
                ->help("Leave blank to use the app's time zone.")
                ->options(fn () => array_combine($zones = timezone_identifiers_list(), $zones)),
        ];
    }

    public function variables(): array
    {
        return [
            'schedule.scheduled_at' => 'Scheduled time',
            'schedule.timezone' => 'Time zone',
        ];
    }

    /**
     * Whether the schedule is due in the minute containing $now. Settings
     * must already have been validated against rules().
     */
    public function isDue(array $settings, DateTimeInterface $now): bool
    {
        return (new CronExpression(trim($settings['cron'])))->isDue($now, $this->timezone($settings));
    }

    public function timezone(array $settings): string
    {
        $timezone = $settings['timezone'] ?? null;

        return $timezone ?: config('app.timezone', 'UTC');
    }
}
