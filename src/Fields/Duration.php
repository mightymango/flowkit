<?php

namespace Act\Flowkit\Fields;

/**
 * An ISO 8601 duration such as P7D or PT2H.
 */
class Duration extends Field
{
    public function component(): string
    {
        return 'flowkit::field.duration';
    }

    protected function baseRules(): array
    {
        return ['string', 'regex:/^P(?!$)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(?=\d)(\d+H)?(\d+M)?(\d+S)?)?$/'];
    }
}
