<?php

namespace App\Traits;

use App\Helpers\TenantHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    /**
     * Boot the trait.
     */
    protected static function bootBelongsToTenant(): void
    {
        // Automatically set tenant_id when creating models
        static::creating(function (Model $model) {
            if (!$model->tenant_id && TenantHelper::hasTenant()) {
                $model->tenant_id = TenantHelper::currentId();
            }
        });

        // Automatically scope queries by tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (TenantHelper::hasTenant()) {
                $builder->where('tenant_id', TenantHelper::currentId());
            }
        });
    }

    /**
     * Get the tenant that owns the model.
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    /**
     * Scope query to exclude tenant filtering (for admin purposes).
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * Scope query to specific tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->withoutGlobalScope('tenant')->where('tenant_id', $tenantId);
    }

    /**
     * Check if model belongs to current tenant.
     */
    public function belongsToCurrentTenant(): bool
    {
        return $this->tenant_id === TenantHelper::currentId();
    }
}
