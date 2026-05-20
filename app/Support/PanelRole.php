<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Phân loại role panel admin vs người dùng ngoài (customer/shipper).
 */
final class PanelRole
{
    public const INTERNAL = ['admin', 'staff', 'sale_staff', 'account_manager', 'manager'];

    public const EXTERNAL = ['customer', 'shipper'];

    public static function isInternal(string $roleName): bool
    {
        return in_array(strtolower($roleName), self::INTERNAL, true);
    }

    public static function isExternal(string $roleName): bool
    {
        return in_array(strtolower($roleName), self::EXTERNAL, true);
    }

    /** Role dùng cho panel admin (không phải customer/shipper). */
    public static function isPanelRole(string $roleName): bool
    {
        return !self::isExternal($roleName);
    }

    public static function externalPlaceholders(): string
    {
        return implode(',', array_fill(0, count(self::EXTERNAL), '?'));
    }

    /** @param string[] $roles */
    public static function hasInternalRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if (self::isInternal((string) $role)) {
                return true;
            }
        }
        return false;
    }

    /** Có ít nhất một role được vào panel (không phải customer/shipper). */
    public static function hasAnyPanelRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if (self::isPanelRole((string) $role)) {
                return true;
            }
        }
        return false;
    }

    /** @param string[] $roles */
    public static function hasOnlyExternalRoles(array $roles): bool
    {
        if ($roles === []) {
            return false;
        }
        foreach ($roles as $role) {
            if (!self::isExternal((string) $role)) {
                return false;
            }
        }
        return true;
    }

    public static function internalPlaceholders(): string
    {
        return implode(',', array_fill(0, count(self::INTERNAL), '?'));
    }
}
