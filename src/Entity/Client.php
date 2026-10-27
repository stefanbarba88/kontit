<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\LegalForm;
use App\Repository\ClientRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_CLIENT_PIB', fields: ['pib'])]
#[UniqueEntity(fields: ['pib'], message: 'Klijent sa ovim PIB-om već postoji.', ignoreNull: true)]
class Client
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Unesite naziv klijenta.')]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 20, enumType: LegalForm::class)]
    #[Assert\NotNull(message: 'Izaberite pravnu formu.')]
    private ?LegalForm $legalForm = null;

    #[ORM\Column(length: 9, nullable: true)]
    #[Assert\Regex(pattern: '/^\d{9}$/', message: 'PIB mora imati tačno 9 cifara.')]
    private ?string $pib = null;

    #[ORM\Column(length: 8, nullable: true)]
    #[Assert\Regex(pattern: '/^\d{8}$/', message: 'Matični broj mora imati tačno 8 cifara.')]
    private ?string $maticniBroj = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $pdvObveznik = false;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $address = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $city = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $shortName = null;

    // --- podaci iz NBS-a (NbsService), osim naziva ostalo se ne unosi ručno ---
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $municipality = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $region = null;

    /** Delatnost (naziv) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $activityName = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $registrationDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $foundingDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbsCompanyTypeId = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbsStatusId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $nbsSyncedAt = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $contactPerson = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: 'Email adresa nije ispravna.')]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $primaryAccountant = null;

    #[ORM\ManyToOne]
    private ?User $backupAccountant = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getLegalForm(): ?LegalForm
    {
        return $this->legalForm;
    }

    public function setLegalForm(?LegalForm $legalForm): static
    {
        $this->legalForm = $legalForm;

        return $this;
    }

    public function getPib(): ?string
    {
        return $this->pib;
    }

    public function setPib(?string $pib): static
    {
        $this->pib = '' === $pib ? null : $pib;

        return $this;
    }

    public function getMaticniBroj(): ?string
    {
        return $this->maticniBroj;
    }

    public function setMaticniBroj(?string $maticniBroj): static
    {
        $this->maticniBroj = '' === $maticniBroj ? null : $maticniBroj;

        return $this;
    }

    public function isPdvObveznik(): bool
    {
        return $this->pdvObveznik;
    }

    public function setPdvObveznik(bool $pdvObveznik): static
    {
        $this->pdvObveznik = $pdvObveznik;

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

    public function getContactPerson(): ?string
    {
        return $this->contactPerson;
    }

    public function setContactPerson(?string $contactPerson): static
    {
        $this->contactPerson = $contactPerson;

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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

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

    public function getPrimaryAccountant(): ?User
    {
        return $this->primaryAccountant;
    }

    public function setPrimaryAccountant(User $primaryAccountant): static
    {
        $this->primaryAccountant = $primaryAccountant;

        return $this;
    }

    public function getBackupAccountant(): ?User
    {
        return $this->backupAccountant;
    }

    public function setBackupAccountant(?User $backupAccountant): static
    {
        $this->backupAccountant = $backupAccountant;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = '' === $postalCode ? null : $postalCode;

        return $this;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): static
    {
        $this->shortName = '' === $shortName ? null : $shortName;

        return $this;
    }

    public function getMunicipality(): ?string
    {
        return $this->municipality;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function getActivityName(): ?string
    {
        return $this->activityName;
    }

    public function getRegistrationDate(): ?\DateTimeImmutable
    {
        return $this->registrationDate;
    }

    public function getFoundingDate(): ?\DateTimeImmutable
    {
        return $this->foundingDate;
    }

    public function getNbsCompanyTypeId(): ?int
    {
        return $this->nbsCompanyTypeId;
    }

    public function getNbsStatusId(): ?int
    {
        return $this->nbsStatusId;
    }

    public function getNbsSyncedAt(): ?\DateTimeImmutable
    {
        return $this->nbsSyncedAt;
    }

    /**
     * Upisuje podatke iz NBS-a (NbsService::lookup). Prazne vrednosti ne brišu ručno unete podatke
     * (telefon, email), a naziv i adresa iz NBS-a su izvor istine.
     *
     * @param array<string, mixed> $data
     */
    public function applyNbs(array $data): static
    {
        $this->name = $data['name'] ?: $this->name;
        $this->shortName = $data['shortName'] ?: $this->shortName;
        $this->pib = $data['pib'] ?: $this->pib;
        $this->maticniBroj = $data['maticniBroj'] ?: $this->maticniBroj;
        $this->address = $data['address'] ?: $this->address;
        $this->postalCode = $data['postalCode'] ?: $this->postalCode;
        $this->city = $data['city'] ?: $this->city;
        $this->phone = $this->phone ?: ($data['phone'] ?: null);
        $this->email = $this->email ?: ($data['email'] ?: null);
        $this->municipality = $data['municipality'] ?: null;
        $this->region = $data['region'] ?: null;
        $this->activityName = $data['activityName'] ?: null;
        $this->registrationDate = $data['registrationDate'];
        $this->foundingDate = $data['foundingDate'];
        $this->nbsCompanyTypeId = $data['companyTypeId'];
        $this->nbsStatusId = $data['statusId'];
        $this->nbsSyncedAt = new \DateTimeImmutable();

        return $this;
    }
}
