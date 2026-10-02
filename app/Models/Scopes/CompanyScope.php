<?php

namespace App\Models\Scopes;

use App\Services\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    /**
     * Aplica el scope de aislamiento por empresa a la consulta de Eloquent.
     */
    public function apply(Builder $builder, Model $model): void
    {
        /** @var CompanyContext $context */
        $context = app(CompanyContext::class);

        if ($context->hasCompany()) {
            $builder->where('company_id', $context->getCompanyId());
        }
    }
}
