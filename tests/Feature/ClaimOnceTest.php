<?php

use Illuminate\Support\Facades\Cache;
use MightyMango\Flowkit\Engine\Context;
use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Models\FlowClaim;

it('claims a step once per run', function () {
    $context = (new Context([], runId: 5))->forStep('s1');

    expect($context->claimOnce())->toBeTrue()
        ->and($context->claimOnce())->toBeFalse()
        ->and((new Context([], runId: 5))->claimOnce('s1'))->toBeFalse()
        ->and($context->claimOnce('s2'))->toBeTrue()
        ->and((new Context([], runId: 6))->claimOnce('s1'))->toBeTrue();

    expect(FlowClaim::where('flow_run_id', 5)->pluck('key')->all())
        ->toEqualCanonicalizing(['flowkit:5:s1', 'flowkit:5:s2']);
});

it('needs to know the step', function () {
    (new Context([], runId: 5))->claimOnce();
})->throws(LogicException::class);

it('does not use the cache', function () {
    // A store without tags, as the tenancy package's cache bootstrapper
    // requires: any cache call made while claiming would throw.
    Cache::shouldReceive('add', 'put', 'get', 'tags')->never();

    expect((new Context([], runId: 5))->forStep('s1')->claimOnce())->toBeTrue();
});

it('removes a run\'s claims with the run', function () {
    Flowkit::define('onboarding')->trigger('core.manual')->step('s1', 'core.set-variable', ['name' => 'a', 'value' => 'b'])->save();
    $run = Flowkit::run('onboarding');
    (new Context([], runId: $run->id))->claimOnce('s1');
    (new Context([], runId: $run->id + 1))->claimOnce('s1');

    $run->delete();

    expect(FlowClaim::pluck('flow_run_id')->all())->toBe([$run->id + 1]);
});
