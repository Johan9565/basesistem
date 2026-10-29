<?php

namespace App\Http\Middleware;

use App\Models\ComponentThemeModel;
use App\Models\ModulesModel;
use App\Models\NotificationsModel;
use App\Models\PermissionsModel;
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

            // Obtener lista de empresas accesibles del usuario
            if ($user->canSelectAllCompanies()) {
                $compQuery = \App\Models\Company::query();
                $allComps = $compQuery->where('status', '!=', 'inactive')->orderBy('name', 'asc')->get();
                if ($allComps->isEmpty()) {
                    $allComps = \App\Models\Company::orderBy('name', 'asc')->get();
                }
                $userCompanies = $allComps->map(function ($comp) use ($companyContext) {
                    return [
                        'id' => (string) $comp->_id,
                        'name' => $comp->name,
                        'slug' => $comp->slug,
                        'is_active' => (string) $comp->_id === (string) $companyContext->getCompanyId(),
                        'modules' => $comp->modules ?? [],
                    ];
                })->values()->toArray();
            } else {
                $companyIds = $user->getAssignedCompanyIds();
                if (! empty($companyIds)) {
                    $userCompanies = \App\Models\Company::whereInMongoIds($companyIds)
                        ->where('status', '!=', 'inactive')
                        ->orderBy('name', 'asc')
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
            }

            // Si activeCompany es null pero existen empresas disponibles, auto-sincronizar la primera
            if (! $activeCompany && ! empty($userCompanies)) {
                $firstComp = \App\Models\Company::whereInMongoIds([$userCompanies[0]['id']])->first();
                if ($firstComp) {
                    $activeCompany = $firstComp;
                    $companyContext->setCompany($activeCompany);
                    $request->session()->put('active_company_id', (string) $activeCompany->_id);
                    $userCompanies[0]['is_active'] = true;
                    $companyModules = $activeCompany->modules ?? [];
                }
            }

            if (ModulesModel::where('route', 'whatsapp.chat-test')->doesntExist()) {
                ModulesModel::create([
                    'route' => 'whatsapp.chat-test',
                    'name' => 'Chat Test',
                    'relation' => 'whatsapp',
                    'order_index' => 4,
                    'status' => 1,
                ]);
            }
            if (PermissionsModel::where('name', 'whatsapp.chat-test')->doesntExist()) {
                PermissionsModel::create([
                    'name' => 'whatsapp.chat-test',
                    'description' => 'Módulo Chat Test (Simulador)',
                    'module' => 'whatsapp.chat-test',
                    'status' => 1,
                ]);
                PermissionsModel::create([
                    'name' => 'whatsapp.chat-test.view',
                    'description' => 'Ver y acceder a Chat Test',
                    'module' => 'whatsapp.chat-test',
                    'status' => 1,
                ]);
                PermissionsModel::create([
                    'name' => 'whatsapp.chat-test.send',
                    'description' => 'Enviar mensajes en Chat Test',
                    'module' => 'whatsapp.chat-test',
                    'status' => 1,
                ]);
                PermissionsModel::create([
                    'name' => 'whatsapp.chat-test.reset',
                    'description' => 'Reiniciar Chat Test',
                    'module' => 'whatsapp.chat-test',
                    'status' => 1,
                ]);
            }

            if (ModulesModel::where('route', 'orders')->doesntExist()) {
                ModulesModel::create([
                    'route' => 'orders',
                    'name' => 'Pedidos',
                    'relation' => 1,
                    'order_index' => 3,
                    'status' => 1,
                ]);
            }
            if (PermissionsModel::where('name', 'orders.view')->doesntExist()) {
                PermissionsModel::create([
                    'name' => 'orders',
                    'description' => 'Módulo Pedidos y Ventas',
                    'module' => 'orders',
                    'status' => 1,
                ]);
                PermissionsModel::create([
                    'name' => 'orders.view',
                    'description' => 'Ver listado y detalles de pedidos',
                    'module' => 'orders',
                    'status' => 1,
                ]);
                PermissionsModel::create([
                    'name' => 'orders.create',
                    'description' => 'Crear pedidos y descontar inventario',
                    'module' => 'orders',
                    'status' => 1,
                ]);
                PermissionsModel::create([
                    'name' => 'orders.edit',
                    'description' => 'Modificar pedidos y estado',
                    'module' => 'orders',
                    'status' => 1,
                ]);
                PermissionsModel::create([
                    'name' => 'orders.delete',
                    'description' => 'Eliminar o cancelar pedidos',
                    'module' => 'orders',
                    'status' => 1,
                ]);
            }

            if (! empty($userPermissions)) {
                $allModules = ModulesModel::where('status', 1)
                    ->orderBy('order_index', 'asc')
                    ->get()
                    // Filtrar según permisos de usuario y módulos habilitados en la empresa activa
                    ->filter(function ($module) use ($user, $userPermissions, $activeCompany) {
                        $route = (string) $module->route;

                        // El módulo de roles es EXCLUSIVO para el Super Administrador
                        if ($route === 'roles' && ! $user->isSuperAdmin()) {
                            return false;
                        }

                        // Dropdowns contenedores se filtrarán por sus hijos
                        if ($module->relation !== 0 && (string) $module->relation !== '0') {
                            $hasAccess = in_array($route, $userPermissions, true)
                                || in_array("{$route}.view", $userPermissions, true);

                            if (! $hasAccess) {
                                $hasAccess = collect($userPermissions)->contains(fn ($p) => str_starts_with($p, "{$route}."));
                            }

                            if (! $hasAccess) {
                                return false;
                            }
                        }

                        if (! $activeCompany) {
                            if ((string) $module->relation === '0' || $module->relation === 0) {
                                return true;
                            }

                            return is_string($module->route) && $module->route !== '' && Route::has($module->route);
                        }

                        if (str_starts_with($route, 'products') && ! $activeCompany->isModuleEnabled('inventory')) {
                            return false;
                        }
                        if (str_starts_with($route, 'orders') && ! $activeCompany->isModuleEnabled('orders')) {
                            return false;
                        }
                        if (str_starts_with($route, 'services') && ! $activeCompany->isModuleEnabled('services')) {
                            return false;
                        }
                        if ($route === 'whatsapp.calendar' && ! $activeCompany->isModuleEnabled('appointments')) {
                            return false;
                        }
                        if ($route === 'whatsapp.chat-test' && ! $activeCompany->isModuleEnabled('chat-test') && ! $activeCompany->isModuleEnabled('whatsapp')) {
                            return false;
                        }
                        if (str_starts_with($route, 'whatsapp') && $route !== 'whatsapp.calendar' && $route !== 'whatsapp.chat-test' && ! $activeCompany->isModuleEnabled('whatsapp')) {
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

                // Clasificación: Módulos propios de la empresa vs Módulos globales de plataforma
                $isCompanyRoute = function (string $route, mixed $relation = null): bool {
                    $companyKeys = [
                        'products',
                        'orders',
                        'services',
                        'whatsapp.calendar',
                        'whatsapp',
                        'whatsapp.instances',
                        'whatsapp.conversations',
                        'whatsapp.send',
                        'whatsapp.chat-test',
                        'chat-test',
                        'employees',
                    ];

                    if (in_array($route, $companyKeys, true)) {
                        return true;
                    }

                    if ($relation === 'whatsapp' || (string) $relation === 'whatsapp') {
                        return true;
                    }

                    return str_starts_with($route, 'whatsapp.')
                        || str_starts_with($route, 'products.')
                        || str_starts_with($route, 'orders.')
                        || str_starts_with($route, 'services.');
                };

                // 1. Menú Superior (Global / Plataforma / SaaS)
                $topModulesList = $allModules
                    ->filter(fn ($m) => ! $isCompanyRoute((string) $m->route, $m->relation))
                    ->values();

                $topDropdownRoutes = $topModulesList
                    ->where('relation', 0)
                    ->pluck('route')
                    ->toArray();

                $topChildrenMap = $topModulesList
                    ->filter(fn ($m) => in_array($m->relation, $topDropdownRoutes))
                    ->groupBy('relation');

                $userMenu = $topModulesList
                    ->filter(fn ($m) => ! in_array($m->relation, $topDropdownRoutes))
                    ->map(function ($module) use ($topChildrenMap) {
                        $data = $module->toArray();
                        $data['is_dropdown'] = $module->relation == 0;
                        $data['children'] = $topChildrenMap
                            ->get($module->route, collect())
                            ->values()
                            ->toArray();

                        return $data;
                    })
                    ->filter(fn ($module) => ! ($module['is_dropdown'] ?? false) || ! empty($module['children']))
                    ->values()
                    ->toArray();

                // 2. Menú Lateral (Operativo de la Empresa Activa)
                $companyMenu = [];
                if ($activeCompany) {
                    $compModules = $allModules
                        ->filter(fn ($m) => $isCompanyRoute((string) $m->route, $m->relation))
                        ->values();

                    $sections = [
                        'whatsapp' => [
                            'key' => 'whatsapp',
                            'title' => 'WhatsApp CRM',
                            'icon' => 'chat',
                            'items' => [],
                        ],
                        'appointments' => [
                            'key' => 'appointments',
                            'title' => 'Agenda & Citas',
                            'icon' => 'calendar',
                            'items' => [],
                        ],
                        'operations' => [
                            'key' => 'operations',
                            'title' => 'Operaciones',
                            'icon' => 'box',
                            'items' => [],
                        ],
                        'team' => [
                            'key' => 'team',
                            'title' => 'Equipo & Roles',
                            'icon' => 'users',
                            'items' => [],
                        ],
                        'other' => [
                            'key' => 'other',
                            'title' => 'Módulos',
                            'icon' => 'grid',
                            'items' => [],
                        ],
                    ];

                    foreach ($compModules as $mod) {
                        if ($mod->relation === 0 || (string) $mod->relation === '0') {
                            continue;
                        }

                        $r = (string) $mod->route;
                        $item = [
                            'id' => (string) $mod->_id,
                            'name' => $mod->name,
                            'route' => $mod->route,
                            'icon' => $mod->icon ?? null,
                        ];

                        if (str_starts_with($r, 'whatsapp.') && $r !== 'whatsapp.calendar') {
                            $sections['whatsapp']['items'][] = $item;
                        } elseif ($r === 'whatsapp.calendar') {
                            $sections['appointments']['items'][] = $item;
                        } elseif (in_array($r, ['products', 'orders', 'services'], true) || str_starts_with($r, 'products.') || str_starts_with($r, 'orders.') || str_starts_with($r, 'services.')) {
                            $sections['operations']['items'][] = $item;
                        } elseif (in_array($r, ['employees', 'roles'], true)) {
                            $sections['team']['items'][] = $item;
                        } else {
                            $sections['other']['items'][] = $item;
                        }
                    }

                    $companyMenu = collect($sections)
                        ->filter(fn ($sec) => ! empty($sec['items']))
                        ->values()
                        ->toArray();
                }
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
                'menu' => $userMenu ?? [],
                'top_menu' => $userMenu ?? [],
                'company_menu' => $companyMenu ?? [],
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
