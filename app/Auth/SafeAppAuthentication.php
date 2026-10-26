<?php

namespace App\Auth;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use LogicException;

class SafeAppAuthentication extends AppAuthentication
{
    public function verifyRecoveryCode(string $recoveryCode, ?HasAppAuthenticationRecovery $user = null): bool
    {
        try {
            return parent::verifyRecoveryCode($recoveryCode, $user);
        } catch (LogicException $exception) {
            if ($exception->getMessage() === 'The user does not have any app authentication recovery codes.') {
                return false;
            }

            throw $exception;
        }
    }
}
