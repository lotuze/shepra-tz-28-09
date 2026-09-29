<?php

declare(strict_types=1);

namespace App\Import;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ImageDownloader implements ImageDownloaderInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $storagePath,
        private readonly int $maxSize,
        private readonly float $timeout,
    ) {
    }

    public function download(string $url): ImageDownloadResult
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '' || strtolower($host) === 'localhost') {
            throw new \RuntimeException('Image URL host is invalid.');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);
        if ($ips === false || $ips === []) {
            throw new \RuntimeException('Image host cannot be resolved.');
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new \RuntimeException('Private or reserved image host is not allowed.');
            }
        }

        $relative = 'images/' . hash('sha256', $url) . '.img';
        $target = $this->storagePath . '/' . $relative;
        if (is_file($target) && filesize($target) > 0) {
            return new ImageDownloadResult($relative, false);
        }
        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
            throw new \RuntimeException('Unable to create image storage directory.');
        }
        $temporary = $target . '.' . bin2hex(random_bytes(6)) . '.part';
        try {
            $response = $this->client->request('GET', $url, [
                'timeout' => $this->timeout,
                'max_duration' => $this->timeout,
                'max_redirects' => 0,
            ]);
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                throw new \RuntimeException('Image server returned HTTP ' . $response->getStatusCode() . '.');
            }
            $type = strtolower($response->getHeaders(false)['content-type'][0] ?? '');
            if (!str_starts_with($type, 'image/')) {
                throw new \RuntimeException('Downloaded content is not an image.');
            }
            $handle = fopen($temporary, 'wb');
            if ($handle === false) {
                throw new \RuntimeException('Unable to create image file.');
            }
            $size = 0;
            foreach ($this->client->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    throw new \RuntimeException('Image download timed out.');
                }
                $content = $chunk->getContent();
                $size += strlen($content);
                if ($size > $this->maxSize) {
                    throw new \RuntimeException('Image exceeds maximum allowed size.');
                }
                fwrite($handle, $content);
            }
            fclose($handle);
            if ($size === 0 || !rename($temporary, $target)) {
                throw new \RuntimeException('Downloaded image is empty or cannot be stored.');
            }
            return new ImageDownloadResult($relative, true);
        } catch (\Throwable $exception) {
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }
            if (is_file($temporary)) {
                unlink($temporary);
            }
            throw $exception;
        }
    }
}
