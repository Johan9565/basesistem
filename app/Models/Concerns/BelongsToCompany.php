<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Services\Tenancy\CompanyContext;

trait BelongsToCompany
{
    /**
     * Inicializa el comportamiento del trait en el modelo.
     */
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            if (empty($model->company_id)) {
                /** @var CompanyContext $context */
                $context = app(CompanyContext::class);
                if ($context->hasCompany()) {
                    $model->company_id = $context->getCompanyId();
                }
            }
        });
    }

    /**
     * Relación con la Empresa propietaria del registro.
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
