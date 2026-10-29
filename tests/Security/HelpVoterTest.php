<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Enum\HelpGuide;
use App\Security\Voter\HelpVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * Uputstvo (SPEC 8o): svaka rola vidi uputstvo svoje role i nižih; superadmin sva.
 */
class HelpVoterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<HelpGuide>}>
     */
    public static function roles(): iterable
    {
        yield 'radnik' => ['ROLE_RADNIK', [HelpGuide::RADNIK]];
        yield 'šef' => ['ROLE_SEF', [HelpGuide::SEF, HelpGuide::RADNIK]];
        yield 'admin' => ['ROLE_ADMIN', [HelpGuide::ADMIN, HelpGuide::SEF, HelpGuide::RADNIK]];
        yield 'superadmin' => ['ROLE_SUPERADMIN', [HelpGuide::ADMIN, HelpGuide::SEF, HelpGuide::RADNIK]];
    }

    /**
     * @param list<HelpGuide> $expected
     */
    #[DataProvider('roles')]
    public function testGuidesPerRole(string $role, array $expected): void
    {
        $voter = new HelpVoter(new RoleHierarchy([
            'ROLE_SEF' => ['ROLE_RADNIK'],
            'ROLE_ADMIN' => ['ROLE_SEF'],
            'ROLE_SUPERADMIN' => ['ROLE_ADMIN'],
        ]));
        $token = new UsernamePasswordToken(new User(), 'main', [$role]);

        $granted = array_values(array_filter(HelpGuide::cases(), static fn (HelpGuide $g) => VoterInterface::ACCESS_GRANTED === $voter->vote($token, $g, [HelpVoter::VIEW])));

        self::assertSame($expected, $granted);
    }
}
