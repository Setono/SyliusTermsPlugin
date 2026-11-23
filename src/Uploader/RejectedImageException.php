<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Uploader;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * An uploaded file the ImageUploaderInterface didn't store. It translates to a message for the admin that names the file
 */
final class RejectedImageException extends \RuntimeException implements TranslatableInterface
{
    private function __construct(
        string $message,
        private readonly string $translationKey,
        private readonly string $fileName,
    ) {
        parent::__construct($message);
    }

    public static function notAnImage(string $fileName): self
    {
        return new self(
            sprintf('"%s" is not a PNG, JPEG, GIF or WebP image', $fileName),
            'setono_sylius_terms.ui.uploaded_image.not_an_image',
            $fileName,
        );
    }

    public static function tooLarge(string $fileName): self
    {
        return new self(
            sprintf('"%s" is larger than 5 MB', $fileName),
            'setono_sylius_terms.ui.uploaded_image.too_large',
            $fileName,
        );
    }

    /**
     * PHP stopped the upload, because it is larger than PHP's upload_max_filesize, which may be below 5 MB
     */
    public static function tooLargeForServer(string $fileName): self
    {
        return new self(
            sprintf('"%s" is larger than the server accepts', $fileName),
            'setono_sylius_terms.ui.uploaded_image.too_large_for_server',
            $fileName,
        );
    }

    public static function notUploaded(string $fileName): self
    {
        return new self(
            sprintf('"%s" could not be uploaded', $fileName),
            'setono_sylius_terms.ui.uploaded_image.upload_failed',
            $fileName,
        );
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->translationKey, ['%name%' => $this->fileName], 'messages', $locale);
    }
}
