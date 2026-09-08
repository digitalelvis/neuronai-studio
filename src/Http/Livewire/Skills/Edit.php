<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Skills;

use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillParser;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillPathPolicy;
use DigitalElvis\NeuronAIStudio\Support\ResolvesOptionalRouteModel;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Edit extends Component
{
    use ResolvesOptionalRouteModel;

    public ?SkillDefinition $skill = null;

    public string $slug = '';

    public string $displayName = '';

    /** @var list<string> */
    public array $selectedCategories = [];

    public string $coverImage = '';

    public string $description = '';

    public string $license = '';

    public string $compatibility = '';

    public string $body = '';

    /** @var array<int, array{path: string, content: string}> */
    public array $resourceFiles = [];

    public function mount(mixed $skill = null): void
    {
        $this->skill = $this->resolveOptionalRouteModel($skill, SkillDefinition::class);

        if ($this->skill?->exists) {
            $this->slug = $this->skill->slug;
            $this->displayName = (string) ($this->skill->display_name ?? '');
            $this->selectedCategories = $this->skill->categoryList();
            $this->coverImage = (string) ($this->skill->cover_image ?? '');
            $this->description = $this->skill->description;
            $this->license = (string) ($this->skill->license ?? '');
            $this->compatibility = (string) ($this->skill->compatibility ?? '');
            $this->body = (string) ($this->skill->body ?? '');
            $this->resourceFiles = [];

            foreach ($this->skill->files() as $path => $content) {
                $this->resourceFiles[] = ['path' => $path, 'content' => $content];
            }
        }
    }

    public function addResourceFile(string $prefix = 'references/'): void
    {
        $this->resourceFiles[] = ['path' => $prefix, 'content' => ''];
    }

    public function removeResourceFile(int $index): void
    {
        unset($this->resourceFiles[$index]);
        $this->resourceFiles = array_values($this->resourceFiles);
    }

    public function save(): void
    {
        app(SkillParser::class)->validateFields($this->slug, $this->description);

        $validated = $this->validate([
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(StudioTables::name('skill_definitions'), 'slug')->ignore($this->skill?->id),
            ],
            'displayName' => 'nullable|string|max:255',
            'selectedCategories' => 'array',
            'selectedCategories.*' => 'string|max:64',
            'coverImage' => 'nullable|string|max:1000',
            'description' => 'required|string|max:1024',
            'license' => 'nullable|string|max:255',
            'compatibility' => 'nullable|string|max:500',
            'body' => 'nullable|string',
            'resourceFiles' => 'array',
            'resourceFiles.*.path' => 'nullable|string|max:255',
            'resourceFiles.*.content' => 'nullable|string',
        ]);

        $policy = app(SkillPathPolicy::class);
        $resources = [];

        foreach ($validated['resourceFiles'] ?? [] as $file) {
            $path = $policy->normalize(trim((string) ($file['path'] ?? '')));
            $content = (string) ($file['content'] ?? '');

            if ($path === '' || $content === '') {
                continue;
            }

            if (! $policy->isAllowed($path)) {
                $this->addError('resourceFiles', __('neuronai-studio::skills.invalid_resource_path'));

                return;
            }

            $resources[$path] = $content;
        }

        $allowedKeys = SkillDefinition::categoryKeys();
        $categories = array_values(array_filter(
            $validated['selectedCategories'] ?? [],
            fn ($key) => is_string($key) && in_array($key, $allowedKeys, true)
        ));

        $payload = [
            'slug' => $validated['slug'],
            'display_name' => ($validated['displayName'] ?? '') !== '' ? $validated['displayName'] : $validated['slug'],
            'categories' => $categories === [] ? null : $categories,
            'cover_image' => ($validated['coverImage'] ?? '') !== '' ? $validated['coverImage'] : null,
            'description' => $validated['description'],
            'license' => $validated['license'] !== '' ? $validated['license'] : null,
            'compatibility' => $validated['compatibility'] !== '' ? $validated['compatibility'] : null,
            'body' => $validated['body'] ?? null,
            'resources' => $resources === [] ? null : $resources,
            'source' => $this->skill?->source ?? SkillDefinition::SOURCE_STUDIO,
        ];

        if ($this->skill?->exists) {
            $this->skill->update($payload);
        } else {
            $this->skill = SkillDefinition::create($payload);
        }

        session()->flash('success', __('neuronai-studio::flash.skill_saved'));

        $this->redirect(route('neuronai-studio.skills.show', $this->skill));
    }

    public function render()
    {
        $parser = app(SkillParser::class);
        $preview = $parser->buildFrontmatter([
            'name' => $this->slug ?: 'skill-name',
            'description' => $this->description ?: 'Description',
            'license' => $this->license ?: null,
            'compatibility' => $this->compatibility ?: null,
        ])."\n\n".($this->body ?? '');

        return view('neuronai-studio::livewire.skills.edit', [
            'preview' => $preview,
            'categories' => config('neuronai-studio.skills.categories', []),
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [
                ['label' => __('neuronai-studio::ui.breadcrumbs.skills'), 'url' => route('neuronai-studio.skills.index')],
                ['label' => $this->skill?->exists ? $this->skill->displayName() : __('neuronai-studio::ui.actions.new_skill')],
            ],
            title: $this->skill?->exists ? 'Edit Skill' : 'Create Skill',
        ));
    }
}
