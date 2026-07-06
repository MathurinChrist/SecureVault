<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploader
{
    /** Allowed image MIME types → the extension we assign (never trust the client's). */
    private const ALLOWED = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    private const MAX_BYTES = 5_242_880; // 5 MiB

    public function __construct(
        private string $targetDirectory,
        private SluggerInterface $slugger,
    ) {
    }

    public function upload(UploadedFile $file): string
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new \Exception('Le fichier est trop volumineux (max 5 Mo).');
        }

        // Validate against the detected MIME type, not the client-supplied name/extension, so an
        // .svg/.html/.php disguised as an image cannot be stored under the web root (stored XSS/RCE).
        $mime = $file->getMimeType();
        if (!isset(self::ALLOWED[$mime])) {
            throw new \Exception('Type de fichier non autorisé. Formats acceptés : PNG, JPEG, WEBP, GIF.');
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = $safeFilename.'-'.uniqid().'.'.self::ALLOWED[$mime];

        try {
            $file->move($this->getTargetDirectory(), $fileName);
        } catch (FileException $e) {
            throw new \Exception('Impossible de télécharger le fichier.');
        }

        return $fileName;
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }
}
