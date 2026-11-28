<?php

namespace MightyMango\Flowkit\Bricks\Core;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use MightyMango\Flowkit\Bricks\Action;
use MightyMango\Flowkit\Engine\Context;
use MightyMango\Flowkit\Engine\Result;
use MightyMango\Flowkit\Fields\Text;
use MightyMango\Flowkit\Fields\Textarea;
use MightyMango\Flowkit\Notifications\FlowNotification;

/**
 * Send an email to a user or an address. The recipient is either a user id
 * (looked up on `flowkit.notify.user_model`) or an email address. Subject
 * and message take `{{ variables }}`, which the runner resolves before the
 * step runs.
 *
 * Not safe to retry: the step's idempotency key is claimed in the cache
 * before sending, so a retried job never sends twice. Sending is therefore
 * at most once: if the send itself throws, it isn't tried again.
 */
class Notify extends Action
{
    public static string $key = 'core.notify';

    public static string $label = 'Send an email';

    public static string $group = 'Core';

    public static ?string $description = 'Email a user or an address.';

    public static bool $retrySafe = false;

    public function fields(): array
    {
        return [
            Text::make('recipient')
                ->label('Send to')
                ->placeholder('{{ employee.manager_id }} or someone@example.com')
                ->help('A user id from a variable, or an email address.')
                ->variables('user')
                ->required(),
            Text::make('subject')->label('Subject')->supportsVariables()->required(),
            Textarea::make('message')->label('Message')->supportsVariables()->required(),
        ];
    }

    public function rules(): array
    {
        $rules = parent::rules();

        // A lone `{{ user.id }}` placeholder resolves to an integer, so the
        // recipient isn't held to Text's `string` rule.
        $rules['recipient'] = ['required', $this->recipientRule()];

        return $rules;
    }

    public function handle(Context $context, array $settings): Result
    {
        if ($context->dryRun) {
            return $this->dryRun($context, $settings);
        }

        $stepId = $context->stepId();

        if ($stepId === null) {
            return Result::fail('The step id is not known, so the email could not be sent safely.');
        }

        $recipient = $this->recipient($settings['recipient'] ?? null);

        if ($recipient === null) {
            return Result::fail('The recipient must be a user id or an email address.');
        }

        $notifiable = $recipient['type'] === 'user'
            ? $this->findUser($recipient['value'])
            : Notification::route('mail', $recipient['value']);

        if ($notifiable === null) {
            return Result::fail('No user was found for the recipient.');
        }

        if (! $context->claimOnce($stepId)) {
            return Result::skip(['sent' => false, 'already_sent' => true]);
        }

        $notifiable->notify(new FlowNotification((string) $settings['subject'], (string) $settings['message']));

        return Result::ok(['sent' => true, 'recipient_type' => $recipient['type']]);
    }

    public function dryRun(Context $context, array $settings): Result
    {
        $recipient = $this->recipient($settings['recipient'] ?? null);

        return Result::ok([
            'dry_run' => true,
            'would_send' => [
                'recipient' => match ($recipient['type'] ?? null) {
                    'user' => 'User #'.$recipient['value'],
                    'email' => $recipient['value'],
                    default => null,
                },
                'subject' => $settings['subject'] ?? null,
                'message' => $settings['message'] ?? null,
            ],
        ]);
    }

    /**
     * The model recipients are looked up on: `flowkit.notify.user_model`, or
     * else the model of the default guard's user provider.
     *
     * @return class-string<Model>|null
     */
    public static function userModel(): ?string
    {
        if ($model = config('flowkit.notify.user_model')) {
            return $model;
        }

        $provider = config('auth.guards.'.config('auth.defaults.guard').'.provider');

        return $provider ? config("auth.providers.{$provider}.model") : null;
    }

    /**
     * @return array{type: 'user'|'email', value: int|string}|null
     */
    private function recipient(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ((is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value > 0) {
            return ['type' => 'user', 'value' => (int) $value];
        }

        if (is_string($value) && Validator::make(['email' => $value], ['email' => 'email:rfc'])->passes()) {
            return ['type' => 'email', 'value' => $value];
        }

        return null;
    }

    private function findUser(int $id): ?Model
    {
        $model = static::userModel();

        if (! $model || ! class_exists($model)) {
            return null;
        }

        $user = $model::query()->find($id);

        return $user && method_exists($user, 'notify') ? $user : null;
    }

    /**
     * A user id or an email address. In the editor the value is still a
     * template, so a `{{ placeholder }}` passes there; the runner checks the
     * resolved value.
     */
    private function recipientRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (is_string($value) && preg_match('/\{\{.+?\}\}/s', $value)) {
                return;
            }

            if ($this->recipient($value) === null) {
                $fail('The :attribute must be a user id or an email address.');
            }
        };
    }
}
