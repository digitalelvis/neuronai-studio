<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\ContentBlocks\AudioContent;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\HttpClient\HttpRequest;
use NeuronAI\HttpClient\HttpResponse;
use NeuronAI\HttpClient\StreamInterface;
use NeuronAI\Providers\Gemini\Audio\GeminiSpeechToText;
use NeuronAI\Providers\Gemini\Audio\GeminiTextToSpeech;
use NeuronAI\Providers\Gemini\Image\GeminiImage;
use NeuronAI\Providers\Gemini\Video\GeminiVideo;

class GeminiMediaDriverTest extends TestCase
{
    public function test_image_driver_reads_inline_data(): void
    {
        $http = new SequenceHttpClient([
            [
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'inlineData' => [
                                'mimeType' => 'image/png',
                                'data' => base64_encode('png-bytes'),
                            ],
                        ]],
                    ],
                ]],
            ],
        ]);

        $message = (new GeminiImage('key', 'gemini-3.1-flash-image', [], $http))
            ->chat(new UserMessage('a red circle'));

        $image = $message->getImage();
        $this->assertInstanceOf(ImageContent::class, $image);
        $this->assertSame('image/png', $image->mediaType);
        $this->assertSame('models/gemini-3.1-flash-image:generateContent', $http->requests[0]->uri);
        $body = $http->requests[0]->body;
        $this->assertSame(['TEXT', 'IMAGE'], $body['generationConfig']['responseModalities']);
    }

    public function test_speech_driver_returns_audio(): void
    {
        $http = new SequenceHttpClient([
            [
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'inlineData' => [
                                'mimeType' => 'audio/wav',
                                'data' => base64_encode('wav'),
                            ],
                        ]],
                    ],
                ]],
            ],
        ]);

        $message = (new GeminiTextToSpeech('key', 'gemini-3.1-flash-tts-preview', 'Kore', [], $http))
            ->chat(new UserMessage('hello'));

        $this->assertSame('audio/wav', $message->getAudio()->mediaType);
        $this->assertSame('Kore', $http->requests[0]->body['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName']);
    }

    public function test_transcribe_driver_reads_a_file_path(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'stt');
        file_put_contents($path, 'audio-bytes');

        $http = new SequenceHttpClient([
            [
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => 'hello world']],
                    ],
                ]],
            ],
        ]);

        $message = (new GeminiSpeechToText('key', 'gemini-3.5-transcribe', [], $http))
            ->chat(new UserMessage(new AudioContent($path, SourceType::URL, 'audio/wav')));

        $this->assertSame('hello world', $message->getContent());
        $inline = $http->requests[0]->body['contents'][0]['parts'][1]['inlineData'];
        $this->assertSame(base64_encode('audio-bytes'), $inline['data']);

        unlink($path);
    }

    public function test_video_driver_polls_until_done(): void
    {
        $http = new SequenceHttpClient([
            ['name' => 'operations/vid-1'],
            [
                'done' => true,
                'response' => [
                    'generateVideoResponse' => [
                        'generatedSamples' => [[
                            'video' => [
                                'bytesBase64Encoded' => base64_encode('mp4'),
                                'mimeType' => 'video/mp4',
                            ],
                        ]],
                    ],
                ],
            ],
        ]);

        $message = (new GeminiVideo('key', 'veo-3.1-generate-preview', 5, [], 0, $http))
            ->chat(new UserMessage('a lighthouse'));

        $this->assertSame('video/mp4', $message->getContentBlocks()[0]->mediaType);
        $this->assertSame('models/veo-3.1-generate-preview:predictLongRunning', $http->requests[0]->uri);
        $this->assertSame('operations/vid-1', $http->requests[1]->uri);
    }

    public function test_video_driver_times_out(): void
    {
        $http = new SequenceHttpClient([
            ['name' => 'operations/vid-2'],
            ['done' => false, 'name' => 'operations/vid-2'],
        ]);

        $this->expectException(ProviderException::class);

        (new GeminiVideo('key', 'veo-3.1-generate-preview', 0, [], 0, $http))
            ->chat(new UserMessage('a lighthouse'));
    }
}

class SequenceHttpClient implements HttpClientInterface
{
    /** @var array<int, HttpRequest> */
    public array $requests = [];

    /** @param  array<int, array<string, mixed>>  $responses */
    public function __construct(protected array $responses) {}

    public function request(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        $next = array_shift($this->responses) ?? [];

        return new HttpResponse(200, json_encode($next));
    }

    public function stream(HttpRequest $request): StreamInterface
    {
        throw new \RuntimeException('Streaming is not used in these tests.');
    }

    public function withBaseUri(string $baseUri): HttpClientInterface
    {
        return $this;
    }

    public function withHeaders(array $headers): HttpClientInterface
    {
        return $this;
    }

    public function withTimeout(float $timeout): HttpClientInterface
    {
        return $this;
    }
}
