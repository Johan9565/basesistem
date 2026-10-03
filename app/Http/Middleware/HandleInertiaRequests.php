<?php

namespace App\Http\Middleware;

use App\Models\ComponentThemeModel;
use App\Models\ModulesModel;
use App\Models\NotificationsModel;
use App\Support\LandingPalette;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $userMenu = [];
        $userPermissions = []; // slugs del campo "module" del permiso (para v-if en Vue)
        $notificationUnreadCount = $user
            ? NotificationsModel::where('user_id', (string) $user->getKey())
                ->where('created_at', '>=', now()->startOfMonth())
                ->where('created_at', '<=', now())
                ->where('is_read', false)
                ->count()
            : 0;
        $role = $user ? $user->role_data()->first() : null;

        $companyContext = app(\App\Services\Tenancy\CompanyContext::class);
        $activeCompany = $companyContext->getCompany();
        $companyModules = $activeCompany ? ($activeCompany->modules ?? []) : [];
        $userCompanies = [];

        if ($user) {
            $permissionCache = app(\App\Services\Tenancy\PermissionCacheService::class);
            $userPermissions = $permissionCache->getUserPermissions($user, $companyContext->getCompanyId());

            // Obtener lista de empresas del usuario
            $memberships = $user->companyMemberships()
                ->where('status', 'active')
                ->get();

            $companyIds = $memberships->pluck('company_id')->filter()->toArray();
            if (! empty($companyIds)) {
                $userCompanies = \App\Models\Company::whereIn('_id', $companyIds)
                    ->where('status', 'active')
                    ->get()
                    ->map(function ($comp) use ($companyContext) {
                        return [
                            'id' => (string) $comp->_id,
                            'name' => $comp->name,
                            'slug' => $comp->slug,
                            'is_active' => (string) $comp->_id === (string) $companyContext->getCompanyId(),
                            'modules' => $comp->modules ?? [],
                        ];
                    })
                    ->values()
                    ->toArray();
            }

            if (! empty($userPermissions)) {
                $allModules = ModulesModel::where('status', 1)
                    ->whereIn('route', $userPermissions)
                    ->orderBy('order_index', 'asc')
                    ->get()
                    // Filtrar según módulos habilitados en la empresa activa
                    ->filter(function ($module) use ($activeCompany) {
                        if (! $activeCompany) {
                            return true;
                        }

                        $route = (string) $module->route;
                        if (str_starts_with($route, 'products') && ! $activeCompany->isModuleEnabled('inventory')) {
                            return false;
                        }
                        if (str_starts_with($route, 'services') && ! $activeCompany->isModuleEnabled('services')) {
                            return false;
                        }
                        if ($route === 'whatsapp.calendar' && ! $activeCompany->isModuleEnabled('appointments')) {
                            return false;
                        }
                        if (str_starts_with($route, 'whatsapp') && $route !== 'whatsapp.calendar' && ! $activeCompany->isModuleEnabled('whatsapp')) {
                            return false;
                        }

                        if ((string) $module->relation === '0' || $module->relation === 0) {
                            return true;
                        }

                        return is_string($module->route)
                            && $module->route !== ''
                            && Route::has($module->route);
                    })
                    ->values();

                // Rutas de módulos dropdown (relation == 0)
                $dropdownRoutes = $allModules
                    ->where('relation', 0)
                    ->pluck('route')
                    ->toArray();

                // Hijos agrupados por su relation (que apunta al route del padre)
                $childrenMap = $allModules
                    ->filter(fn ($m) => in_array($m->relation, $dropdownRoutes))
                    ->groupBy('relation');

                // Menú top-level: los que NO son hijos de un dropdown
                $userMenu = $allModules
                    ->filter(fn ($m) => ! in_array($m->relation, $dropdownRoutes))
                    ->map(function ($module) use ($childrenMap) {
                        $data = $module->toArray();
                        $data['is_dropdown'] = $module->relation == 0;
                        $data['children'] = $childrenMap
                            ->get($module->route, collect())
                            ->values()
                            ->toArray();

                        return $data;
                    })
                    // Ocultar dropdowns vacíos (sin hijos visibles)
                    ->filter(fn ($module) => ! ($module['is_dropdown'] ?? false) || ! empty($module['children']))
                    ->values();
            }
        }

        $themeDoc = ComponentThemeModel::first();

        return [
            ...parent::share($request),
            'csrf_token' => csrf_token(),
            'flash' => $request->session()->get('flash'),
            'activeTheme' => $themeDoc?->active_theme ?? 'dark',
            'landingPalette' => LandingPalette::resolve($themeDoc?->landing_palette),
            'landingPalettePreset' => $themeDoc?->landing_palette_preset ?? 'azul',
            'branding' => [
                'logo_url' => $themeDoc?->logo_url,
                'auth_side_image_url' => $themeDoc?->auth_side_image_url,
                'auth_side_image_pos_x' => (float) ($themeDoc?->auth_side_image_pos_x ?? 50),
                'auth_side_image_pos_y' => (float) ($themeDoc?->auth_side_image_pos_y ?? 50),
            ],
            'auth' => [
                'user' => $user,
                'role' => $role?->role ?? null,
                'menu' => $userMenu,
                'notification_unread_count' => $notificationUnreadCount,
                'can' => $userPermissions,
                'active_company' => $activeCompany ? [
                    'id' => (string) $activeCompany->_id,
                    'name' => $activeCompany->name,
                    'slug' => $activeCompany->slug,
                    'modules' => $activeCompany->modules ?? [],
                ] : null,
                'user_companies' => $userCompanies,
                'company_modules' => $companyModules,
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
