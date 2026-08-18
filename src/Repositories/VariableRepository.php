<?php

namespace DigitalElvis\NeuronAIStudio\Repositories;

use DigitalElvis\NeuronAIStudio\Models\Variable;
use Illuminate\Database\Eloquent\Collection;

class VariableRepository
{
    public function findByName(string $name): ?Variable
    {
        return Variable::findPreferred('name', $name);
    }

    /** @return Collection<int, Variable> */
    public function allOrdered(): Collection
    {
        return Variable::query()->orderBy('name')->get();
    }

    /**
     * Resolve plaintext value by name or throw.
     *
     * @throws \DigitalElvis\NeuronAIStudio\Exceptions\VariableResolutionException
     */
    public function resolveValue(string $name): string
    {
        $variable = $this->findByName($name);

        if ($variable === null) {
            throw new \DigitalElvis\NeuronAIStudio\Exceptions\VariableResolutionException(
                "Studio variable [{$name}] was not found."
            );
        }

        $value = (string) ($variable->value ?? '');

        if ($value === '' && $variable->isCredential()) {
            throw new \DigitalElvis\NeuronAIStudio\Exceptions\VariableResolutionException(
                "Studio credential [{$name}] is empty."
            );
        }

        return $value;
    }
}
