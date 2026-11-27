<?php

namespace MightyMango\Flowkit\Engine;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * ExpressionLanguage with none of the default functions registered.
 * Add safe helpers here deliberately, one at a time.
 */
final class SandboxedExpressionLanguage extends ExpressionLanguage
{
    protected function registerFunctions(): void
    {
        //
    }
}
