<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Controller\Action;

use Setono\SyliusTermsPlugin\Uploader\ImageUploaderInterface;
use Setono\SyliusTermsPlugin\Uploader\RejectedImageException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Stores an image the admin dropped, pasted or picked in the Content editor. It answers in JSON with the image's URL,
 * or, when the image is rejected, with a translated error that names the file. It is an admin route, so only a logged-in
 * admin reaches it
 */
final readonly class UploadImageAction
{
    /** The id of the CSRF token the Content editor sends along with each image */
    public const CSRF_TOKEN_ID = 'setono_sylius_terms_upload_image';

    public function __construct(
        private ImageUploaderInterface $imageUploader,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $token = new CsrfToken(self::CSRF_TOKEN_ID, $request->request->getString('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new AccessDeniedHttpException('The CSRF token is invalid');
        }

        $image = $request->files->get('image');
        if (!$image instanceof UploadedFile) {
            throw new BadRequestHttpException('The request has no image');
        }

        try {
            return new JsonResponse(['url' => $this->imageUploader->upload($image)]);
        } catch (RejectedImageException $exception) {
            return new JsonResponse(['error' => $exception->trans($this->translator)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
