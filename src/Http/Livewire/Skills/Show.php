<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Skills;

use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillParser;
use DigitalElvis\NeuronAIStudio\Support\ResolvesOptionalRouteModel;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Livewire\Component;

class Show extends Component
{
    use ResolvesOptionalRouteModel;

    public SkillDefinition $skill;

    public string $selectedPath = 'SKILL.md';

    public function mount(mixed $skill): void
    {
        $this->skill = $this->resolveOptionalRouteModel($skill, SkillDefinition::class)
            ?? abort(404);

        if (! $this->skill->exists) {
            abort(404);
        }
    }

    public function selectFile(string $path): void
    {
        $this->selectedPath = $path;
    }

    public function deleteSkill(): void
    {
        if ($this->skill->isLockedByPlugin()) {
            session()->flash('error', __('neuronai-studio::skills.locked_by_plugin'));

            return;
        }

        $this->skill->delete();
        session()->flash('success', __('neuronai-studio::flash.skill_deleted'));
        $this->redirect(route('neuronai-studio.skills.index'));
    }

    public function render()
    {
        $parser = app(SkillParser::class);
        $frontmatter = $parser->buildFrontmatter(array_filter([
            'name' => $this->skill->slug,
            'description' => $this->skill->description,
            'license' => $this->skill->license,
            'compatibility' => $this->skill->compatibility,
            'metadata' => $this->skill->metadata,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []));

        $viewer = $this->viewerPayload($frontmatter);

        return view('neuronai-studio::livewire.skills.show', [
            'categories' => config('neuronai-studio.skills.categories', []),
            'fileTree' => $this->skill->fileTree(),
            'frontmatter' => $frontmatter,
            'viewer' => $viewer,
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [
                ['label' => __('neuronai-studio::ui.breadcrumbs.skills'), 'url' => route('neuronai-studio.skills.index')],
                ['label' => $this->skill->displayName()],
            ],
            title: $this->skill->displayName(),
            contentFlush: true,
        ));
    }

    /** @return array{type: string, title: string, content: string} */
    protected function viewerPayload(string $frontmatter): array
    {
        if ($this->selectedPath === 'SKILL.md') {
            return [
                'type' => 'skill_md',
                'title' => 'SKILL.md',
                'content' => (string) $this->skill->body,
                'yaml' => $frontmatter,
            ];
        }

        $files = $this->skill->files();
        $raw = $files[$this->selectedPath] ?? null;

        if ($raw === null) {
            return [
                'type' => 'missing',
                'title' => $this->selectedPath,
                'content' => '',
                'yaml' => '',
            ];
        }

        if (str_starts_with($raw, 'base64:')) {
            return [
                'type' => 'binary',
                'title' => $this->selectedPath,
                'content' => __('neuronai-studio::skills.binary_preview'),
                'yaml' => '',
            ];
        }

        $extension = strtolower(pathinfo($this->selectedPath, PATHINFO_EXTENSION));

        return [
            'type' => in_array($extension, ['md', 'markdown'], true) ? 'markdown' : 'code',
            'title' => $this->selectedPath,
            'content' => $raw,
            'yaml' => '',
        ];
    }
}
