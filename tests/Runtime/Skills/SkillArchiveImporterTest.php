<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Skills;

use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillArchiveImporter;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Illuminate\Support\Facades\File;
use ZipArchive;

class SkillArchiveImporterTest extends TestCase
{
    public function test_imports_zip_with_scripts_and_references(): void
    {
        $dir = sys_get_temp_dir().'/skill-imp-'.uniqid();
        File::ensureDirectoryExists($dir.'/scripts');
        File::ensureDirectoryExists($dir.'/references');
        File::put($dir.'/SKILL.md', <<<'MD'
---
name: demo-skill
description: Demo skill for archive import tests.
---

# Demo
MD);
        File::put($dir.'/scripts/hello.sh', "#!/bin/bash\necho hi\n");
        File::put($dir.'/references/notes.md', "# Notes\n");

        $zipPath = $dir.'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFile($dir.'/SKILL.md', 'demo-skill/SKILL.md');
        $zip->addFile($dir.'/scripts/hello.sh', 'demo-skill/scripts/hello.sh');
        $zip->addFile($dir.'/references/notes.md', 'demo-skill/references/notes.md');
        $zip->close();

        $skill = app(SkillArchiveImporter::class)->import($zipPath, [
            'source' => SkillDefinition::SOURCE_UPLOAD,
        ]);

        $this->assertSame('demo-skill', $skill->slug);
        $this->assertSame(SkillDefinition::SOURCE_UPLOAD, $skill->source);
        $this->assertArrayHasKey('scripts/hello.sh', $skill->files());
        $this->assertArrayHasKey('references/notes.md', $skill->files());

        File::deleteDirectory($dir);
        File::delete($zipPath);
    }
}
