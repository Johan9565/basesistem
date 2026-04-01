<?php

namespace App\Http\Controllers;

use App\Events\NotificacionToUser;
use App\Models\DependenciesModel;
use App\Models\RoleModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use MongoDB\BSON\ObjectId;
use App\Models\LogsModel;
use App\Models\NotificationsModel;
use App\Support\NotificationLinkResolver;
class users extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'role_id' => 'nullable|string',
            'status' => 'nullable|in:0,1',
            'area_id' => 'nullable|string',
        ]);

        $query = User::query();

        $search = isset($filters['search']) ? trim($filters['search']) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('ape_pat', 'like', '%'.$search.'%')
                    ->orWhere('ape_mat', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['role_id'])) {
            try {
                $query->where('role_id', new ObjectId($filters['role_id']));
            } catch (\Throwable $e) {
                // id inválido: no aplicar filtro de rol
            }
        }

        if (array_key_exists('status', $filters) && $filters['status'] !== null && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }
        if (! empty($filters['area_id'])) {
            try {
                $query->where('area_id', new ObjectId($filters['area_id']));
            } catch (\Throwable $e) {
                // id inválido: no aplicar filtro de area
            }
        }

        $users = $query->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(function ($user) {
                $role = $user->role_data()->first();
                $areaId = $user->area_id ?? null;

                return [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'ape_pat' => $user->ape_pat ?? '',
                    'ape_mat' => $user->ape_mat ?? '',
                    'email' => $user->email,
                    'role' => $role ? $role->role : '—',
                    'role_id' => $role ? (string) $role->getKey() : '',
                    'status' => $user->status ?? 1,
                    'area_id' => $areaId ? (string) $areaId : '',
                ];
            });

        $roles = RoleModel::where('status', 1)
            ->get(['id', 'name', 'role'])
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'name' => $r->role,
            ]);
        $areas = DependenciesModel::where('status', 1)
            ->get(['id', 'name'])
            ->map(fn ($a) => [
                'id' => (string) $a->id,
                'name' => $a->name,
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'areas' => $areas,
            'filters' => [
                'search' => $search,
                'role_id' => $filters['role_id'] ?? '',
                'status' => array_key_exists('status', $filters) && $filters['status'] !== null && $filters['status'] !== ''
                    ? (string) $filters['status']
                    : '',
                'area_id' => $filters['area_id'] ?? '',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'ape_pat' => 'required|string|max:255',
            'ape_mat' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role_id' => 'required|string',
            'area_id' => 'required|string',
            'status' => 'required|in:0,1',
        ]);

        $role = RoleModel::findOrFail($request->role_id);

        User::create([
            'name' => $request->name,
            'ape_pat' => $request->ape_pat,
            'ape_mat' => $request->ape_mat,
            'email' => $request->email,
            'area_id' => new ObjectId($request->area_id),
            'password' => Hash::make($request->password),
            'role_id' => new ObjectId($role->getKey()),
            'status' => (int) $request->status,
            'active' => false,
        ]);

        return redirect()->route('users');
    }

    public function update(Request $request, $userId)
    {
        $user = User::findOrFail(new ObjectId($userId));

        $request->validate([
            'name' => 'required|string|max:255',
            'ape_pat' => 'required|string|max:255',
            'ape_mat' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($userId),
            ],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'role_id' => 'required|string',
            'status' => 'required|in:0,1',
            'area_id' => 'required|string',
        ]);

        $role = RoleModel::findOrFail($request->role_id);

        $data = [
            'name' => $request->name,
            'ape_pat' => $request->ape_pat,
            'ape_mat' => $request->ape_mat,
            'email' => $request->email,
            'area_id' => new ObjectId($request->area_id),
            'role_id' => new ObjectId($role->getKey()),
            'status' => (int) $request->status,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $before = [
            'name' => (string) ($user->name ?? ''),
            'ape_pat' => (string) ($user->ape_pat ?? ''),
            'ape_mat' => (string) ($user->ape_mat ?? ''),
            'email' => (string) ($user->email ?? ''),
            'role_id' => $user->role_id ? (string) $user->role_id : '',
            'area_id' => $user->area_id ? (string) $user->area_id : '',
            'status' => (int) ($user->status ?? 1),
        ];

        $user->update($data);
        $user->refresh();

        $after = [
            'name' => (string) ($user->name ?? ''),
            'ape_pat' => (string) ($user->ape_pat ?? ''),
            'ape_mat' => (string) ($user->ape_mat ?? ''),
            'email' => (string) ($user->email ?? ''),
            'role_id' => $user->role_id ? (string) $user->role_id : '',
            'area_id' => $user->area_id ? (string) $user->area_id : '',
            'status' => (int) ($user->status ?? 1),
        ];

        $cambios = [];
        foreach (['name', 'ape_pat', 'ape_mat', 'email', 'role_id', 'area_id', 'status'] as $key) {
            if ($before[$key] !== $after[$key]) {
                $cambios[$key] = [
                    'antes' => $before[$key],
                    'después' => $after[$key],
                ];
            }
        }
        if ($request->filled('password')) {
            $cambios['password'] = true;
        }

        $fieldLabels = [
            'name' => 'Nombre',
            'ape_pat' => 'Apellido paterno',
            'ape_mat' => 'Apellido materno',
            'email' => 'Correo',
            'role_id' => 'Rol',
            'area_id' => 'Área',
            'status' => 'Estado',
        ];

        $formatStatus = static function ($v): string {
            return (int) $v === 1 ? 'Activo' : 'Inactivo';
        };

        $roleDisplay = static function (?string $id): string {
            if ($id === null || $id === '') {
                return '—';
            }
            try {
                $m = RoleModel::find(new ObjectId($id));

                return $m ? (string) $m->role : $id;
            } catch (\Throwable $e) {
                return $id;
            }
        };

        $areaDisplay = static function (?string $id): string {
            if ($id === null || $id === '') {
                return '—';
            }
            try {
                $m = DependenciesModel::find(new ObjectId($id));

                return $m ? (string) $m->name : $id;
            } catch (\Throwable $e) {
                return $id;
            }
        };

        $partesCambios = [];
        foreach ($cambios as $key => $val) {
            if ($key === 'password') {
                $partesCambios[] = 'Contraseña actualizada';
                continue;
            }
            if (! is_array($val) || ! array_key_exists('antes', $val) || ! array_key_exists('después', $val)) {
                continue;
            }
            $label = $fieldLabels[$key] ?? $key;
            $antes = $val['antes'];
            $después = $val['después'];
            if ($key === 'status') {
                $antes = $formatStatus($antes);
                $después = $formatStatus($después);
            } elseif ($key === 'role_id') {
                $antes = $roleDisplay((string) $antes);
                $después = $roleDisplay((string) $después);
            } elseif ($key === 'area_id') {
                $antes = $areaDisplay((string) $antes);
                $después = $areaDisplay((string) $después);
            } else {
                $antes = (string) $antes;
                $después = (string) $después;
            }
            // Sin comillas dobles: en JSON las escapan como \" y en algunas vistas se ven mal.
            $partesCambios[] = $label.': '.$antes.' → '.$después;
        }

        $cambiosText = empty($partesCambios)
            ? 'Sin cambios en los datos del perfil.'
            : implode('. ', $partesCambios).'.';

        $highlightDisplayKeys = [];
        if ($before['name'] !== (string) ($user->name ?? '')) {
            $highlightDisplayKeys[] = 'name';
        }
        if ($before['ape_pat'] !== (string) ($user->ape_pat ?? '')) {
            $highlightDisplayKeys[] = 'ape_pat';
        }
        if ($before['ape_mat'] !== (string) ($user->ape_mat ?? '')) {
            $highlightDisplayKeys[] = 'ape_mat';
        }
        if ($before['email'] !== (string) ($user->email ?? '')) {
            $highlightDisplayKeys[] = 'email';
        }
        $newRoleId = $user->role_id ? (string) $user->role_id : '';
        if ($before['role_id'] !== $newRoleId) {
            $highlightDisplayKeys[] = 'role';
        }
        $newAreaId = $user->area_id ? (string) $user->area_id : '';
        if ($before['area_id'] !== $newAreaId) {
            $highlightDisplayKeys[] = 'area';
        }
        if ($before['status'] !== (int) ($user->status ?? 1)) {
            $highlightDisplayKeys[] = 'status';
        }

        $recipientId = (string) $user->getKey();
        $actorId = (string) $request->user()->getKey();
        $actorNameCompletaName= (string) $request->user()->name.' '. (string) $request->user()->ape_pat.' '. (string) $request->user()->ape_mat;
        $notificationItemIds = array_values(array_filter([
            (string) $user->getKey(),
            $actorId !== (string) $user->getKey() ? $actorId : null,
        ]));
        $notificationLinkDefs = [
            ['label' => 'Ver perfil', 'route' => 'profile.edit', 'params' => []],
        ];

        NotificationsModel::create([
            'user_id' => $recipientId,
            'message' => $actorId === $recipientId
                ? 'Tu cuenta ha sido actualizada correctamente.'
                : 'Un administrador actualizó tu cuenta. Puedes revisar tu perfil.',
            'item_ids' => $notificationItemIds,
            'is_read' => false,
            'read_at' => null,
            'links' => NotificationLinkResolver::resolve($notificationLinkDefs),
        ]);
        LogsModel::create([
            'user_id' => $recipientId,
            'action' => 'Actualización de usuario',
            'description' => 'El usuario '.$user->name.' ha sido actualizado por '.$actorNameCompletaName.'. '.$cambiosText,
        ]);
        NotificacionToUser::dispatch(
            message: $actorId === $recipientId
                ? 'Tu cuenta ha sido actualizada correctamente.'
                : 'Un administrador actualizó tu cuenta. Puedes revisar tu perfil.',
            userId: $recipientId,
            itemIds: $notificationItemIds,
            links: $notificationLinkDefs,
            currentPaths: ['/profile'],
            meta: [
                'navigate' => false,
                'inertiaGlobal' => [
                    'only' => ['auth'],
                    'preserveScroll' => true,
                ],
                'inertia' => [
                    'only' => ['profileDisplay', 'mustVerifyEmail', 'status'],
                    'preserveScroll' => true,
                ],
                'highlightDisplayKeys' => $highlightDisplayKeys,
            ],
        );

        return redirect()->route('users');
    }

    public function destroy($userId)
    {
        $user = User::findOrFail(new ObjectId($userId));
        $user->delete();

        return redirect()->route('users');
    }
}
