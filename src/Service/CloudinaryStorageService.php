<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CloudinaryStorageService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $cloudName = null,
        private readonly ?string $apiKey = null,
        private readonly ?string $apiSecret = null,
        private readonly ?string $uploadPreset = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return trim((string) $this->cloudName) !== ''
            && trim((string) $this->apiKey) !== ''
            && trim((string) $this->apiSecret) !== '';
    }

    /**
     * @return array{url:string,public_id:string,resource_type:string,mime_type:string,uploaded_at:string}
     */
    public function uploadGuaranteeDocument(UploadedFile $file, int $userId, ?int $garantieId = null): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Cloudinary n est pas configure. Definissez CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY et CLOUDINARY_API_SECRET.');
        }

        $timestamp = time();
        $folder = 'nexora/garanties';
        $context = sprintf('user_id=%d|garantie_id=%s', $userId, $garantieId !== null ? (string) $garantieId : 'new');
        $signatureBase = sprintf('context=%s&folder=%s&timestamp=%d%s', $context, $folder, $timestamp, (string) $this->apiSecret);
        $signature = sha1($signatureBase);

        $url = sprintf('https://api.cloudinary.com/v1_1/%s/auto/upload', $this->cloudName);
        $response = $this->httpClient->request('POST', $url, [
            'body' => [
                'file' => fopen($file->getPathname(), 'rb'),
                'api_key' => (string) $this->apiKey,
                'timestamp' => (string) $timestamp,
                'signature' => $signature,
                'folder' => $folder,
                'context' => $context,
                'upload_preset' => (string) $this->uploadPreset,
            ],
        ]);

        $payload = $response->toArray(false);
        if ((int) $response->getStatusCode() >= 400 || !isset($payload['secure_url'], $payload['public_id'])) {
            $message = (string) ($payload['error']['message'] ?? 'Echec upload Cloudinary.');
            throw new \RuntimeException($message);
        }

        return [
            'url' => (string) $payload['secure_url'],
            'public_id' => (string) $payload['public_id'],
            'resource_type' => (string) ($payload['resource_type'] ?? 'raw'),
            'mime_type' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
            'uploaded_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }
}

