<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Fichiers des justificatifs (spec 005, C12) : hors de public/, dans var/ (ignoré par git), un dossier
 * par environnement pour que les tests n'écrivent pas dans celui de dev. Le nom de stockage est généré
 * ici, jamais dérivé du nom d'origine.
 */
final class ReceiptStorage
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/receipts/%kernel.environment%')]
        private readonly string $directory,
    ) {
    }

    /** Déplace le fichier dans le stockage et renvoie son nom de stockage. */
    public function store(File $file): string
    {
        $name = bin2hex(random_bytes(16));
        $file->move($this->directory, $name);

        return $name;
    }

    public function path(string $storageName): string
    {
        return $this->directory.'/'.basename($storageName);
    }

    /** Vide le stockage de l'environnement (données de démo rechargées : make db). */
    public function purge(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /** Efface le fichier s'il existe encore (effacer deux fois n'est pas une erreur). */
    public function delete(string $storageName): void
    {
        $path = $this->path($storageName);
        if (is_file($path)) {
            unlink($path);
        }
    }
}
