<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Inertia\Inertia;
use App\Models\Permission;
use Illuminate\Support\Str;
use App\Http\Requests\RoleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleController extends BaseController
{
    use \App\Traits\LogsActivity;

    /**
     * Constructor to apply middleware
     */


    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $roleQuery = Role::query()->withPermissionCheck()->with(['permissions', 'creator']);

        // Handle search
        if ($request->filled('search')) {
            $search = $request->search;
            $roleQuery = $roleQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Handle sorting
        if ($request->filled('sort_field') && $request->filled('sort_direction')) {
            $roleQuery = $roleQuery->orderBy($request->sort_field, $request->sort_direction);
        } else {
            $roleQuery = $roleQuery->latest();
        }

        // Handle pagination
        $perPage = $request->input('per_page', 10);
        $roles = $roleQuery->paginate($perPage)->withQueryString();

        // Add is_editable attribute to each role
        $roles->getCollection()->transform(function ($role) {
            $role->is_editable = !in_array($role->name, isNotEditableRoles());
            return $role;
        });

        $permissions = $this->getFilteredPermissions();

        return Inertia::render('roles/index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'filters' => [
                'search' => $request->search ?? '',
                'per_page' => $perPage,
                'sort_field' => $request->sort_field ?? 'created_at',
                'sort_direction' => $request->sort_direction ?? 'desc',
            ],
        ]);
    }

    /**
     * Get permissions filtered by user role
     */
    private function getFilteredPermissions()
    {
        $user = Auth::user();
        $userType = $user->type ?? 'company';

        // Superadmin sees all permissions
        if (in_array($userType, ['superadmin', 'super admin'])) {
            return Permission::all()->groupBy('module');
        }

        // Get allowed modules for current user role
        $allowedModules = config('role-permissions.' . $userType, config('role-permissions.company'));

        // Filter permissions by allowed modules
        $query = Permission::whereIn('module', $allowedModules);

        $permissions = $query->get()->groupBy('module');

        return $permissions;
    }

    /**
     * Validate permissions against user's allowed modules and preserve existing unallowed ones
     */
    private function validatePermissions(array $permissionNames, ?Role $role = null)
    {
        $user = Auth::user();
        $userType = $user->type ?? 'company';

        // Superadmin can assign any permission
        if (in_array($userType, ['superadmin', 'super admin'])) {
            return $permissionNames;
        }

        // Get allowed modules for current user role
        $allowedModules = config('role-permissions.' . $userType, config('role-permissions.company'));

        // Build query to get valid permissions
        $query = Permission::whereIn('module', $allowedModules)
            ->whereIn('name', $permissionNames);

        $validPermissions = $query->pluck('name')->toArray();

        // If editing an existing role, preserve permissions from unallowed modules
        if ($role) {
            $existingUnallowed = $role->permissions()
                ->whereNotIn('module', $allowedModules)
                ->pluck('name')
                ->toArray();

            $validPermissions = array_unique(array_merge($validPermissions, $existingUnallowed));
        }

        return $validPermissions;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $permissions = $this->getFilteredPermissions();
        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label,
                'permissions' => $role->permissions->pluck('name')
            ];
        });

        return Inertia::render('roles/create', [
            'permissions' => $permissions,
            'roles' => $roles
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RoleRequest $request)
    {
        // Validate permissions against user's allowed modules
        $validatedPermissions = $this->validatePermissions($request->permissions ?? [], null);

        // Use direct model creation to bypass Spatie's duplicate check
        $role = new Role();
        $role->label = $request->label;
        $role->name = Str::slug($request->label);
        $role->description = $request->description;
        $role->created_by = Auth::id();
        $role->guard_name = 'web';
        $role->save();

        if ($role) {
            $role->syncPermissions($validatedPermissions);

            return redirect()->route('roles.index')->with('success', __('Role created successfully with Permissions!'));
        }
        return redirect()->back()->with('error', __('Unable to create Role with permissions. Please try again!'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        $role->is_editable = !in_array($role->name, isNotEditableRoles());
        if (!$role->is_editable) {
            return redirect()->route('roles.index')->with('error', __('System roles cannot be edited.'));
        }

        $permissions = $this->getFilteredPermissions();

        return Inertia::render('roles/edit', [
            'role' => $role,
            'permissions' => $permissions
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RoleRequest $request, Role $role)
    {
        \Log::info('Reached RoleController@update for role: ' . $role->name);
        if ($role) {
            // Validate permissions against user's allowed modules
            $validatedPermissions = $this->validatePermissions($request->permissions ?? [], $role);

            $newSlug = Str::slug($request->label);

            // Only update name if it's different to avoid duplicate key error
            if ($role->name !== $newSlug) {
                $role->name = $newSlug;
            }

            $role->label = $request->label;
            $role->description = $request->description;

            $role->save();

            # Update the permissions
            $role->syncPermissions($validatedPermissions);

            return redirect()->route('roles.index')->with('success', __('Role updated successfully with Permissions!'));
        }
        return redirect()->back()->with('error', __('Unable to update Role with permissions. Please try again!'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        if ($role) {
            // Prevent deletion of system roles
            // if ($role->is_system_role) {
            //     return redirect()->back()->with('error', __('System roles cannot be deleted!'));
            // }

            if (in_array($role->name, isNotDeletableRoles())) {
                return redirect()->back()->with('error', __('System roles cannot be deleted!'));
            }

            $roleName = $role->name;
            $role->delete();

            return redirect()->route('roles.index')->with('success', __('Role deleted successfully!'));
        }
        return redirect()->back()->with('error', __('Unable to delete Role. Please try again!'));
    }
}
