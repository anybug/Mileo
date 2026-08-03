<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_ADMIN')]
final class NewsContentUploadController extends AbstractController
{
    #[Route('/admin/news/upload-content-file', name: 'admin_news_upload_content_file', methods: ['POST'])]
    public function __invoke(
        Request $request,
        SluggerInterface $slugger,
        string $projectDir,
    ): JsonResponse {
        $file = $request->files->get('file');

        if ($file === null) {
            return new JsonResponse([
                'error' => 'Aucun fichier envoyé.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!str_starts_with((string) $file->getMimeType(), 'image/')) {
            return new JsonResponse([
                'error' => 'Seules les images sont autorisées.',
            ], JsonResponse::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $slugger->slug($originalName)->lower();

        $filename = sprintf(
            '%s-%s.%s',
            $safeName,
            bin2hex(random_bytes(8)),
            $file->guessExtension() ?: 'bin',
        );

        $uploadDir = $projectDir.'/public/uploads/news/content';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        try {
            $file->move($uploadDir, $filename);
        } catch (FileException) {
            return new JsonResponse([
                'error' => 'Impossible d’enregistrer le fichier.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'url' => '/uploads/news/content/'.$filename,
        ]);
    }
}