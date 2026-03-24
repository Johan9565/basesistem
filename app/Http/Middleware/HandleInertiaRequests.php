<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;
use App\Models\ModulesModel;
use App\Models\PermissionsModel;
use App\Models\ComponentThemeModel;
use App\Models\NotificationsModel;

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
                    ->get();

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
                    ->values();
            }
        }

        $themeDoc = ComponentThemeModel::first();

        return [
            ...parent::share($request),
            'csrf_token' => csrf_token(),
            'flash' => $request->session()->get('flash'),
            'activeTheme' => $themeDoc?->active_theme ?? 'dark',
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
