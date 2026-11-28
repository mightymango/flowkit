<?php

namespace MightyMango\Flowkit\Fields;

/**
 * An ISO 8601 duration such as P7D or PT2H. The editor shows a number and a
 * unit (minutes, hours, days or weeks), with a raw ISO 8601 box for anything
 * else; step cards describe it in words.
 */
class Duration extends Field
{
    public const PATTERN = '/^P(?!$)(?:(?<Y>\d+)Y)?(?:(?<Mo>\d+)M)?(?:(?<W>\d+)W)?(?:(?<D>\d+)D)?(?:T(?=\d)(?:(?<H>\d+)H)?(?:(?<Mi>\d+)M)?(?:(?<S>\d+)S)?)?$/';

    /** The translation key for each part, largest first. */
    private const UNITS = [
        'Y' => 'years',
        'Mo' => 'months',
        'W' => 'weeks',
        'D' => 'days',
        'H' => 'hours',
        'Mi' => 'minutes',
        'S' => 'seconds',
    ];

    public function component(): string
    {
        return 'flowkit::field.duration';
    }

    protected function baseRules(): array
    {
        return ['string', 'regex:'.self::PATTERN];
    }

    public function summarise(mixed $value): ?string
    {
        return is_string($value) ? (self::describe($value) ?? $value) : null;
    }

    /**
     * A duration in words in the app's locale, e.g. "1 week, 2 days", or
     * null if it isn't a valid ISO 8601 duration.
     */
    public static function describe(string $duration): ?string
    {
        if (! preg_match(self::PATTERN, $duration, $match)) {
            return null;
        }

        $parts = [];

        foreach (self::UNITS as $group => $unit) {
            if (($match[$group] ?? '') !== '') {
                $parts[] = trans_choice("flowkit::editor.durations.{$unit}", (int) $match[$group]);
            }
        }

        return implode(', ', $parts);
    }
}
