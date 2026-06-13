<?php

namespace App\Security\Voter;

use App\Entity\SharedVault;
use App\Entity\User;
use App\Entity\Vault;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class VaultVoter extends Voter
{
    public const VIEW   = 'VIEW';
    public const EDIT   = 'EDIT';
    public const DELETE = 'DELETE';
    public const SHARE  = 'SHARE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::SHARE], true)
            && $subject instanceof Vault;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Vault $vault */
        $vault = $subject;

        return match ($attribute) {
            self::VIEW   => $this->canView($vault, $user),
            self::EDIT   => $this->canEdit($vault, $user),
            self::DELETE => $this->isOwner($vault, $user),
            self::SHARE  => $this->canShare($vault, $user),
            default      => false,
        };
    }

    private function isOwner(Vault $vault, User $user): bool
    {
        return $vault->getUser() === $user;
    }

    private function getSharedAccess(Vault $vault, User $user): ?SharedVault
    {
        foreach ($vault->getSharedVaults() as $sv) {
            /** @var SharedVault $sv */
            if ($sv->getRecipient() === $user && $sv->isAccepted()) {
                return $sv;
            }
        }
        return null;
    }

    private function canView(Vault $vault, User $user): bool
    {
        return $this->isOwner($vault, $user) || $this->getSharedAccess($vault, $user) !== null;
    }

    private function canEdit(Vault $vault, User $user): bool
    {
        if ($this->isOwner($vault, $user)) {
            return true;
        }
        $shared = $this->getSharedAccess($vault, $user);
        return $shared !== null && $shared->getPermission()->canWrite();
    }

    private function canShare(Vault $vault, User $user): bool
    {
        if ($this->isOwner($vault, $user)) {
            return true;
        }
        $shared = $this->getSharedAccess($vault, $user);
        return $shared !== null && $shared->getPermission()->canAdmin();
    }
}
