<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Import\ImageDownloader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ImageDownloaderTest extends TestCase
{
    public function testRedirectsAreNotFollowed(): void
    {
        $requestOptions = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestOptions): MockResponse {
            $requestOptions = $options;

            return new MockResponse('', [
                'http_code' => 302,
                'response_headers' => ['location: https://example.com/image.jpg'],
            ]);
        });
        $storage = sys_get_temp_dir() . '/image-downloader-' . bin2hex(random_bytes(6));
        $downloader = new ImageDownloader($client, $storage, 1024, 1.0);

        try {
            $downloader->download('https://93.184.216.34/redirect');
            self::fail('A redirect response must be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Image server returned HTTP 302.', $exception->getMessage());
            self::assertSame(0, $requestOptions['max_redirects'] ?? null);
        } finally {
            if (is_dir($storage . '/images')) {
                rmdir($storage . '/images');
            }
            if (is_dir($storage)) {
                rmdir($storage);
            }
        }
    }
}
