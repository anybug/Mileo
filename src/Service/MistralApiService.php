<?php
// src/Service/MistralApiService.php
namespace App\Service;

use App\Entity\Report;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MistralApiService
{
    public function __construct(
        private MistralPromptBuilder $promptBuilder,
        private HttpClientInterface $httpClient,
        private string $apiKey
    ) {}

    public function generateTrips(string $actionType, Report $report, array $parameters): array
    {
        $prompt = $this->promptBuilder->buildPrompt($actionType, $report, $parameters);

        //dd($prompt);

        $response = $this->httpClient->request('POST', 'https://api.mistral.ai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'mistral-small-latest',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.1,
            ],
        ]);

        return $this->extractJsonFromResponse($response->toArray()['choices'][0]['message']['content']);
    }

    private function extractJsonFromResponse(string $response): array
    {
        if (preg_match('/\[.*\]/s', $response, $matches)) {
            return json_decode($matches[0], true) ?? [];
        }
        return [];
    }
}
