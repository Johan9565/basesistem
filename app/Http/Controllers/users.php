<?php

namespace App\Http\Controllers;

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
    public function index()
    {
        $users = User::orderBy('name')->get()->map(function ($user) {
            $role = $user->role_data()->first();
            return [
                'id'    => (string) $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $role ? $role->role : '—',
                'role_id' => $role ? (string) $role->getKey() : '',
                'status'=> $user->status ?? 1,
            ];
        });

        $roles = RoleModel::where('status', 1)
            ->get(['id', 'name', 'role'])
            ->map(fn($r) => [
                'id'   => (string) $r->id,
                'name' => $r->role,
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role_id' => 'required|string',
            'status' => 'required|in:0,1',
        ]);

        $role = RoleModel::findOrFail($request->role_id);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
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
        ]);

        $role = RoleModel::findOrFail($request->role_id);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
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
