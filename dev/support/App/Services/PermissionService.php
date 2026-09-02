<?php

namespace App\Services;

use Giga\Core\Database;

/**
 * Stand-in di PermissionService per l'harness dev/ — NON parte del
 * pacchetto giga-cms (vive sotto App\Services, come nel progetto
 * consumer reale, per dimostrare fedelmente il contratto "enforcement
 * lato Controller", mai dentro un Service di giga-cms).
 *
 * can()/loadPermissions() replicano ESATTAMENTE la logica reale verificata
 * su wos-pro/cms-neviobianchi durante l'audit (stessa cache in sessione
 * con version-check su permissions_version, stesso bypass superadmin) —
 * non una versione semplificata. Omesso solo il blocco di costanti
 * PROJECTS_VIEW/USERS_EDIT/ecc.: sono nomi di permesso di sistema fissi a
 * compile-time, specifici di ogni progetto e irrilevanti per permessi di
 * Content Type dinamici (il cui module è uno slug deciso a runtime) — il
 * meccanismo can(string $permission) sotto non dipende da quelle costanti,
 * funziona con qualunque stringa "module.action".
 */
class PermissionService
{
    public static function can(string $permission): bool
    {
        if (($_SESSION['user_role_slug'] ?? '') === 'superadmin') {
            return true;
        }

        return in_array($permission, self::loadPermissions(), true);
    }

    public static function clearCache(): void
    {
        unset(
            $_SESSION['user_permissions'],
            $_SESSION['user_permissions_version']
        );
    }

    private static function loadPermissions(): array
    {
        $roleId = $_SESSION['user_role_id'] ?? null;

        if (!$roleId) {
            return [];
        }

        $db = Database::getInstance();

        $row            = $db->fetchOne(
            "SELECT permissions_version FROM sec_roles WHERE id = ? LIMIT 1",
            [$roleId]
        );
        $currentVersion = (int) ($row['permissions_version'] ?? 0);

        if (
            isset($_SESSION['user_permissions'], $_SESSION['user_permissions_version']) &&
            $_SESSION['user_permissions_version'] === $currentVersion
        ) {
            return $_SESSION['user_permissions'];
        }

        $rows = $db->fetchAll(
            "SELECT p.module, p.action
             FROM sec_permissions p
             JOIN sec_role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?",
            [$roleId]
        );

        $permissions = array_map(
            fn($r) => $r['module'] . '.' . $r['action'],
            $rows
        );

        $_SESSION['user_permissions']         = $permissions;
        $_SESSION['user_permissions_version'] = $currentVersion;

        return $permissions;
    }
}
