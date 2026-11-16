<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Enum\Role;
use App\Security\Voter\ImpersonationVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * USER_IMPERSONATE (SPEC 8s): pokretač je admin+, meta je STROGO niža po role_hierarchy, ne sebe, ne neaktivan, bez ugnježdavanja.
 */
class ImpersonationVoterTest extends TestCase
{
    private const HIERARCHY = [
        'ROLE_SEF' => ['ROLE_RADNIK'],
        'ROLE_ADMIN' => ['ROLE_SEF'],
        'ROLE_SUPERADMIN' => ['ROLE_ADMIN'],
    ];

    private function user(int $id, string|Role $role, bool $active = true): User
    {
        $u = (new User())->setFirstName('U'.$id)->setLastName('T')->setEmail("u$id@t.local")->setPassword('x')->setIsActive($active);
        // role se postavlja direktno, da bi se probala i rola koje nema u enumu (nova rola u hijerarhiji)
        $ref = new \ReflectionProperty(User::class, 'roles');
        $ref->setValue($u, [$role instanceof Role ? $role->value : $role]);
        (new \ReflectionProperty(User::class, 'id'))->setValue($u, $id);

        return $u;
    }

    private function vote(User $actor, User $target, array $hierarchy = self::HIERARCHY, bool $nested = false): int
    {
        $voter = new ImpersonationVoter(new RoleHierarchy($hierarchy));
        $token = new UsernamePasswordToken($actor, 'main', $actor->getRoles());
        if ($nested) {
            $token = new SwitchUserToken($target, 'main', $target->getRoles(), $token);
        }

        return $voter->vote($token, $target, [ImpersonationVoter::IMPERSONATE]);
    }

    /** @return iterable<string, array{Role, Role, bool}> */
    public static function matrix(): iterable
    {
        yield 'superadmin -> admin' => [Role::SUPERADMIN, Role::ADMIN, true];
        yield 'superadmin -> šef' => [Role::SUPERADMIN, Role::SEF, true];
        yield 'superadmin -> radnik' => [Role::SUPERADMIN, Role::RADNIK, true];
        yield 'admin -> šef' => [Role::ADMIN, Role::SEF, true];
        yield 'admin -> radnik' => [Role::ADMIN, Role::RADNIK, true];
        yield 'admin -> admin' => [Role::ADMIN, Role::ADMIN, false];
        yield 'admin -> superadmin' => [Role::ADMIN, Role::SUPERADMIN, false];
        yield 'superadmin -> superadmin' => [Role::SUPERADMIN, Role::SUPERADMIN, false];
        yield 'šef -> radnik' => [Role::SEF, Role::RADNIK, false];
        yield 'šef -> šef' => [Role::SEF, Role::SEF, false];
        yield 'šef -> admin' => [Role::SEF, Role::ADMIN, false];
        yield 'radnik -> radnik' => [Role::RADNIK, Role::RADNIK, false];
        yield 'radnik -> šef' => [Role::RADNIK, Role::SEF, false];
    }

    #[DataProvider('matrix')]
    public function testRoleMatrix(Role $actorRole, Role $targetRole, bool $allowed): void
    {
        $result = $this->vote($this->user(1, $actorRole), $this->user(2, $targetRole));
        self::assertSame($allowed ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED, $result);
    }

    public function testNeverOnSelf(): void
    {
        $admin = $this->user(1, Role::SUPERADMIN);
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($admin, $admin));
        // isti nalog učitan dvaput (drugi objekat, isti id)
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($admin, $this->user(1, Role::RADNIK)));
    }

    public function testNeverOnInactiveUser(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->user(1, Role::ADMIN), $this->user(2, Role::RADNIK, false)));
    }

    public function testNoNestedImpersonation(): void
    {
        // admin već gleda kao radnik: ni na koga drugog, čak ni na šefa
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->user(1, Role::ADMIN), $this->user(2, Role::RADNIK), nested: true));
    }

    public function testNewRoleInHierarchyFollowsTheSameRule(): void
    {
        // nova rola VODJA između šefa i admina, nova rola STAZIST ispod radnika: pravilo se ne menja, a kod ne nabraja role
        $hierarchy = [
            'ROLE_STAZIST' => [],
            'ROLE_RADNIK' => ['ROLE_STAZIST'],
            'ROLE_SEF' => ['ROLE_RADNIK'],
            'ROLE_VODJA' => ['ROLE_SEF'],
            'ROLE_ADMIN' => ['ROLE_VODJA'],
            'ROLE_SUPERADMIN' => ['ROLE_ADMIN'],
        ];
        $admin = $this->user(1, Role::ADMIN);
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, $this->user(2, 'ROLE_VODJA'), $hierarchy));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, $this->user(3, 'ROLE_STAZIST'), $hierarchy));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->user(4, 'ROLE_VODJA'), $this->user(5, Role::RADNIK), $hierarchy), 'vođa nije admin, pa ne sme ni na radnika');
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($admin, $this->user(6, Role::ADMIN), $hierarchy));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->user(7, Role::SUPERADMIN), $this->user(8, Role::ADMIN), $hierarchy));
    }
}
