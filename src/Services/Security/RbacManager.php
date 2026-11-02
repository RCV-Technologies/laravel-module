<?php

namespace RCV\Core\Services\Security;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

class RbacManager
{
    /** @var array<string,array<string,bool>> role => [permission => true] */
    protected array $rolePermissions = [];

    /** @var array<string,array<string,bool>> guard + user ID => [role => true] */
    protected array $userRoles = [];

    /** @var array<string,array<string,bool>> guard + tenant ID => [role => true] */
    protected array $tenantRoles = [];

    /** @var array<string,array<string,bool>> guard + user ID + tenant ID => tenant membership */
    protected array $tenantMemberships = [];

    public function defineRole(string $role, array $permissions): void
    {
        $map = [];
        foreach ($permissions as $perm) {
            $map[$perm] = true;
        }
        $this->rolePermissions[$role] = $map;
    }

    public function assignRoleToUser(Authenticatable|string|int $user, string $role, ?string $guard = null): void
    {
        $userId = is_object($user) ? (string) $user->getAuthIdentifier() : (string) $user;
        $identity = $this->identityKey($guard, $userId);
        $this->userRoles[$identity][$role] = true;
    }

    public function assignRoleToTenant(string $tenantId, string $role, ?string $guard = null): void
    {
        $this->tenantRoles[$this->identityKey($guard, $tenantId)][$role] = true;
    }

    public function assignUserToTenant(
        Authenticatable|string|int $user,
        string $tenantId,
        ?string $guard = null
    ): void {
        $userId = is_object($user) ? (string) $user->getAuthIdentifier() : (string) $user;
        $this->tenantMemberships[$this->identityKey($guard, $userId)][$tenantId] = true;
    }

    public function userHasPermission(
        Authenticatable|string|int $user,
        string $permission,
        ?string $tenantId = null,
        ?string $guard = null
    ): bool {
        $userId = is_object($user) ? (string) $user->getAuthIdentifier() : (string) $user;
        $roles = array_keys($this->userRoles[$this->identityKey($guard, $userId)] ?? []);

        if ($tenantId !== null) {
            if (! $this->hasTenantMembership($guard, $userId, $tenantId)) {
                return false;
            }
            $tenantRoles = $this->tenantRoles[$this->identityKey($guard, $tenantId)] ?? [];
            $roles = array_unique(array_merge($roles, array_keys($tenantRoles)));
        }

        foreach ($roles as $role) {
            if (! empty($this->rolePermissions[$role][$permission])) {
                return true;
            }
        }

        return false;
    }

    public function reset(): void
    {
        $this->rolePermissions = [];
        $this->userRoles = [];
        $this->tenantRoles = [];
        $this->tenantMemberships = [];
    }

    protected function identityKey(?string $guard, string $identifier): string
    {
        $guardName = $guard ?? Auth::getDefaultDriver();

        return $guardName.':'.$identifier;
    }

    protected function hasTenantMembership(?string $guard, string $userId, string $tenantId): bool
    {
        return ! empty($this->tenantMemberships[$this->identityKey($guard, $userId)][$tenantId]);
    }
}
