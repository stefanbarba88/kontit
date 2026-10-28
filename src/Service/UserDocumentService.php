<?php

namespace App\Service;

use App\Entity\DocumentCategory;
use App\Entity\User;
use App\Entity\UserAbsence;
use App\Entity\UserDocument;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Dokumenti korisnika (SPEC 8l). Fajlovi su van public/ u var/storage/user-documents/{userId}/ pod nasumičnim imenom.
 * Dozvoljeni su PDF, JPG i PNG do 20 MB (tip se proverava po sadržaju fajla, ne po nastavku).
 */
class UserDocumentService
{
    public const MAX_SIZE = 20 * 1024 * 1024;
    public const MIME_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationService $notifications,
        #[Autowire('%kernel.project_dir%/var/storage/user-documents')] private readonly string $dir,
    ) {
    }

    /**
     * Čuva otpremljen fajl i pravi zapis. Admin koji dodaje dokument drugome šalje mu obaveštenje.
     */
    public function upload(User $owner, User $actor, UploadedFile $file, DocumentCategory $category, string $title, ?\DateTimeImmutable $validUntil, ?string $note, ?UserAbsence $absence): UserDocument
    {
        if (!$file->isValid()) {
            throw new \DomainException('Fajl nije otpremljen: '.$file->getErrorMessage());
        }
        if ($file->getSize() > self::MAX_SIZE) {
            throw new \DomainException('Fajl je veći od 20 MB.');
        }
        $mime = (string) $file->getMimeType();
        if (!isset(self::MIME_TYPES[$mime])) {
            throw new \DomainException('Dozvoljeni su samo PDF, JPG i PNG fajlovi.');
        }
        if (null !== $absence && $absence->getUser() !== $owner) {
            throw new \DomainException('Odsustvo ne pripada ovom korisniku.');
        }

        $stored = bin2hex(random_bytes(16)).'.'.self::MIME_TYPES[$mime];
        $size = (int) $file->getSize();
        $original = mb_substr($file->getClientOriginalName() ?: 'dokument.'.self::MIME_TYPES[$mime], 0, 255);
        $file->move($this->folder($owner), $stored);

        $document = (new UserDocument($owner, $actor, $stored, $original, $mime, $size))
            ->setCategory($category)
            ->setTitle('' !== trim($title) ? mb_substr(trim($title), 0, 150) : pathinfo($original, \PATHINFO_FILENAME))
            ->setValidUntil($validUntil)
            ->setNote($note)
            ->setAbsence($absence);
        $this->em->persist($document);
        $this->em->flush();

        if ($actor !== $owner) {
            $this->notifications->notify($owner, NotificationType::DOCUMENT_ADDED, 'Dodat ti je dokument', sprintf('%s je dodao dokument „%s“ (%s).', $actor->getFullName(), $document->getTitle(), $category->getName()), ['user_documents', ['id' => $owner->getId()]], $actor);
        }

        return $document;
    }

    public function path(UserDocument $document): string
    {
        return $this->folder($document->getUser()).'/'.$document->getStoredName();
    }

    public function delete(UserDocument $document): void
    {
        $document->softDelete();
        $this->em->flush();
    }

    public function restore(UserDocument $document): void
    {
        $document->restore();
        $this->em->flush();
    }

    /**
     * Trajno brisanje iz korpe (samo admin): zapis i fajl na disku.
     */
    public function purge(UserDocument $document): void
    {
        if (!$document->isDeleted()) {
            throw new \DomainException('Trajno se briše samo dokument iz korpe.');
        }
        $path = $this->path($document);
        $this->em->remove($document);
        $this->em->flush();
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function folder(User $user): string
    {
        $folder = $this->dir.'/'.$user->getId();
        if (!is_dir($folder) && !mkdir($folder, 0770, true) && !is_dir($folder)) {
            throw new \RuntimeException('Folder za dokumente nije napravljen.');
        }

        return $folder;
    }
}
