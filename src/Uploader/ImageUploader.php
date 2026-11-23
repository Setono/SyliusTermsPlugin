<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Uploader;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

final readonly class ImageUploader implements ImageUploaderInterface
{
    /**
     * The Flysystem storage of Uploaded images, which SetonoSyliusTermsExtension::prepend() configures. Apps move it by
     * defining a storage of this name in their own config
     */
    public const STORAGE = 'setono_sylius_terms.uploaded_image_storage';

    /** In bytes, 5 MB. The Terms PDF has the same cap when it downloads images, see ImageInliner */
    private const MAX_SIZE = 5 * 1024 * 1024;

    /** The extension of each allowed image type */
    private const EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private FilesystemOperator $storage,
        private MimeTypeGuesserInterface $mimeTypeGuesser,
    ) {
    }

    public function upload(UploadedFile $file): string
    {
        if (!$file->isValid()) {
            // PHP stops an upload larger than its upload_max_filesize, or than the form's MAX_FILE_SIZE. Either may be
            // below 5 MB
            throw in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)
                ? RejectedImageException::tooLargeForServer($file->getClientOriginalName())
                : RejectedImageException::notUploaded($file->getClientOriginalName());
        }

        $path = $file->getPathname();

        // Judged by the bytes, never by the file's name or the MIME type the client sent
        $extension = self::EXTENSIONS[$this->mimeTypeGuesser->guessMimeType($path) ?? ''] ?? null;
        if (null === $extension) {
            throw RejectedImageException::notAnImage($file->getClientOriginalName());
        }

        if ($file->getSize() > self::MAX_SIZE) {
            throw RejectedImageException::tooLarge($file->getClientOriginalName());
        }

        $bytes = $file->getContent();
        $name = sprintf('%s.%s', hash('sha256', $bytes), $extension);

        // The same bytes get the same name, so an image that is stored already is the same image. It is stored as
        // uploaded: no re-encoding, no resizing and no stripping of metadata
        if (!$this->storage->fileExists($name)) {
            $this->storage->write($name, $bytes);
        }

        return $this->storage->publicUrl($name);
    }
}
