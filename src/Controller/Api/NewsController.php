<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\News;
use App\Repository\NewsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/news', name: 'api_news_')]
final class NewsController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        NewsRepository $newsRepository,
    ): JsonResponse {
        $news = $newsRepository->findLatestPublished(2);

        return $this->json([
            'data' => array_map(
                static fn (News $item): array => [
                    'id' => $item->getId(),
                    'title' => $item->getTitle(),
                    'content' => $item->getContent(),
                    'publishedAt' => $item
                        ->getPublishedAt()
                        ?->format(\DateTimeInterface::ATOM),
                    'createdAt' => $item
                        ->getCreatedAt()
                        ?->format(\DateTimeInterface::ATOM),
                    'updatedAt' => $item
                        ->getUpdatedAt()
                        ?->format(\DateTimeInterface::ATOM),
                ],
                $news,
            ),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(News $news): JsonResponse
    {
        if (
            $news->getPublishedAt() === null
            || $news->getPublishedAt() > new \DateTimeImmutable()
        ) {
            throw $this->createNotFoundException(
                'Actualité introuvable.',
            );
        }

        return $this->json([
            'data' => [
                'id' => $news->getId(),
                'title' => $news->getTitle(),
                'content' => $news->getContent(),
                'publishedAt' => $news
                    ->getPublishedAt()
                    ?->format(\DateTimeInterface::ATOM),
                'createdAt' => $news
                    ->getCreatedAt()
                    ?->format(\DateTimeInterface::ATOM),
                'updatedAt' => $news
                    ->getUpdatedAt()
                    ?->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }
}