<?php

namespace App\Support\Permissions;

final class PermissionKeys
{
    public const SECTIONS = ['dashboard', 'live_map', 'tracker_users', 'admins', 'settings'];

    public const ACTIONS = ['view', 'create', 'edit', 'delete'];

    /** @return list<array{key:string,section:string,action:string,label:string}> */
    public static function all(): array
    {
        $keys = [];
        foreach (self::SECTIONS as $section) {
            foreach (self::ACTIONS as $action) {
                $keys[] = [
                    'key' => "{$section}.{$action}",
                    'section' => $section,
                    'action' => $action,
                    'label' => ucfirst($action).' '.str_replace('_', ' ', $section),
                ];
            }
        }

        return $keys;
    }
}
