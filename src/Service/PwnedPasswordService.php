<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PwnedPasswordService
{
    private const API_URL = 'https://api.pwnedpasswords.com/range/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    /**
     * Returns the number of times a plaintext password has appeared in known data breaches.
     * Uses k-anonymity: only the first 5 chars of the SHA-1 hash are sent to the API.
     */
    public function countBreaches(string $plaintextPassword): int
    {
        $hash   = strtoupper(sha1($plaintextPassword));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);

        $response = $this->httpClient->request('GET', self::API_URL . $prefix, [
            'headers'      => ['Add-Padding' => 'true'],
            'timeout'      => 5,
            'max_duration' => 10,
        ]);

        foreach (explode("\n", $response->getContent()) as $line) {
            [$lineSuffix, $count] = explode(':', trim($line)) + ['', '0'];
            if (strtoupper($lineSuffix) === $suffix) {
                return (int) $count;
            }
        }

        return 0;
    }

    public function isBreached(string $plaintextPassword): bool
    {
        return $this->countBreaches($plaintextPassword) > 0;
    }
}
