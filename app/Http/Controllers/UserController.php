<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'search'    => ['nullable', 'string', 'max:100'],
            'role'      => ['nullable', 'in:admin,operator'],
            'is_active' => ['nullable', 'boolean'],
            'per_page'  => ['nullable', 'integer', 'between:1,100'],
        ]);

        $users = User::query()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->role, fn ($query, $role) => $query->where('role', $role))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $user = User::create(array_merge(['is_active' => true], $request->validated()))->fresh();

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        $actor = $request->user('api');
        $data  = $request->validated();

        // ninguém se tranca fora do sistema nem contorna a "senha atual"
        if ($actor->is($user)) {
            if (isset($data['role']) && $data['role'] !== $user->role) {
                abort(409, 'Não pode alterar o seu próprio perfil.');
            }
            if (array_key_exists('is_active', $data) && ! $data['is_active']) {
                abort(409, 'Não pode desativar a sua própria conta.');
            }
            if (isset($data['password'])) {
                abort(409, 'Altere a sua própria senha em Configurações.');
            }
        }

        $user->update($data);

        return new UserResource($user->fresh());
    }
}
