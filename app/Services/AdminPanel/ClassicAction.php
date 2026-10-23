<?php

namespace App\Services\AdminPanel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Runs an action through the classic admin controller that already owns it,
 * so the panel gets the same side effects (wallet refunds, stock, pushes,
 * mails) without a second copy of the logic.
 *
 * Classic actions answer with a redirect and a Toastr flash. This swaps in a
 * request carrying the action's input, calls the method, and reads the flash
 * back as the panel's result.
 */
class ClassicAction
{
    /**
     * @param  class-string  $controller
     * @param  array  $input  request input the classic method reads
     * @param  array  $params  named method parameters (route params, models)
     * @return array{ok: bool, message: string}
     */
    public static function run(string $controller, string $method, array $input = [], array $params = [], string $httpMethod = 'POST'): array
    {
        $original = app('request');
        $request = Request::create($original->url(), $httpMethod, $input);
        $request->setLaravelSession($original->session());
        $request->setUserResolver($original->getUserResolver());
        $request->headers->set('referer', $original->headers->get('referer', url('/admin/panel')));

        self::clearToasts();
        app()->instance('request', $request);

        try {
            // Request and FormRequest parameters resolve from the swapped-in request.
            $response = app()->call([app($controller), $method], $params);
        } catch (ValidationException $e) {
            return ['ok' => false, 'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage()];
        } finally {
            app()->instance('request', $original);
        }

        $toasts = self::takeToasts();

        if ($response instanceof JsonResponse) {
            $data = (array) $response->getData(true);
            $error = $data['errors'][0]['message'] ?? ($data['errors']['message'] ?? null);
            if ($error === null && $response->getStatusCode() >= 400) {
                $error = $data['message'] ?? translate('messages.failed');
            }
            return ['ok' => $error === null, 'message' => $error ?? ($toasts[0]['message'] ?? $data['message'] ?? '')];
        }

        // Classic actions bail out with only a warning/error; a success toast
        // means the change landed, even if a side effect (a push) then warned.
        $toasts = collect($toasts);
        $good = $toasts->whereIn('type', ['success', 'info']);
        $bad = $toasts->whereIn('type', ['error', 'warning']);
        if ($good->isEmpty() && $bad->isNotEmpty()) {
            return ['ok' => false, 'message' => strip_tags((string) $bad->first()['message'])];
        }
        $message = $good->pluck('message')->merge($bad->pluck('message'))->map(fn ($m) => strip_tags((string) $m))->implode(' · ');
        return ['ok' => true, 'message' => $message];
    }

    private static function clearToasts(): void
    {
        session()->forget('toastr::messages');
        if (app()->bound('toastr')) {
            (fn () => $this->messages = [])->call(app('toastr'));
        }
    }

    private static function takeToasts(): array
    {
        $toasts = session('toastr::messages', []);
        self::clearToasts();
        return $toasts;
    }
}
