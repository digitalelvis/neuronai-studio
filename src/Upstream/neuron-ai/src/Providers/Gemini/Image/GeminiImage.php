<?php

declare(strict_types=1);

namespace NeuronAI\Providers\Gemini\Image;

use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\HttpClient\HttpRequest;
use NeuronAI\Providers\Gemini\GeminiMediaProvider;

use function end;

/**
 * Nano Banana and other Gemini image models via generateContent.
 *
 * https://ai.google.dev/gemini-api/docs/image-generation
 */
class GeminiImage extends GeminiMediaProvider
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        protected string $key,
        protected string $model,
        protected array $parameters = [],
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = ($httpClient ?? new GuzzleHttpClient())
            ->withBaseUri($this->baseUri)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $this->key,
            ]);
    }

    public function chat(Message ...$messages): Message
    {
        $message = end($messages);
        $parts = [];
        $prompt = $this->promptFrom($message);

        if ($prompt !== '') {
            $parts[] = ['text' => $prompt];
        }

        $image = $message->getImage();
        if ($image !== null) {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => $image->mediaType ?? 'image/png',
                    'data' => $image->content,
                ],
            ];
        }

        if ($parts === []) {
            throw new ProviderException('Gemini image generation requires a text prompt or a source image.');
        }

        $body = [
            'contents' => [[
                'role' => 'user',
                'parts' => $parts,
            ]],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
                ...$this->parameters,
            ],
        ];

        $result = $this->httpClient->request(
            HttpRequest::post(uri: 'models/'.$this->model.':generateContent', body: $body)
        )->json();

        $this->assertNoApiError($result);

        $inline = $this->firstInline($result, 'image/');
        if ($inline === null) {
            throw new ProviderException('Gemini image generation returned no image data.');
        }

        $response = new AssistantMessage(
            new ImageContent($inline['data'], SourceType::BASE64, $inline['mime'])
        );

        return $this->attachUsage($response, $result);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{data: string, mime: string}|null
     */
    protected function firstInline(array $result, string $mimePrefix): ?array
    {
        $parts = $result['candidates'][0]['content']['parts'] ?? [];

        foreach ($parts as $part) {
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (! is_array($inline)) {
                continue;
            }

            $mime = (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? '');
            $data = (string) ($inline['data'] ?? '');

            if ($data !== '' && ($mime === '' || str_starts_with($mime, $mimePrefix))) {
                return ['data' => $data, 'mime' => $mime !== '' ? $mime : 'image/png'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function attachUsage(AssistantMessage $response, array $result): AssistantMessage
    {
        $usage = $result['usageMetadata'] ?? null;
        if (! is_array($usage)) {
            return $response;
        }

        $response->setUsage(new Usage(
            (int) ($usage['promptTokenCount'] ?? 0),
            (int) ($usage['candidatesTokenCount'] ?? 0),
        ));

        return $response;
    }
}
