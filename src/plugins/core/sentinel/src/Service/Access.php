<?php

namespace ChurchCRM\Plugins\Sentinel\Service;

use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Portal\Auth\PortalMemberUser;

final class Access
{
    public static function allows(User $user, string $action): bool
    {
        if ($user instanceof PortalMemberUser) {
            return false;
        }
        return match ($action) {
            'read' => true,
            'contacts' => $user->isEditRecordsEnabled(),
            'partners' => $user->isEditRecordsEnabled() && $user->isNotesEnabled(),
            'partner-read' => $user->isNotesEnabled(),
            'events' => $user->canViewEvents(),
            'event-write', 'assignments' => $user->canManageEvents(),
            'publish', 'consent', 'settings' => $user->isAdmin(),
            'email', 'sync' => $user->isEmailEnabled(),
            default => false,
        };
    }
}
