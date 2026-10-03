<?php

namespace App\Services\Tenancy;

use App\Models\Company;

class CompanyContext
{
    protected ?Company $company = null;

    protected ?string $companyId = null;

    /**
     * Establece la empresa activa en el contexto.
     */
    public function setCompany(?Company $company): void
    {
        $this->company = $company;
        $this->companyId = $company ? (string) $company->_id : null;
    }

    /**
     * Establece el ID de la empresa activa y carga la entidad si es necesario.
     */
    public function setCompanyId(?string $companyId): void
    {
        $this->companyId = $companyId;
        if (! empty($companyId)) {
            $this->company = Company::find($companyId);
        } else {
            $this->company = null;
        }
    }

    /**
     * Obtiene la empresa activa.
     */
    public function getCompany(): ?Company
    {
        if (! $this->company && ! empty($this->companyId)) {
            $this->company = Company::find($this->companyId);
        }

        return $this->company;
    }

    /**
     * Obtiene el identificador string de la empresa activa.
     */
    public function getCompanyId(): ?string
    {
        return $this->companyId ?: ($this->company ? (string) $this->company->_id : null);
    }

    /**
     * Verifica si hay una empresa activa en el contexto.
     */
    public function hasCompany(): bool
    {
        return ! empty($this->getCompanyId());
    }

    /**
     * Verifica si un módulo específico está habilitado para la empresa activa.
     */
    public function isModuleEnabled(string $module): bool
    {
        $company = $this->getCompany();
        if (! $company) {
            return false;
        }

        return $company->isModuleEnabled($module);
    }

    /**
     * Limpia el contexto activo.
     */
    public function clear(): void
    {
        $this->company = null;
        $this->companyId = null;
    }
}
