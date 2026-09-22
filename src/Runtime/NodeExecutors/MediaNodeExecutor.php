<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\Media\MediaArtifactStore;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\ContentBlocks\AudioContent;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\ContentBlocks\VideoContent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Workflow\WorkflowState;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class MediaNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        protected ProviderRegistry $providers,
        protected MediaArtifactStore $artifacts = new MediaArtifactStore,
    ) {}

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $type = (string) ($nodeConfig['type'] ?? '');
        $data = $nodeConfig['data'] ?? [];
        $provider = (string) ($data['provider'] ?? $this->defaultProvider($type));
        $catalog = (array) config("neuronai-studio.media.{$type}.{$provider}", []);
        $driver = (string) ($catalog['driver'] ?? '');

        if ($driver === '') {
            throw new InvalidArgumentException("Media provider [{$provider}] is not configured for [{$type}].");
        }

        $models = (array) ($catalog['models'] ?? []);
        $model = (string) ($data['model'] ?? ($models[0] ?? ''));
        $prompt = StateTemplateInterpolator::interpolate((string) ($data['prompt'] ?? ''), $state);
        if ($prompt === '' && $state->has('input')) {
            $prompt = (string) $state->get('input');
        }

        $message = $type === 'transcribe'
            ? $this->transcriptionMessage($prompt, $data, $state)
            : new UserMessage($prompt);

        $constructor = $this->constructorArgs($type, $provider, $data, $catalog);
        $apiKey = isset($data['api_key']) && is_string($data['api_key']) && $data['api_key'] !== ''
            ? $data['api_key']
            : null;

        $response = $this->providers
            ->resolveMedia($driver, $model, $constructor, $apiKey)
            ->chat($message);

        $outputKey = (string) ($data['output_key'] ?? $type.'_result');

        if ($type === 'transcribe') {
            $state->set($outputKey, (string) $response->getContent());

            return 'default';
        }

        $block = $this->mediaBlock($response->getContentBlocks());
        if ($block === null) {
            throw new InvalidArgumentException("Media provider [{$driver}] did not return a {$type} payload.");
        }

        $attachmentType = $type === 'speech' ? 'audio' : $type;
        $attachment = $this->artifacts->store($block, $attachmentType);
        $state->set($outputKey, $attachment);

        $existing = $state->get('attachments');
        $attachments = is_array($existing) ? $existing : [];
        $attachments[] = $attachment;
        $state->set('attachments', $attachments);

        return 'default';
    }

    protected function defaultProvider(string $type): string
    {
        $catalog = (array) config("neuronai-studio.media.{$type}", []);

        return (string) (array_key_first($catalog) ?: '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    protected function constructorArgs(string $type, string $provider, array $data, array $catalog): array
    {
        $args = [];

        if ($type === 'speech') {
            $voice = (string) ($data['voice'] ?? ($catalog['default_voice'] ?? ''));
            if ($provider === 'elevenlabs') {
                $args['voiceId'] = $voice;
            } else {
                $args['voice'] = $voice;
            }
        }

        if ($type === 'transcribe' && $provider === 'openai') {
            $args['language'] = (string) ($data['language'] ?? 'en');
        }

        if ($type === 'image' && $provider === 'openai') {
            $args['output_format'] = (string) ($data['output_format'] ?? 'png');
        }

        if ($type === 'video') {
            $parameters = [];
            if (($data['aspect_ratio'] ?? '') !== '') {
                $parameters['aspectRatio'] = (string) $data['aspect_ratio'];
            }
            if (($data['duration_seconds'] ?? '') !== '') {
                $parameters['durationSeconds'] = (int) $data['duration_seconds'];
            }
            if ($parameters !== []) {
                $args['parameters'] = $parameters;
            }
        }

        return $args;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function transcriptionMessage(string $prompt, array $data, WorkflowState $state): UserMessage
    {
        $attachment = $this->audioAttachment($data, $state);
        $disk = (string) config('neuronai-studio.attachments.disk', 'local');
        $path = Storage::disk($disk)->path((string) $attachment['storage_key']);
        $blocks = [];

        if ($prompt !== '') {
            $blocks[] = new TextContent($prompt);
        }

        $blocks[] = new AudioContent(
            $path,
            SourceType::URL,
            (string) ($attachment['mime_type'] ?? 'audio/mpeg'),
        );

        return new UserMessage($blocks);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function audioAttachment(array $data, WorkflowState $state): array
    {
        $key = (string) ($data['input_key'] ?? 'attachments');
        $value = $state->get($key);

        if (is_array($value) && isset($value['storage_key'])) {
            return $value;
        }

        $items = is_array($value) ? $value : [];
        foreach ($items as $item) {
            if (is_array($item) && ($item['type'] ?? '') === 'audio' && ($item['storage_key'] ?? '') !== '') {
                return $item;
            }
        }

        throw new InvalidArgumentException('Transcribe requires an audio attachment in state.');
    }

    /**
     * @param  array<int, mixed>  $blocks
     */
    protected function mediaBlock(array $blocks): ImageContent|AudioContent|VideoContent|null
    {
        foreach ($blocks as $block) {
            if ($block instanceof ImageContent || $block instanceof AudioContent || $block instanceof VideoContent) {
                return $block;
            }
        }

        return null;
    }
}
