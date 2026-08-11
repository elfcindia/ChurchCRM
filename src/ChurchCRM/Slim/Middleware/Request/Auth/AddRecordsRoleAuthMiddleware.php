<?php

declare(strict_types=1);

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class AddRecordsRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isAddRecordsEnabled();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must have Add Records permission');
    }

    protected function getRoleName(): string
    {
        return 'AddRecords';
    }
}
