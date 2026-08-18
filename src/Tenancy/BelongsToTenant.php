<?php

namespace DigitalElvis\NeuronAIStudio\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait BelongsToTenant
{
    public function initializeBelongsToTenant(): void
    {
        $this->mergeFillable(['tenant_id']);
    }

    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::saving(function (Model $model) {
            if (StudioTenancy::scopesShared() && ! $model->exists) {
                if (! StudioTenancy::isCentral()) {
                    $tenantId = StudioTenancy::id();

                    if ($tenantId === null) {
                        throw TenantRequiredException::forWrite();
                    }

                    $model->setAttribute('tenant_id', $tenantId);
                }
            }

            $tenantId = $model->getAttribute('tenant_id');
            $model->setAttribute(
                'tenant_scope',
                $tenantId === null || $tenantId === '' ? '' : (string) $tenantId
            );
        });
    }

    /**
     * Restrict to the tenant that would own a new write (excludes globals).
     */
    public function scopeInCurrentTenant(Builder $query): Builder
    {
        if (! StudioTenancy::scopesShared()) {
            return $query;
        }

        $column = $query->getModel()->getTable().'.tenant_id';

        if (StudioTenancy::isCentral() || StudioTenancy::id() === null) {
            return $query->whereNull($column);
        }

        return $query->where($column, StudioTenancy::id());
    }

    public static function findBySlug(string $slug): ?static
    {
        return static::findPreferred('slug', $slug);
    }

    public static function findBySlugOrFail(string $slug): static
    {
        $model = static::findBySlug($slug);

        if ($model === null) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(static::class);
        }

        return $model;
    }

    public static function findPreferred(string $column, string $value): ?static
    {
        return static::query()
            ->where($column, $value)
            ->orderByRaw('case when tenant_id is null then 1 else 0 end')
            ->first();
    }
}
