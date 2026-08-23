<?php declare(strict_types=1);

namespace App\Http;

/** The result of a {@see SafeHttpFetcher} request. */
final readonly class FetchedResource
{
    /** @param array<string, list<string>> $headers */
    public function __construct(
        public string $url,
        public int $statusCode,
        public string $content,
        public array $headers,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }
}
