<?php

namespace App\Domain\Shared\Enums;

enum TenantRole: string
{
    case TenantAdmin = 'tenant_admin';
    case TenantMember = 'tenant_member';
    case ReadOnlyViewer = 'read_only_viewer';

    public function canManageEnvironments(): bool
    {
        return $this === self::TenantAdmin;
    }

    public function canManageTenant(): bool
    {
        return $this === self::TenantAdmin;
    }
}
