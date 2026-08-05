<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Tools;

use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\Toolkits\ToolkitInterface;

/**
 * Injects a {@see ToolContext} snapshot into opt-in tools and toolkit children.
 */
final class ToolContextInjector
{
    public static function apply(mixed $tool, ?ToolContext $context): mixed
    {
        if ($context === null) {
            return $tool;
        }

        if ($tool instanceof ToolContextAware) {
            $tool->setToolContext($context);
        }

        if ($tool instanceof ToolkitInterface) {
            return self::wrapToolkit($tool, $context);
        }

        return $tool;
    }

    /**
     * @param  array<int, mixed>  $tools
     * @return array<int, mixed>
     */
    public static function applyMany(array $tools, ?ToolContext $context): array
    {
        if ($context === null) {
            return $tools;
        }

        return array_map(
            static fn (mixed $tool): mixed => self::apply($tool, $context),
            $tools,
        );
    }

    /**
     * Normalize config.tool_context (ToolContext|array|null) into a VO or null.
     */
    public static function fromConfig(mixed $raw): ?ToolContext
    {
        if ($raw instanceof ToolContext) {
            return $raw;
        }

        if (is_array($raw)) {
            return ToolContext::fromArray($raw);
        }

        return null;
    }

    protected static function wrapToolkit(ToolkitInterface $toolkit, ToolContext $context): ToolkitInterface
    {
        return new class($toolkit, $context) implements ToolkitInterface
        {
            public function __construct(
                protected ToolkitInterface $inner,
                protected ToolContext $toolContext,
            ) {}

            public function guidelines(): ?string
            {
                return $this->inner->guidelines();
            }

            public function tools(): array
            {
                return array_map(
                    function (ToolInterface $tool): ToolInterface {
                        if ($tool instanceof ToolContextAware) {
                            $tool->setToolContext($this->toolContext);
                        }

                        return $tool;
                    },
                    $this->inner->tools(),
                );
            }

            public function exclude(array $classes): ToolkitInterface
            {
                $this->inner->exclude($classes);

                return $this;
            }

            public function only(array $classes): ToolkitInterface
            {
                $this->inner->only($classes);

                return $this;
            }

            public function with(string $class, callable $callback): ToolkitInterface
            {
                $this->inner->with($class, $callback);

                return $this;
            }
        };
    }
}
