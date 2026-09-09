<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use DigitalElvis\NeuronAIStudio\Models\PluginAccount;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Plugins\OAuth\PluginOAuthRegistry;
use DigitalElvis\NeuronAIStudio\Plugins\OAuth\PluginOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class PluginOAuthController extends Controller
{
    public function authorize(
        Request $request,
        string $slug,
        PluginOAuthRegistry $registry,
        PluginOAuthService $oauth,
    ): RedirectResponse {
        if (! $registry->isConfigured($slug)) {
            return $this->failureRedirect(null, __('neuronai-studio::plugins.oauth_not_configured', ['name' => $slug]));
        }

        $install = PluginInstall::query()->find((int) $request->query('install'));
        $account = PluginAccount::query()->find((int) $request->query('account'));

        if ($install === null || $account === null || $account->plugin_install_id !== $install->id || $install->slug !== $slug) {
            return $this->failureRedirect(null, __('neuronai-studio::plugins.oauth_invalid_session'));
        }

        try {
            return redirect()->away($oauth->authorizationUrl($install, $account));
        } catch (Throwable $e) {
            return $this->failureRedirect($install, $e->getMessage());
        }
    }

    public function callback(Request $request, PluginOAuthService $oauth): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->failureRedirect(null, (string) $request->query('error_description', $request->query('error')));
        }

        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if ($state === '' || $code === '') {
            return $this->failureRedirect(null, __('neuronai-studio::plugins.oauth_missing_code'));
        }

        try {
            $install = $oauth->handleCallback($state, $code);

            return redirect()
                ->route('neuronai-studio.plugins.index', [
                    'connector' => 'plugin_install:'.$install->id,
                ])
                ->with('success', __('neuronai-studio::plugins.oauth_success', ['name' => $install->name]));
        } catch (Throwable $e) {
            return $this->failureRedirect(null, $e->getMessage());
        }
    }

    protected function failureRedirect(?PluginInstall $install, string $message): RedirectResponse
    {
        $params = [];

        if ($install !== null) {
            $params['connector'] = 'plugin_install:'.$install->id;
        }

        return redirect()
            ->route('neuronai-studio.plugins.index', $params)
            ->with('error', $message);
    }
}
