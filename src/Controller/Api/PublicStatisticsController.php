<?php
// src/Controller/Api/PublicStatisticsController.php

namespace App\Controller\Api;

use App\Service\PublicStatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PublicStatisticsController extends AbstractController
{
    #[Route('/api/statistics', name: 'api_statistics', methods: ['GET'])]
    public function __invoke(
        PublicStatisticsService $publicStatisticsService,
    ): JsonResponse {
        return $this->json($publicStatisticsService->getData());
    }
}