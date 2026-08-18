<?php

namespace DigitalElvis\NeuronAIStudio\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! StudioTenancy::scopesShared() || StudioTenancy::skipsScope()) {
            return;
        }

        $column = $model->getTable().'.tenant_id';

        if (StudioTenancy::isCentral()) {
            $builder->whereNull($column);

            return;
        }

        $tenantId = StudioTenancy::id();

        if ($tenantId === null) {
            $builder->whereRaw('0 = 1');

            return;
        }

        $builder->where(function (Builder $query) use ($column, $tenantId) {
            $query->where($column, $tenantId)->orWhereNull($column);
        });
    }
}
