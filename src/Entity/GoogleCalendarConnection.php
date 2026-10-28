<?php

namespace App\Entity;

use App\Repository\GoogleCalendarConnectionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Povezan Google nalog korisnika (SPEC 8n.3). Aplikacija upisuje samo u svoj kalendar "KontIT" (scope calendar.app.created).
 * Tokeni su šifrovani (TokenCipher, libsodium), nikad u čistom obliku u bazi.
 */
#[ORM\Entity(repositoryClass: GoogleCalendarConnectionRepository::class)]
class GoogleCalendarConnection
{
    /** Posle ovoliko neuspeha zaredom korisnik dobija obaveštenje */
    public const MAX_FAILURES = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $googleEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $calendarId = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $accessToken;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $refreshToken = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $connectedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column]
    private int $failures = 0;

    public function __construct(User $user, string $encryptedAccessToken)
    {
        $this->user = $user;
        $this->accessToken = $encryptedAccessToken;
        $this->connectedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getGoogleEmail(): ?string
    {
        return $this->googleEmail;
    }

    public function setGoogleEmail(?string $email): static
    {
        $this->googleEmail = $email;

        return $this;
    }

    public function getCalendarId(): ?string
    {
        return $this->calendarId;
    }

    public function setCalendarId(?string $calendarId): static
    {
        $this->calendarId = $calendarId;

        return $this;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $encrypted): static
    {
        $this->accessToken = $encrypted;

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $encrypted): static
    {
        $this->refreshToken = $encrypted;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getConnectedAt(): \DateTimeImmutable
    {
        return $this->connectedAt;
    }

    public function getLastSyncAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getFailures(): int
    {
        return $this->failures;
    }

    public function markSynced(): static
    {
        $this->lastSyncAt = new \DateTimeImmutable();
        $this->lastError = null;
        $this->failures = 0;

        return $this;
    }

    /**
     * @return bool da li je baš sad dostignut prag neuspeha (tada ide obaveštenje, jednom)
     */
    public function markFailed(string $error): bool
    {
        $this->lastError = mb_substr($error, 0, 1000);
        ++$this->failures;

        return self::MAX_FAILURES === $this->failures;
    }
}
