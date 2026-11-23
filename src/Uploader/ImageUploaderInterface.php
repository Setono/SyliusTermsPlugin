<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Uploader;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores an image an admin uploaded into Content, an Uploaded image (ADR 0009)
 */
interface ImageUploaderInterface
{
    /**
     * Stores the image under a name made from its bytes, unless it is stored already, and returns the URL it is served at
     *
     * @throws RejectedImageException if the upload failed, or the file isn't a PNG, JPEG, GIF or WebP image of at most 5 MB
     */
    public function upload(UploadedFile $file): string;
}
