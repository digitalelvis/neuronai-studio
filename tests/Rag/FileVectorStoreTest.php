<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Rag;

use DigitalElvis\NeuronAIStudio\Http\Livewire\KnowledgeBases\Edit;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeDocument;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\DocumentIngestService;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\EmbeddingsFactory;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\RagRetrievalService;
use DigitalElvis\NeuronAIStudio\Tests\Support\FakeEmbeddingsProvider;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Livewire\Livewire;

class FileVectorStoreTest extends TestCase
{
    protected string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storagePath = sys_get_temp_dir().'/neuronai-studio-rag-'.uniqid('', true);
        mkdir($this->storagePath, 0o755, true);

        config()->set('neuronai-studio.rag.storage_path', $this->storagePath);
        app(EmbeddingsFactory::class)->extend('fake', fn () => new FakeEmbeddingsProvider);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->storagePath);

        parent::tearDown();
    }

    protected function fileKnowledgeBase(string $name = 'Memory'): KnowledgeBase
    {
        return KnowledgeBase::create([
            'name' => $name,
            'embeddings_provider' => 'fake',
            'vector_store_driver' => 'file',
        ]);
    }

    public function test_search_returns_empty_when_store_file_is_missing(): void
    {
        $kb = $this->fileKnowledgeBase();

        $results = app(RagRetrievalService::class)->search($kb, 'anything');

        $this->assertSame([], $results);
        $this->assertFileDoesNotExist($this->storagePath.'/'.$kb->slug.'.store');
    }

    public function test_file_ingest_creates_store_file_and_search_finds_chunks(): void
    {
        $kb = $this->fileKnowledgeBase('Product Docs');

        app(DocumentIngestService::class)->ingestText(
            $kb,
            'The premium plan costs ninety nine dollars per month.',
            'pricing',
        );

        $storeFile = $this->storagePath.'/'.$kb->slug.'.store';

        $this->assertFileExists($storeFile);

        $results = app(RagRetrievalService::class)->search($kb, 'premium plan price');

        $this->assertNotEmpty($results);
        $this->assertStringContainsString('premium plan', strtolower($results[0]['content']));
    }

    public function test_ingest_with_no_extracted_chunks_marks_document_failed(): void
    {
        $kb = $this->fileKnowledgeBase();

        $path = $this->storagePath.'/empty.pdf';
        file_put_contents($path, '%PDF-1.4 empty');

        $document = app(DocumentIngestService::class)->ingestFile($kb, $path, 'empty.pdf');

        $this->assertSame(KnowledgeDocument::STATUS_FAILED, $document->status);
        $this->assertNotNull($document->error);
        $this->assertStringContainsString('No text chunks were extracted', $document->error);
        $this->assertFileDoesNotExist($this->storagePath.'/'.$kb->slug.'.store');
    }

    public function test_run_search_preview_handles_missing_store_without_crashing(): void
    {
        $kb = $this->fileKnowledgeBase();

        Livewire::test(Edit::class, ['knowledgeBase' => $kb])
            ->set('searchQuery', 'refund policy')
            ->call('runSearch')
            ->assertSet('searchError', 'No matching chunks. Ingest documents or adjust the threshold.')
            ->assertSet('searchResults', []);
    }

    protected function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
