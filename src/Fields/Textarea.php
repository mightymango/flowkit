<?php

namespace Act\Flowkit\Fields;

class Textarea extends Field
{
    public function component(): string
    {
        return 'flowkit::field.textarea';
    }

    protected function baseRules(): array
    {
        return ['string', 'max:10000'];
    }
}
