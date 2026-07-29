<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Variables;

use DigitalElvis\NeuronAIStudio\Models\Variable;
use DigitalElvis\NeuronAIStudio\Repositories\VariableRepository;
use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class VariableModelTest extends TestCase
{
    public function test_variables_table_exists(): void
    {
        $this->assertTrue(\Schema::hasTable(StudioTables::name('variables')));
    }

    public function test_create_read_update_delete(): void
    {
        $variable = Variable::create([
            'name' => 'OPENAI_PROD',
            'type' => Variable::TYPE_GENERIC,
            'value' => 'plain-value',
        ]);

        $this->assertSame('plain-value', Variable::find($variable->id)?->value);

        $variable->update(['value' => 'updated']);
        $this->assertSame('updated', $variable->fresh()->value);

        $variable->delete();
        $this->assertNull(Variable::find($variable->id));
    }

    public function test_name_uniqueness(): void
    {
        Variable::create([
            'name' => 'UNIQUE_KEY',
            'type' => Variable::TYPE_GENERIC,
            'value' => 'a',
        ]);

        $this->expectException(QueryException::class);

        Variable::create([
            'name' => 'UNIQUE_KEY',
            'type' => Variable::TYPE_GENERIC,
            'value' => 'b',
        ]);
    }

    public function test_credential_encrypted_at_rest(): void
    {
        $plain = 'sk-secret-12345';

        $variable = Variable::create([
            'name' => 'SECRET_KEY',
            'type' => Variable::TYPE_CREDENTIAL,
            'value' => $plain,
        ]);

        $raw = DB::table(StudioTables::name('variables'))->where('id', $variable->id)->value('value');
        $this->assertNotSame($plain, $raw);
        $this->assertSame($plain, $variable->fresh()->value);
        $this->assertSame('*****', $variable->fresh()->display_value);
    }

    public function test_generic_stored_plaintext(): void
    {
        Variable::create([
            'name' => 'BASE_URL',
            'type' => Variable::TYPE_GENERIC,
            'value' => 'https://example.com',
        ]);

        $raw = DB::table(StudioTables::name('variables'))->where('name', 'BASE_URL')->value('value');
        $this->assertSame('https://example.com', $raw);
    }

    public function test_type_flip_generic_to_credential_encrypts(): void
    {
        $variable = Variable::create([
            'name' => 'FLIP_ME',
            'type' => Variable::TYPE_GENERIC,
            'value' => 'secret-now',
        ]);

        $variable->updateTyped(Variable::TYPE_CREDENTIAL, 'secret-now', keepValueIfBlank: false);

        $raw = DB::table(StudioTables::name('variables'))->where('id', $variable->id)->value('value');
        $this->assertNotSame('secret-now', $raw);
        $this->assertSame('secret-now', $variable->fresh()->value);
    }

    public function test_repository_find_by_name(): void
    {
        Variable::create([
            'name' => 'FIND_ME',
            'type' => Variable::TYPE_GENERIC,
            'value' => 'x',
        ]);

        $repo = app(VariableRepository::class);
        $this->assertNotNull($repo->findByName('FIND_ME'));
        $this->assertNull($repo->findByName('MISSING'));
    }

    public function test_valid_name_pattern(): void
    {
        $this->assertTrue(Variable::isValidName('OPENAI_KEY'));
        $this->assertFalse(Variable::isValidName('openai_key'));
        $this->assertFalse(Variable::isValidName('1BAD'));
    }
}
