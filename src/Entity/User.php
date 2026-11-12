<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\Role;
use App\Enum\ThemePreference;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Korisnik sa ovom email adresom već postoji.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Unesi ime.')]
    #[Assert\Length(max: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Unesi prezime.')]
    #[Assert\Length(max: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Unesi email.')]
    #[Assert\Email(message: 'Email adresa nije ispravna.')]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $address = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $city = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $postalCode = null;

    /** Pozicija (nivo) zaposlenog. NE daje prava pristupa: njih određuju samo ROLE_* */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Position $position = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $employedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $internalNote = null;

    /** Tajni token za ICS pretplatu na kalendar (SPEC 8n); null = nema linka */
    #[ORM\Column(length: 40, nullable: true, unique: true)]
    private ?string $icsToken = null;

    /** Broj dana godišnjeg odmora po godini (prazno = podrazumevano iz podešavanja, SPEC 8j.3) */
    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 60)]
    private ?int $annualLeaveDays = null;

    /** Izbor teme prikaza (DESIGN.md sekcija 6) */
    #[ORM\Column(length: 10, enumType: ThemePreference::class, options: ['default' => 'SYSTEM'])]
    private ThemePreference $themePreference = ThemePreference::SYSTEM;

    /** Mesečni kapacitet u satima (prazno = opšte podešavanje), SPEC 8d.3 */
    #[ORM\Column(nullable: true)]
    private ?int $capacityHours = null;

    /**
     * Koje vrste notifikacija korisnik želi i na mail (popunjava se u Fazi 5).
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $emailNotifications = ['SPECIAL_APPROVAL_SUBMITTED', 'SPECIAL_APPROVAL_REMINDER'];

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }

    public function getInitials(): string
    {
        // prvo slovo imena i prezimena ("Đorđe Šarić" = "ĐŠ"); za "Dž", "Lj", "Nj" samo prvo slovo
        return mb_strtoupper(mb_substr(trim((string) $this->firstName), 0, 1).mb_substr(trim((string) $this->lastName), 0, 1));
    }

    /**
     * Boja avatara (0-7), izvedena iz imena: ista osoba uvek ima istu boju (DESIGN.md 7.9).
     */
    public function getAvatarTone(): int
    {
        return crc32(mb_strtolower($this->getFullName())) % 8;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = Role::RADNIK->value;

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Najviša rola korisnika. U bazi se čuva jedna rola, a hijerarhiju rešava role_hierarchy.
     */
    public function getHighestRole(): Role
    {
        foreach ([Role::SUPERADMIN, Role::ADMIN, Role::SEF] as $role) {
            if (in_array($role->value, $this->roles, true)) {
                return $role;
            }
        }

        return Role::RADNIK;
    }

    public function setHighestRole(Role $role): static
    {
        $this->roles = [$role->value];

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getPosition(): ?Position
    {
        return $this->position;
    }

    /** Ne menja prava pristupa; istoriju beleži UserService::changePosition */
    public function setPosition(?Position $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getEmployedAt(): ?\DateTimeImmutable
    {
        return $this->employedAt;
    }

    public function setEmployedAt(?\DateTimeImmutable $employedAt): static
    {
        $this->employedAt = $employedAt;

        return $this;
    }

    public function getIcsToken(): ?string
    {
        return $this->icsToken;
    }

    public function setIcsToken(?string $token): static
    {
        $this->icsToken = $token;

        return $this;
    }

    public function getAnnualLeaveDays(): ?int
    {
        return $this->annualLeaveDays;
    }

    public function setAnnualLeaveDays(?int $days): static
    {
        $this->annualLeaveDays = $days;

        return $this;
    }

    public function getInternalNote(): ?string
    {
        return $this->internalNote;
    }

    public function setInternalNote(?string $internalNote): static
    {
        $this->internalNote = $internalNote;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getEmailNotifications(): array
    {
        return $this->emailNotifications;
    }

    /**
     * @param list<string> $emailNotifications
     */
    public function setEmailNotifications(array $emailNotifications): static
    {
        $this->emailNotifications = $emailNotifications;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function isEqualTo(UserInterface $user): bool
    {
        // $this je korisnik deserijalizovan iz sesije (password je crc32c skraćenica, vidi __serialize),
        // a $user je sveže učitan iz baze. Deaktiviran korisnik se odmah odjavljuje.
        if (!$user instanceof self || !$user->isActive()) {
            return false;
        }

        $passwordUnchanged = $this->password === $user->getPassword()
            || $this->password === hash('crc32c', (string) $user->getPassword());

        return $passwordUnchanged && $user->getUserIdentifier() === $this->getUserIdentifier();
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', (string) $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }

    public function getThemePreference(): ThemePreference
    {
        return $this->themePreference;
    }

    public function setThemePreference(ThemePreference $themePreference): static
    {
        $this->themePreference = $themePreference;

        return $this;
    }

    public function getCapacityHours(): ?int
    {
        return $this->capacityHours;
    }

    public function setCapacityHours(?int $hours): static
    {
        $this->capacityHours = $hours;

        return $this;
    }
}
