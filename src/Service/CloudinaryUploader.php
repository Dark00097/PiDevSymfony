<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CloudinaryUploader
{
    /**
     * @var array<string, string>
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(string:CLOUDINARY_CLOUD_NAME)%')]
        private readonly string $cloudName,
        #[Autowire('%env(string:CLOUDINARY_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(string:CLOUDINARY_API_SECRET)%')]
        private readonly string $apiSecret,
    ) {
    }

    public function uploadProfileImage(UploadedFile $file, string $folder = 'nexora/profile'): string
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('Profile image upload failed.');
        }

        $mimeType = (string) $file->getMimeType();
        if (!array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new \InvalidArgumentException('Only JPG, PNG, WEBP or GIF profile images are allowed.');
        }

        $this->assertConfigured();

        $timestamp = (string) time();
        $publicId = sprintf('profile_%s', bin2hex(random_bytes(10)));
        $signature = $this->buildSignature([
            'folder' => $folder,
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ]);

        $apiUrl = sprintf('https://api.cloudinary.com/v1_1/%s/image/upload', rawurlencode($this->cloudName));
        $formData = new FormDataPart([
            'file' => DataPart::fromPath(
                $file->getPathname(),
                $file->getClientOriginalName() !== '' ? $file->getClientOriginalName() : ($publicId.'.'.self::ALLOWED_MIME_TYPES[$mimeType]),
                $mimeType
            ),
            'api_key' => $this->apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'folder' => $folder,
            'public_id' => $publicId,
        ]);

        $response = $this->httpClient->request('POST', $apiUrl, [
            'headers' => $formData->getPreparedHeaders()->toArray(),
            'body' => $formData->bodyToIterable(),
        ]);

        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);

        if ($statusCode >= 400) {
            $cloudinaryError = (string) (($payload['error']['message'] ?? '') ?: '');
            throw new \RuntimeException($cloudinaryError !== '' ? 'Cloudinary upload failed: '.$cloudinaryError : 'Cloudinary upload failed.');
        }

        $secureUrl = trim((string) ($payload['secure_url'] ?? ''));
        if ($secureUrl === '') {
            throw new \RuntimeException('Cloudinary upload failed: secure URL was not returned.');
        }

        return $secureUrl;
    }

    /**
     * @param array<string, string> $params
     */
    private function buildSignature(array $params): string
    {
        ksort($params);

        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === '') {
                continue;
            }

            $parts[] = $key.'='.$value;
        }

        return sha1(implode('&', $parts).$this->apiSecret);
    }

    private function assertConfigured(): void
    {
        if (trim($this->cloudName) === '' || trim($this->apiKey) === '' || trim($this->apiSecret) === '') {
            throw new \RuntimeException('Cloudinary credentials are missing. Configure CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY and CLOUDINARY_API_SECRET.');
        }
    }
}
