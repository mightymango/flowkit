<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use MightyMango\Flowkit\Bricks\BrickRegistry;
use MightyMango\Flowkit\Bricks\Core\Notify;
use MightyMango\Flowkit\Engine\Context;
use MightyMango\Flowkit\Engine\Expression;
use MightyMango\Flowkit\Enums\RunStatus;
use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Notifications\FlowNotification;
use MightyMango\Flowkit\Tests\Fixtures\User;

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('password')->nullable();
        $table->timestamps();
    });

    config()->set('flowkit.notify.user_model', User::class);

    Notification::fake();
});

/**
 * Run the brick the way the runner does: resolve the templates, validate,
 * then handle the step.
 */
function notify(array $settings, array $data = [], int $runId = 1, string $stepId = 's1')
{
    $context = (new Context($data, runId: $runId))->forStep($stepId);
    $settings = app(Expression::class)->resolve($settings, $context);
    $brick = app(BrickRegistry::class)->resolve('core.notify');

    expect(Validator::make($settings, $brick->rules())->passes())->toBeTrue();

    return $brick->handle($context, $settings);
}

it('is registered as a core action', function () {
    expect(app(BrickRegistry::class)->resolve('core.notify'))->toBeInstanceOf(Notify::class)
        ->and(Notify::$retrySafe)->toBeFalse();
});

it('sends to an email address', function () {
    $result = notify(['recipient' => 'ana@example.com', 'subject' => 'Hello', 'message' => 'Welcome aboard']);

    expect($result->status)->toBe(RunStatus::Completed)
        ->and($result->output)->toBe(['sent' => true, 'recipient_type' => 'email']);

    Notification::assertSentOnDemand(FlowNotification::class, function (FlowNotification $n, array $channels, AnonymousNotifiable $notifiable) {
        return $notifiable->routes['mail'] === 'ana@example.com'
            && $n->subject === 'Hello'
            && $n->message === 'Welcome aboard';
    });
});

it('sends to a user from a variable', function () {
    $user = User::create(['name' => 'Bo', 'email' => 'bo@example.com']);

    $result = notify(
        ['recipient' => '{{ employee.manager_id }}', 'subject' => 'New starter', 'message' => 'Say hello'],
        ['employee' => ['manager_id' => $user->id]],
    );

    expect($result->output['recipient_type'])->toBe('user');
    Notification::assertSentTo($user, FlowNotification::class);
});

it('finds the user model from the auth provider by default', function () {
    config()->set('flowkit.notify.user_model', null);
    config()->set('auth.providers.users.model', User::class);

    expect(Notify::userModel())->toBe(User::class);
});

it('fills the subject and message from variables', function () {
    notify(
        ['recipient' => '{{ employee.email }}', 'subject' => 'Welcome {{ employee.name }}', 'message' => "Hi {{ employee.name }},\nYou start in {{ employee.department }}."],
        ['employee' => ['name' => 'Ana', 'email' => 'ana@example.com', 'department' => 'Delivery']],
    );

    Notification::assertSentOnDemand(FlowNotification::class, function (FlowNotification $n, array $channels, AnonymousNotifiable $notifiable) {
        $mail = $n->toMail($notifiable);

        return $notifiable->routes['mail'] === 'ana@example.com'
            && $mail->subject === 'Welcome Ana'
            && $mail->introLines === ['Hi Ana,', 'You start in Delivery.'];
    });
});

it('does not render the message as Blade', function () {
    notify(['recipient' => 'ana@example.com', 'subject' => 'Hi', 'message' => '@php echo 1; @endphp {!! $x !!}']);

    Notification::assertSentOnDemand(FlowNotification::class, fn (FlowNotification $n) => $n->message === '@php echo 1; @endphp {!! $x !!}');
});

it('sends nothing in a dry run and shows what would be sent', function () {
    Flowkit::define('welcome')->trigger('core.manual')
        ->step('s1', 'core.notify', [
            'recipient' => '{{ employee.email }}',
            'subject' => 'Welcome {{ employee.name }}',
            'message' => 'Hello {{ employee.name }}',
        ])
        ->save();

    $run = Flowkit::test('welcome', ['employee' => ['name' => 'Ana', 'email' => 'ana@example.com']])->refresh();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->step('s1')->output)->toBe([
            'dry_run' => true,
            'would_send' => ['recipient' => 'ana@example.com', 'subject' => 'Welcome Ana', 'message' => 'Hello Ana'],
        ]);

    Notification::assertNothingSent();
});

it('sends nothing when handled in dry-run mode', function () {
    $context = (new Context([], runId: 1, dryRun: true))->forStep('s1');

    $result = app(Notify::class)->handle($context, ['recipient' => '7', 'subject' => 'Hi', 'message' => 'There']);

    expect($result->output['would_send']['recipient'])->toBe('User #7');
    Notification::assertNothingSent();
});

it('does not send twice for the same run and step', function () {
    $settings = ['recipient' => 'ana@example.com', 'subject' => 'Hi', 'message' => 'Once only'];

    notify($settings, runId: 5, stepId: 's1');
    $retry = notify($settings, runId: 5, stepId: 's1');
    notify($settings, runId: 6, stepId: 's1');

    expect($retry->status)->toBe(RunStatus::Skipped)
        ->and($retry->output['already_sent'])->toBeTrue();
    Notification::assertSentOnDemandTimes(FlowNotification::class, 2);
});

it('refuses to send without knowing its step', function () {
    $result = app(Notify::class)->handle(new Context([], runId: 1), ['recipient' => 'ana@example.com', 'subject' => 'Hi', 'message' => 'There']);

    expect($result->status)->toBe(RunStatus::Failed);
    Notification::assertNothingSent();
});

it('fails when the user does not exist', function () {
    $result = notify(['recipient' => '999', 'subject' => 'Hi', 'message' => 'There']);

    expect($result->status)->toBe(RunStatus::Failed);
    Notification::assertNothingSent();
});

it('rejects invalid settings', function (array $settings, string $field) {
    $validator = Validator::make($settings, app(Notify::class)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has($field))->toBeTrue();
})->with([
    'missing recipient' => [['subject' => 'Hi', 'message' => 'There'], 'recipient'],
    'not an email or id' => [['recipient' => 'not an address', 'subject' => 'Hi', 'message' => 'There'], 'recipient'],
    'zero id' => [['recipient' => 0, 'subject' => 'Hi', 'message' => 'There'], 'recipient'],
    'missing subject' => [['recipient' => 'ana@example.com', 'message' => 'There'], 'subject'],
    'missing message' => [['recipient' => 'ana@example.com', 'subject' => 'Hi'], 'message'],
]);

it('accepts a template recipient in the editor', function () {
    $validator = Validator::make(
        ['recipient' => '{{ employee.manager_id }}', 'subject' => 'Hi', 'message' => 'There'],
        app(Notify::class)->rules(),
    );

    expect($validator->passes())->toBeTrue();
});

it('fails the step in a run when the resolved settings are invalid', function () {
    Flowkit::define('bad-notify')->trigger('core.manual')
        ->step('s1', 'core.notify', ['recipient' => '{{ employee.email }}', 'subject' => 'Hi', 'message' => 'There'])
        ->save();

    $run = Flowkit::start('bad-notify', ['employee' => ['email' => 'nope']])->refresh();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->step('s1')->error)->toContain('recipient');
    Notification::assertNothingSent();
});

it('sends an email when a run reaches the step', function () {
    Flowkit::define('welcome')->trigger('core.manual')
        ->step('s1', 'core.notify', [
            'recipient' => '{{ employee.email }}',
            'subject' => 'Welcome',
            'message' => 'Hello {{ employee.name }}',
        ])
        ->save();

    $run = Flowkit::start('welcome', ['employee' => ['email' => 'ana@example.com', 'name' => 'Ana']])->refresh();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->step('s1')->output)->toBe(['sent' => true, 'recipient_type' => 'email']);

    Notification::assertSentOnDemand(FlowNotification::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'ana@example.com');
});

