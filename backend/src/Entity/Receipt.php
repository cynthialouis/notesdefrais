<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;

/**
 * Justificatif d'une note (spec 005), un seul par note : remplacer, c'est en créer un nouveau.
 * Le fichier est sur disque (ReceiptStorage) sous un nom généré par le serveur ; le nom d'origine
 * ne sert qu'à l'affichage. Jamais modifié après sa création.
 */
#[ORM\Entity]
class Receipt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    public function __construct(
        /** Nom du fichier chez l'utilisateur, pour l'affichage seulement */
        #[ORM\Column(length: 255)]
        private string $originalName,
        /** Type détecté d'après le contenu : image/jpeg, image/png ou application/pdf */
        #[ORM\Column(length: 50)]
        private string $mimeType,
        #[ORM\Column]
        private int $sizeBytes,
        /** Nom du fichier dans le stockage, généré par le serveur */
        #[ORM\Column(length: 64, unique: true)]
        private string $storageName,
    ) {
        // Stocké en UTC (voir Expense::utc())
        $this->uploadedAt = Clock::get()->now()->setTimezone(new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }
}
