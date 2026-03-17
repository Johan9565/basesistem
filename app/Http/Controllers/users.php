<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\RoleModel;
use Inertia\Inertia;

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
                'role'  => $role?->role ?? '—',
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
}
