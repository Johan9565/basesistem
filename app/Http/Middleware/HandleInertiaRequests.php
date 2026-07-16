<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;
use App\Models\ModulesModel;
use App\Models\PermissionsModel;
use App\Models\ComponentThemeModel;
use App\Models\NotificationsModel;
use App\Support\LandingPalette;

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
        $userMenu        = [];
        $userPermissions = []; // slugs del campo "module" del permiso (para v-if en Vue)
        $notificationUnreadCount = $user
            ? NotificationsModel::where('user_id', (string) $user->getKey())
                ->where('created_at', '>=', now()->startOfMonth())
                ->where('created_at', '<=', now())
                ->where('is_read', false)
                ->count()
            : 0;
        $role = $user?->role_data()->first();

        if ($user && $role) {
            $rawPermissions = $role->getPermissionsList();

            $permissionIds = collect($rawPermissions)
                ->pluck('id')
                ->filter()
                ->map(fn($id) => (string) $id)
                ->values()
                ->toArray();

            if (!empty($permissionIds)) {
                $userPermissions = PermissionsModel::whereIn('_id', $permissionIds)
                    ->where('status', 1)
                    ->pluck('module')
                    ->toArray();

                $allModules = ModulesModel::where('status', 1)
                    ->whereIn('route', $userPermissions)
                    ->orderBy('order_index', 'asc')
                    ->get()
                    // Solo módulos con ruta Laravel real, o dropdowns (relation = 0)
                    ->filter(function ($module) {
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
                    ->filter(fn($m) => in_array($m->relation, $dropdownRoutes))
                    ->groupBy('relation');

                // Menú top-level: los que NO son hijos de un dropdown
                $userMenu = $allModules
                    ->filter(fn($m) => !in_array($m->relation, $dropdownRoutes))
                    ->map(function ($module) use ($childrenMap) {
                        $data = $module->toArray();
                        $data['is_dropdown'] = $module->relation == 0;
                        $data['children']    = $childrenMap
                            ->get($module->route, collect())
                            ->values()
                            ->toArray();
                        return $data;
                    })
                    // Ocultar dropdowns vacíos (sin hijos visibles)
                    ->filter(fn($module) => !($module['is_dropdown'] ?? false) || !empty($module['children']))
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
                'user'  => $user,
                'role'  => $role?->role ?? null,
                'menu'  => $userMenu,
                'notification_unread_count' => $notificationUnreadCount,
                'can'   => $userPermissions,
            ],
            'ziggy' => fn() => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
