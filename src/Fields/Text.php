<?php

namespace Act\Flowkit\Fields;

class Text extends Field
{
    public function component(): string
    {
        return 'flowkit::field.text';
    }

    protected function baseRules(): array
    {
        return ['string', 'max:1000'];
    }
}
