<?php

namespace App\Enums;

enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Billing = 'billing';

    /**
     * Every permission known to the application.
     *
     * @return list<string>
     */
    public static function allPermissions(): array
    {
        return [
            'team.view',
            'team.update',
            'team.delete',
            'team.members.manage',
            'team.billing.manage',
            'projects.view',
            'projects.create',
            'projects.update',
            'projects.delete',
        ];
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => self::allPermissions(),
            self::Admin => [
                'team.view',
                'team.update',
                'team.members.manage',
                'projects.view',
                'projects.create',
                'projects.update',
                'projects.delete',
            ],
            self::Member => [
                'team.view',
                'projects.view',
                'projects.create',
                'projects.update',
            ],
            self::Billing => [
                'team.view',
                'team.billing.manage',
                'projects.view',
            ],
        };
    }
}
