<?php

declare(strict_types=1);

namespace NeuronAI\Providers\Gemini\Video;

use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ContentBlocks\VideoContent;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\HttpClient\HttpRequest;
use NeuronAI\Providers\Gemini\GeminiMediaProvider;

use function base64_encode;
use function end;
use function sleep;
use function time;

/**
 * Veo video generation via predictLongRunning and operation polling.
 *
 * https://ai.google.dev/gemini-api/docs/video
 */
class GeminiVideo extends GeminiMediaProvider
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        protected string $key,
        protected string $model,
        protected int $timeoutSeconds = 180,
        protected array $parameters = [],
        protected int $pollIntervalSeconds = 2,
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = ($httpClient ?? new GuzzleHttpClient())
            ->withBaseUri($this->baseUri)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $this->key,
            ])
            ->withTimeout((float) max($this->timeoutSeconds, 30));
    }

    public function chat(Message ...$messages): Message
    {
        $message = end($messages);
        $prompt = $this->promptFrom($message);

        if ($prompt === '') {
            throw new ProviderException('Gemini video generation requires a text prompt.');
        }

        $body = [
            'instances' => [[
                'prompt' => $prompt,
            ]],
        ];

        if ($this->parameters !== []) {
            $body['parameters'] = $this->parameters;
        }

        $started = $this->httpClient->request(
            HttpRequest::post(uri: 'models/'.$this->model.':predictLongRunning', body: $body)
        )->json();

        $this->assertNoApiError($started);

        $operation = $this->wait($started);
        $video = $this->extractVideo($operation);

        return new AssistantMessage(
            new VideoContent($video['data'], $video['source'], $video['mime'])
        );
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    protected function wait(array $operation): array
    {
        $deadline = time() + max(0, $this->timeoutSeconds);
        $current = $operation;

        while (! ($current['done'] ?? false)) {
            $name = (string) ($current['name'] ?? '');
            if ($name === '') {
                throw new ProviderException('Gemini video generation did not return an operation name.');
            }

            if (time() >= $deadline) {
                throw new ProviderException('Gemini video generation timed out waiting for the operation.');
            }

            if ($this->pollIntervalSeconds > 0) {
                sleep($this->pollIntervalSeconds);
            }

            $current = $this->httpClient->request(
                HttpRequest::get($this->relativeUri($name))
            )->json();

            $this->assertNoApiError($current);
        }

        if (isset($current['error'])) {
            throw new ProviderException('Gemini video generation failed.');
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array{data: string, source: SourceType, mime: string}
     */
    protected function extractVideo(array $operation): array
    {
        $response = $operation['response'] ?? [];
        $samples = $response['generateVideoResponse']['generatedSamples']
            ?? $response['generatedVideos']
            ?? $response['videos']
            ?? [];

        $video = $samples[0]['video'] ?? $samples[0] ?? null;
        if (! is_array($video)) {
            throw new ProviderException('Gemini video generation returned no video.');
        }

        $bytes = (string) ($video['bytesBase64Encoded'] ?? $video['bytes_base64_encoded'] ?? '');
        if ($bytes !== '') {
            return [
                'data' => $bytes,
                'source' => SourceType::BASE64,
                'mime' => (string) ($video['mimeType'] ?? 'video/mp4'),
            ];
        }

        $uri = (string) ($video['uri'] ?? '');
        if ($uri === '') {
            throw new ProviderException('Gemini video generation returned no video bytes or URI.');
        }

        $downloaded = $this->httpClient->request(
            HttpRequest::get($this->relativeUri($uri))
        );

        return [
            'data' => base64_encode($downloaded->body),
            'source' => SourceType::BASE64,
            'mime' => 'video/mp4',
        ];
    }

    protected function relativeUri(string $uri): string
    {
        if (! str_starts_with($uri, 'http')) {
            return ltrim($uri, '/');
        }

        $path = (string) (parse_url($uri, PHP_URL_PATH) ?? '');
        $query = parse_url($uri, PHP_URL_QUERY);
        $marker = '/v1beta/';
        $pos = strpos($path, $marker);
        $relative = $pos === false ? ltrim($path, '/') : substr($path, $pos + strlen($marker));

        return is_string($query) && $query !== '' ? $relative.'?'.$query : $relative;
    }
}
