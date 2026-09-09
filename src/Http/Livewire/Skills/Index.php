<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Skills;

use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillArchiveImporter;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class Index extends Component
{
    use WithFileUploads;

    public string $filter = '';

    public string $category = '';

    public bool $ownedOnly = false;

    public bool $showUploadModal = false;

    public bool $showGithubModal = false;

    public bool $showCreateMenu = false;

    public $uploadFile = null;

    public bool $overwriteOnImport = false;

    public string $githubUrl = '';

    public string $importError = '';

    public function delete(int $id): void
    {
        $skill = SkillDefinition::findOrFail($id);

        if ($skill->isLockedByPlugin()) {
            session()->flash('error', __('neuronai-studio::skills.locked_by_plugin'));

            return;
        }

        $skill->delete();
        session()->flash('success', __('neuronai-studio::flash.skill_deleted'));
    }

    public function openUploadModal(): void
    {
        $this->resetImportState();
        $this->showCreateMenu = false;
        $this->showUploadModal = true;
    }

    public function openGithubModal(): void
    {
        $this->resetImportState();
        $this->showCreateMenu = false;
        $this->showGithubModal = true;
    }

    public function closeModals(): void
    {
        $this->showUploadModal = false;
        $this->showGithubModal = false;
        $this->showCreateMenu = false;
        $this->resetImportState();
    }

    public function importUpload(): void
    {
        $this->importError = '';

        $this->validate([
            'uploadFile' => 'required|file|max:10240',
        ]);

        $original = strtolower((string) $this->uploadFile->getClientOriginalName());
        if (! str_ends_with($original, '.zip') && ! str_ends_with($original, '.skill')) {
            $this->importError = __('neuronai-studio::skills.upload_invalid_extension');

            return;
        }

        $path = $this->uploadFile->storeAs(
            'neuronai-studio/skill-uploads',
            uniqid('skill-', true).'.zip',
            'local'
        );

        $absolute = Storage::disk('local')->path($path);

        try {
            $skill = app(SkillArchiveImporter::class)->import($absolute, [
                'overwrite' => $this->overwriteOnImport,
                'source' => SkillDefinition::SOURCE_UPLOAD,
            ]);

            Storage::disk('local')->delete($path);
            $this->closeModals();
            session()->flash('success', __('neuronai-studio::flash.skill_imported'));
            $this->redirect(route('neuronai-studio.skills.show', $skill));
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            $this->importError = $e->getMessage();
        }
    }

    public function importGithub(): void
    {
        $this->importError = '';

        $this->validate([
            'githubUrl' => 'required|url|max:1000',
        ]);

        try {
            $skill = app(SkillGitHubImporter::class)->import($this->githubUrl, [
                'overwrite' => $this->overwriteOnImport,
            ]);

            $this->closeModals();
            session()->flash('success', __('neuronai-studio::flash.skill_imported'));
            $this->redirect(route('neuronai-studio.skills.show', $skill));
        } catch (Throwable $e) {
            $this->importError = $e->getMessage();
        }
    }

    protected function resetImportState(): void
    {
        $this->uploadFile = null;
        $this->githubUrl = '';
        $this->importError = '';
        $this->overwriteOnImport = false;
        $this->resetValidation();
    }

    public function render()
    {
        $categories = config('neuronai-studio.skills.categories', []);

        $skills = SkillDefinition::query()
            ->orderBy('slug')
            ->when($this->filter !== '', function ($query) {
                $needle = strtolower($this->filter);
                $query->where(function ($inner) use ($needle) {
                    $inner->whereRaw('LOWER(slug) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(display_name, ?)) LIKE ?', ['', "%{$needle}%"])
                        ->orWhereRaw('LOWER(description) LIKE ?', ["%{$needle}%"]);
                });
            })
            ->when($this->category !== '', function ($query) {
                $cat = $this->category;
                $query->where(function ($inner) use ($cat) {
                    $inner->whereJsonContains('categories', $cat)
                        ->orWhere('categories', 'like', '%"'.$cat.'"%');
                });
            })
            ->when($this->ownedOnly, fn ($query) => $query->whereNotNull('source'))
            ->get();

        return view('neuronai-studio::livewire.skills.index', [
            'skills' => $skills,
            'categories' => $categories,
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [['label' => __('neuronai-studio::ui.breadcrumbs.skills')]],
            title: __('neuronai-studio::ui.breadcrumbs.skills'),
            headerActions: view('neuronai-studio::partials.header-actions.skills-actions')->render(),
        ));
    }
}
