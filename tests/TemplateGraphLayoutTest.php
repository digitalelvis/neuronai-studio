<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\TemplateRegistry;

class TemplateGraphLayoutTest extends TestCase
{
    private const NODE_WIDTH = 280;

    private const NODE_HEIGHT = 96;

    public function test_bundled_workflow_templates_have_no_overlapping_node_bounds(): void
    {
        $registry = app(TemplateRegistry::class);
        $workflows = $registry->all('workflow');

        $this->assertNotEmpty($workflows);

        foreach ($workflows as $entry) {
            $template = $registry->load('workflow', $entry['id']);
            $this->assertNotNull($template, "Failed to load workflow template: {$entry['id']}");

            $nodes = $template['graph']['nodes'] ?? [];
            $this->assertNotEmpty($nodes, "Workflow template {$entry['id']} has no nodes");

            $overlap = $this->findOverlap($nodes);

            $this->assertNull(
                $overlap,
                sprintf(
                    'Workflow template %s has overlapping nodes %s and %s (AABB %dx%d)',
                    $entry['id'],
                    $overlap[0] ?? '?',
                    $overlap[1] ?? '?',
                    self::NODE_WIDTH,
                    self::NODE_HEIGHT,
                ),
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array{0: string, 1: string}|null
     */
    private function findOverlap(array $nodes): ?array
    {
        $count = count($nodes);

        for ($i = 0; $i < $count; $i++) {
            $a = $this->bounds($nodes[$i]);

            for ($j = $i + 1; $j < $count; $j++) {
                $b = $this->bounds($nodes[$j]);

                if ($this->boundsOverlap($a, $b)) {
                    return [
                        (string) ($nodes[$i]['id'] ?? (string) $i),
                        (string) ($nodes[$j]['id'] ?? (string) $j),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{left: float, top: float, right: float, bottom: float}
     */
    private function bounds(array $node): array
    {
        $x = (float) ($node['position']['x'] ?? 0);
        $y = (float) ($node['position']['y'] ?? 0);

        return [
            'left' => $x,
            'top' => $y,
            'right' => $x + self::NODE_WIDTH,
            'bottom' => $y + self::NODE_HEIGHT,
        ];
    }

    /**
     * @param  array{left: float, top: float, right: float, bottom: float}  $a
     * @param  array{left: float, top: float, right: float, bottom: float}  $b
     */
    private function boundsOverlap(array $a, array $b): bool
    {
        return $a['left'] < $b['right']
            && $a['right'] > $b['left']
            && $a['top'] < $b['bottom']
            && $a['bottom'] > $b['top'];
    }
}
