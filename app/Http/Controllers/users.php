<?php

namespace App\Http\Controllers;

use App\Models\DependenciesModel;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\RoleModel;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use MongoDB\BSON\ObjectId;

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
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('ape_pat', 'like', '%' . $search . '%')
                    ->orWhere('ape_mat', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['role_id'])) {
            try {
                $query->where('role_id', new ObjectId($filters['role_id']));
            } catch (\Throwable $e) {
                // id inválido: no aplicar filtro de rol
            }
        }

        if (array_key_exists('status', $filters) && $filters['status'] !== null && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }
        if (!empty($filters['area_id'])) {
            try {
                $query->where('area_id', new ObjectId($filters['area_id']));
            } catch (\Throwable $e) {
                // id inválido: no aplicar filtro de area
            }
        }

        $users = $query->orderBy('name')->get()->map(function ($user) {
            $role = $user->role_data()->first();
            $areaId = $user->area_id ?? null;
            return [
                'id'    => (string) $user->id,
                'name'  => $user->name,
                'ape_pat' => $user->ape_pat ?? '',
                'ape_mat' => $user->ape_mat ?? '',
                'email' => $user->email,
                'role'  => $role ? $role->role : '—',
                'role_id' => $role ? (string) $role->getKey() : '',
                'status'=> $user->status ?? 1,
                'area_id' => $areaId ? (string) $areaId : '',
            ];
        });

        $roles = RoleModel::where('status', 1)
            ->get(['id', 'name', 'role'])
            ->map(fn($r) => [
                'id'   => (string) $r->id,
                'name' => $r->role,
            ]);
        $areas = DependenciesModel::where('status', 1)
            ->get(['id', 'name'])
            ->map(fn($a) => [
                'id'   => (string) $a->id,
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
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
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

        $user->update($data);

        return redirect()->route('users');
    }

    public function destroy($userId)
    {
        $user = User::findOrFail(new ObjectId($userId));
        $user->delete();

        return redirect()->route('users');
    }
}
