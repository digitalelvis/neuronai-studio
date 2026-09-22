<?php

namespace DigitalElvis\NeuronAIStudio\Registry;

use DigitalElvis\NeuronAIStudio\Support\ProviderParameters;
use DigitalElvis\NeuronAIStudio\Support\StudioTranslator;
use InvalidArgumentException;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Cohere\Cohere;
use NeuronAI\Providers\Deepseek\Deepseek;
use NeuronAI\Providers\ElevenLabs\ElevenLabsSpeechToText;
use NeuronAI\Providers\ElevenLabs\ElevenLabsTextToSpeech;
use NeuronAI\Providers\Gemini\Audio\GeminiSpeechToText;
use NeuronAI\Providers\Gemini\Audio\GeminiTextToSpeech;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\Gemini\Image\GeminiImage;
use NeuronAI\Providers\Gemini\Video\GeminiVideo;
use NeuronAI\Providers\HuggingFace\HuggingFace;
use NeuronAI\Providers\Mistral\Mistral;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\OpenAI\Audio\OpenAISpeechToText;
use NeuronAI\Providers\OpenAI\Audio\OpenAITextToSpeech;
use NeuronAI\Providers\OpenAI\Image\OpenAIImage;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAI\Responses\OpenAIResponses;

class ProviderRegistry
{
    /** @return array<string, array{label: string, models: array}> */
    public function all(): array
    {
        return config('neuronai-studio.providers', []);
    }

    public function labels(): array
    {
        return collect($this->all())
            ->mapWithKeys(fn (array $config, string $key) => [
                $key => StudioTranslator::get(
                    'registry.providers.'.$key,
                    $config['label'] ?? $key
                ),
            ])
            ->all();
    }

    public function modelsFor(string $provider): array
    {
        return config("neuronai-studio.providers.{$provider}.models", []);
    }

    /** @param  array<string, mixed>  $parameters */
    public function resolve(string $provider, ?string $model = null, array $parameters = [], ?string $keyOverride = null): AIProviderInterface
    {
        $config = config("neuron.provider.{$provider}");

        if (! is_array($config) || ! array_key_exists('model', $config)) {
            throw new InvalidArgumentException(
                "AI provider [{$provider}] is not configured. Publish config/neuron.php and set credentials in .env."
            );
        }

        if ($model !== null) {
            $config['model'] = $model;
        }

        if ($parameters !== []) {
            $base = is_array($config['parameters'] ?? null) ? $config['parameters'] : [];
            $config['parameters'] = ProviderParameters::merge($provider, $base, $parameters);
        }

        if ($keyOverride !== null && $keyOverride !== '') {
            $resolved = app(\DigitalElvis\NeuronAIStudio\Runtime\ConfigValueResolver::class)->resolve($keyOverride);
            $config['key'] = is_string($resolved) ? $resolved : (string) $resolved;
        }

        $this->assertProviderConfigured($provider, $config);

        return $this->makeProvider($provider, $config);
    }

    /**
     * Resolve an image, speech, transcription, or video driver.
     *
     * @param  array<string, mixed>  $constructor  Named constructor args (voice, voiceId, language, output_format, parameters, timeoutSeconds).
     */
    public function resolveMedia(string $driver, ?string $model = null, array $constructor = [], ?string $keyOverride = null): AIProviderInterface
    {
        $config = config("neuron.provider.{$driver}");

        if (! is_array($config) || ! array_key_exists('model', $config)) {
            throw new InvalidArgumentException(
                "AI provider [{$driver}] is not configured. Publish config/neuron.php and set credentials in .env."
            );
        }

        if ($model !== null && $model !== '') {
            $config['model'] = $model;
        }

        foreach ($constructor as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $config[$name] = $value;
        }

        if ($keyOverride !== null && $keyOverride !== '') {
            $resolved = app(\DigitalElvis\NeuronAIStudio\Runtime\ConfigValueResolver::class)->resolve($keyOverride);
            $config['key'] = is_string($resolved) ? $resolved : (string) $resolved;
        }

        $this->assertProviderConfigured($driver, $config);

        return $this->makeProvider($driver, $config);
    }

    /** @param array<string, mixed> $config */
    protected function assertProviderConfigured(string $provider, array $config): void
    {
        if (! array_key_exists('key', $config)) {
            return;
        }

        $key = $config['key'];

        if ($key !== null && $key !== '') {
            return;
        }

        $envHint = match ($provider) {
            'openai', 'openai-responses', 'openai-tts', 'openai-stt', 'openai-image' => 'OPENAI_KEY',
            'anthropic' => 'ANTHROPIC_KEY',
            'gemini', 'gemini-image', 'gemini-tts', 'gemini-stt', 'gemini-video' => 'GEMINI_KEY',
            'mistral' => 'MISTRAL_KEY',
            'deepseek' => 'DEEPSEEK_KEY',
            'huggingface' => 'HUGGINGFACE_KEY',
            'cohere' => 'COHERE_KEY',
            'elevenlabs-tts', 'elevenlabs-stt' => 'ELEVENLABS_KEY',
            default => 'the provider key in config/neuron.php',
        };

        throw new InvalidArgumentException(
            "AI provider [{$provider}] is not configured. Set {$envHint} in your .env file, "
            .'or choose a different provider in the node settings.',
        );
    }

    /** @param array<string, mixed> $config */
    protected function makeProvider(string $provider, array $config): AIProviderInterface
    {
        return match ($provider) {
            'anthropic' => new Anthropic(...$config),
            'openai' => new OpenAI(...$config),
            'openai-responses' => new OpenAIResponses(...$config),
            'gemini' => new Gemini(...$config),
            'ollama' => new Ollama(...$config),
            'mistral' => new Mistral(...$config),
            'deepseek' => new Deepseek(...$config),
            'huggingface' => new HuggingFace(...$config),
            'cohere' => new Cohere(...$config),
            'openai-image' => new OpenAIImage(...$config),
            'openai-tts' => new OpenAITextToSpeech(...$config),
            'openai-stt' => new OpenAISpeechToText(...$config),
            'elevenlabs-tts' => new ElevenLabsTextToSpeech(...$config),
            'elevenlabs-stt' => new ElevenLabsSpeechToText(...$config),
            'gemini-image' => new GeminiImage(...$config),
            'gemini-tts' => new GeminiTextToSpeech(...$config),
            'gemini-stt' => new GeminiSpeechToText(...$config),
            'gemini-video' => new GeminiVideo(...$config),
            default => throw new InvalidArgumentException(
                "Unsupported AI provider [{$provider}]. Add support in ProviderRegistry or choose a configured provider."
            ),
        };
    }
}
