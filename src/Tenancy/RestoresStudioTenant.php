<?php

namespace DigitalElvis\NeuronAIStudio\Tenancy;

use Illuminate\Database\Eloquent\Model;

trait RestoresStudioTenant
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withRestoredTenant(?string $tenantId, callable $callback): mixed
    {
        $id = $tenantId === null || $tenantId === '' ? null : $tenantId;

        return StudioTenancy::run($id, $callback);
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    protected function findWithoutTenantScope(string $class, mixed $id): ?Model
    {
        return StudioTenancy::withoutScope(fn () => $class::query()->find($id));
    }
}
