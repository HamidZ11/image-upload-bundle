<?php

namespace Hamid\ImageUploadBundle;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ImageUploader
{
    public function __construct(
        private readonly string $directory,
        private readonly ValidatorInterface $validator,
        private readonly ?LoggerInterface $logger = null,
        private readonly string $maxSize = '5M',
        private readonly int $maxPixels = 10_000_000,
    ) {
    }

    public function upload(UploadedFile $file): string
    {
        // Service callers receive the same protection as form submissions.
        $violations = $this->validator->validate($file, $this->getImageConstraint());
        if (count($violations) > 0) {
            throw new FileException('The uploaded file is not a valid supported image.');
        }

        // Use the detected content type, never the original filename or its extension.
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new FileException('Unsupported image format.'),
        };
        $filename = bin2hex(random_bytes(16)).'.'.$extension;

        try {
            $file->move($this->directory, $filename);
        } catch (FileException $exception) {
            $this->logger?->error('Image upload failed.', ['exception' => $exception]);
            throw $exception;
        }

        return $filename;
    }

    public function remove(?string $filename): void
    {
        if (null === $filename) {
            return;
        }

        // Only generated image names may be removed; a stored path must never escape this directory.
        if (1 !== preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $filename)) {
            $this->logger?->warning('Refusing to remove an invalid image filename.', ['filename' => $filename]);
            return;
        }

        $path = $this->directory.'/'.$filename;
        if (is_file($path) && !@unlink($path)) {
            // A cleanup failure must not undo an already successful database save.
            $this->logger?->warning('Image file could not be removed.', ['filename' => $filename]);
        }
    }

    public function getImageConstraint(): Image
    {
        return new Image(
            maxSize: $this->maxSize,
            mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
            // Bound decoded image memory before checking for corruption.
            maxPixels: $this->maxPixels,
            // Decode the image as well as checking its detected type and dimensions.
            detectCorrupted: true,
        );
    }
}
