<?php

namespace App\Tests\Security;

use App\Entity\SharedVault;
use App\Entity\User;
use App\Entity\Vault;
use App\Entity\VaultPermission;
use App\Security\Voter\VaultVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class VaultVoterTest extends TestCase
{
    private VaultVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new VaultVoter();
    }

    private function makeUser(): User
    {
        return new User();
    }

    private function makeVault(User $owner): Vault
    {
        $vault = new Vault();
        $vault->setName('test')->setUser($owner);
        return $vault;
    }

    private function makeToken(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    private function makeSharedVault(Vault $vault, User $recipient, string $permCode, bool $accepted = true): SharedVault
    {
        $permission = (new VaultPermission())->setCode($permCode)->setName($permCode);

        $sv = new SharedVault();
        $sv->setVault($vault)->setRecipient($recipient)->setSender($vault->getUser())->setPermission($permission);
        if ($accepted) {
            $sv->accept();
        }
        $vault->getSharedVaults()->add($sv);

        return $sv;
    }

    // --- VIEW ---

    public function testOwnerCanView(): void
    {
        $owner = $this->makeUser();
        $vault = $this->makeVault($owner);

        $this->assertSame(
            VaultVoter::ACCESS_GRANTED,
            $this->voter->vote($this->makeToken($owner), $vault, [VaultVoter::VIEW])
        );
    }

    public function testStrangerCannotView(): void
    {
        $owner   = $this->makeUser();
        $vault   = $this->makeVault($owner);
        $other   = $this->makeUser();

        $this->assertSame(
            VaultVoter::ACCESS_DENIED,
            $this->voter->vote($this->makeToken($other), $vault, [VaultVoter::VIEW])
        );
    }

    public function testSharedReadRecipientCanView(): void
    {
        $owner     = $this->makeUser();
        $vault     = $this->makeVault($owner);
        $recipient = $this->makeUser();
        $this->makeSharedVault($vault, $recipient, VaultPermission::READ);

        $this->assertSame(
            VaultVoter::ACCESS_GRANTED,
            $this->voter->vote($this->makeToken($recipient), $vault, [VaultVoter::VIEW])
        );
    }

    public function testPendingShareCannotView(): void
    {
        $owner     = $this->makeUser();
        $vault     = $this->makeVault($owner);
        $recipient = $this->makeUser();
        $this->makeSharedVault($vault, $recipient, VaultPermission::READ, accepted: false);

        $this->assertSame(
            VaultVoter::ACCESS_DENIED,
            $this->voter->vote($this->makeToken($recipient), $vault, [VaultVoter::VIEW])
        );
    }

    // --- EDIT ---

    public function testOwnerCanEdit(): void
    {
        $owner = $this->makeUser();
        $vault = $this->makeVault($owner);

        $this->assertSame(
            VaultVoter::ACCESS_GRANTED,
            $this->voter->vote($this->makeToken($owner), $vault, [VaultVoter::EDIT])
        );
    }

    public function testWritePermissionCanEdit(): void
    {
        $owner     = $this->makeUser();
        $vault     = $this->makeVault($owner);
        $recipient = $this->makeUser();
        $this->makeSharedVault($vault, $recipient, VaultPermission::WRITE);

        $this->assertSame(
            VaultVoter::ACCESS_GRANTED,
            $this->voter->vote($this->makeToken($recipient), $vault, [VaultVoter::EDIT])
        );
    }

    public function testReadOnlyPermissionCannotEdit(): void
    {
        $owner     = $this->makeUser();
        $vault     = $this->makeVault($owner);
        $recipient = $this->makeUser();
        $this->makeSharedVault($vault, $recipient, VaultPermission::READ);

        $this->assertSame(
            VaultVoter::ACCESS_DENIED,
            $this->voter->vote($this->makeToken($recipient), $vault, [VaultVoter::EDIT])
        );
    }

    public function testAdminPermissionCanEdit(): void
    {
        $owner     = $this->makeUser();
        $vault     = $this->makeVault($owner);
        $recipient = $this->makeUser();
        $this->makeSharedVault($vault, $recipient, VaultPermission::ADMIN);

        $this->assertSame(
            VaultVoter::ACCESS_GRANTED,
            $this->voter->vote($this->makeToken($recipient), $vault, [VaultVoter::EDIT])
        );
    }

    // --- DELETE ---

    public function testOnlyOwnerCanDelete(): void
    {
        $owner     = $this->makeUser();
        $vault     = $this->makeVault($owner);
        $recipient = $this->makeUser();
        $this->makeSharedVault($vault, $recipient, VaultPermission::ADMIN);

        $this->assertSame(
            VaultVoter::ACCESS_GRANTED,
            $this->voter->vote($this->makeToken($owner), $vault, [VaultVoter::DELETE])
        );

        $this->assertSame(
            VaultVoter::ACCESS_DENIED,
            $this->voter->vote($this->makeToken($recipient), $vault, [VaultVoter::DELETE])
        );
    }

    // --- SHARE ---

    public function testOwnerCanShare(): void
    {
        $owner = $this->makeUser();
        $vault = $this->makeVault($owner);

        $this->assertSame(
            VaultVoter::ACCESS_GRANTED,
            $this->voter->vote($this->makeToken($owner), $vault, [VaultVoter::SHARE])
        );
    }

    public function testAdminPermissionCanShare(): void
    {
        $owner     = $this->makeUser();
        $vault     = $this->makeVault($owner);
        $recipient = $this->makeUser();
        $this->makeSharedVault($vault, $recipient, VaultPermission::ADMIN);

        $this->assertSame(
            VaultVoter::ACCESS_GRANTED,
            $this->voter->vote($this->makeToken($recipient), $vault, [VaultVoter::SHARE])
        );
    }

    public function testWritePermissionCannotShare(): void
    {
        $owner     = $this->makeUser();
        $vault     = $this->makeVault($owner);
        $recipient = $this->makeUser();
        $this->makeSharedVault($vault, $recipient, VaultPermission::WRITE);

        $this->assertSame(
            VaultVoter::ACCESS_DENIED,
            $this->voter->vote($this->makeToken($recipient), $vault, [VaultVoter::SHARE])
        );
    }

    // --- supports() ---

    public function testDoesNotSupportNonVaultSubject(): void
    {
        $user  = $this->makeUser();
        $token = $this->makeToken($user);

        $this->assertSame(
            VaultVoter::ACCESS_ABSTAIN,
            $this->voter->vote($token, new \stdClass(), [VaultVoter::VIEW])
        );
    }

    public function testDoesNotSupportUnknownAttribute(): void
    {
        $owner = $this->makeUser();
        $vault = $this->makeVault($owner);

        $this->assertSame(
            VaultVoter::ACCESS_ABSTAIN,
            $this->voter->vote($this->makeToken($owner), $vault, ['UNKNOWN_ATTR'])
        );
    }
}
