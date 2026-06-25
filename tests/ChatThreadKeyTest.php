<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests;

use ElvisLopesDigital\NeuronAIStudio\Support\ChatThreadKey;

class ChatThreadKeyTest extends TestCase
{
    public function test_for_agent_scopes_thread_id(): void
    {
        $key = ChatThreadKey::forAgent(5, '550e8400-e29b-41d4-a716-446655440000');

        $this->assertSame('agent:5:550e8400-e29b-41d4-a716-446655440000', $key);
    }

    public function test_for_agent_generates_uuid_when_missing(): void
    {
        $key = ChatThreadKey::forAgent(3);

        $this->assertStringStartsWith('agent:3:', $key);
        $this->assertNotSame('agent:3:', $key);
    }

    public function test_public_id_extracts_uuid_portion(): void
    {
        $publicId = ChatThreadKey::publicId('agent:12:550e8400-e29b-41d4-a716-446655440000');

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $publicId);
    }
}
