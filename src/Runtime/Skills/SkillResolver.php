<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills;

class SkillResolver
{
    public function __construct(
        protected SkillRepository $repository,
    ) {}

    /**
     * @param  array<int, array<string, mixed>|string>  $bindings
     */
    public function resolve(array $bindings): SkillCatalog
    {
        $entries = [];
        $seen = [];

        foreach ($bindings as $binding) {
            $ref = is_array($binding) ? (string) ($binding['ref'] ?? '') : (string) $binding;

            if ($ref === '' || isset($seen[$ref])) {
                continue;
            }

            $definition = $this->repository->findByRef($ref);

            if ($definition === null) {
                continue;
            }

            $seen[$ref] = true;
            $entries[] = new SkillCatalogEntry(
                name: $definition->slug(),
                description: $definition->description(),
                definition: $definition,
            );
        }

        return new SkillCatalog($entries);
    }
}
