<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Receives media (video/photos) that the browser extension grabbed from an
 * Instagram post — media the server itself cannot download — and attaches it to
 * the matching item (by permalink), then queues transcription. Authenticated by
 * its own bearer token (MEDIA_UPLOAD_TOKEN), opened as PUBLIC_ACCESS.
 */
class MediaUploadController extends AbstractController
{
    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'webm', 'm4v'];
    private const PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly FilesystemOperator $defaultStorage,
        private readonly string $mediaUploadToken,
    ) {
    }

    #[Route('/media-upload', name: 'app_media_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        if ($this->mediaUploadToken === '' || !hash_equals($this->mediaUploadToken, $this->bearerToken($request))) {
            return new JsonResponse(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $permalink = trim((string) $request->request->get('permalink', ''));
        if ($permalink === '') {
            return new JsonResponse(['error' => 'permalink is required'], Response::HTTP_BAD_REQUEST);
        }

        $item = $this->findItemByPermalink($permalink);
        if ($item === null) {
            return new JsonResponse(['error' => 'no item found for permalink'], Response::HTTP_NOT_FOUND);
        }

        $profile = $item->getProfile();
        if ($profile === null) {
            return new JsonResponse(['error' => 'item has no profile'], Response::HTTP_CONFLICT);
        }

        $video = $request->files->get('video');
        /** @var list<UploadedFile> $photos */
        $photos = array_values(array_filter($request->files->all('photos')));

        if (!$video instanceof UploadedFile && $photos === []) {
            return new JsonResponse(['error' => 'no media provided'], Response::HTTP_BAD_REQUEST);
        }

        $prefix = sprintf('%d/%d', $profile->getId(), $item->getId());
        $stored = ['photos' => 0, 'video' => false];

        if ($video instanceof UploadedFile) {
            $ext = $this->safeExtension($video, self::VIDEO_EXTENSIONS);
            if ($ext === null) {
                return new JsonResponse(['error' => 'unsupported video type'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
            }
            $path = sprintf('%s/video.%s', $prefix, $ext);
            $this->defaultStorage->write($path, (string) file_get_contents($video->getPathname()));
            $item->setVideoPath($path);
            $stored['video'] = true;
        }

        if ($photos !== []) {
            $paths = [];
            foreach ($photos as $i => $photo) {
                $ext = $this->safeExtension($photo, self::PHOTO_EXTENSIONS);
                if ($ext === null) {
                    return new JsonResponse(['error' => 'unsupported photo type'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
                }
                $path = sprintf('%s/photo_%d.%s', $prefix, $i, $ext);
                $this->defaultStorage->write($path, (string) file_get_contents($photo->getPathname()));
                $paths[] = $path;
            }
            $item->setPhotoPaths($paths);
            $stored['photos'] = count($paths);
        }

        $item->setMediaStatus('completed');
        $item->setMediaError(null);

        if ($stored['video'] && $profile->isTranscribeVideos()) {
            $item->setTranscriptStatus('pending');
            $item->setTranscriptError(null);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'stored',
            'itemId' => $item->getId(),
            'video' => $stored['video'],
            'photos' => $stored['photos'],
            'transcriptQueued' => $stored['video'] && $profile->isTranscribeVideos(),
        ]);
    }

    /**
     * Instagram permalinks differ only by a trailing slash between the page's
     * canonical URL and what was stored, so match both forms.
     */
    private function findItemByPermalink(string $permalink): ?\App\Entity\Item
    {
        $base = rtrim($permalink, '/');
        foreach (array_unique([$permalink, $base, $base . '/']) as $candidate) {
            $item = $this->itemRepository->findOneBy(['permalink' => $candidate]);
            if ($item !== null) {
                return $item;
            }
        }

        return null;
    }

    private function bearerToken(Request $request): string
    {
        $header = $request->headers->get('Authorization', '');

        return str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';
    }

    /**
     * @param list<string> $allowed
     */
    private function safeExtension(UploadedFile $file, array $allowed): ?string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());

        return in_array($ext, $allowed, true) ? $ext : null;
    }
}
