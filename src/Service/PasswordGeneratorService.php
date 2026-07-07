<?php

namespace App\Service;

class PasswordGeneratorService
{
    public function generate(
        int $length = 16,
        bool $uppercase = true,
        bool $lowercase = true,
        bool $numbers = true,
        bool $symbols = true,
    ): string {
        $pool     = '';
        $required = [];

        if ($lowercase) {
            $set      = 'abcdefghijklmnopqrstuvwxyz';
            $pool     .= $set;
            $required[] = $set;
        }
        if ($uppercase) {
            $set      = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $pool     .= $set;
            $required[] = $set;
        }
        if ($numbers) {
            $set      = '0123456789';
            $pool     .= $set;
            $required[] = $set;
        }
        if ($symbols) {
            $set      = '!@#$%^&*-_=+';
            $pool     .= $set;
            $required[] = $set;
        }

        if ($pool === '') {
            throw new \InvalidArgumentException('At least one character set must be selected.');
        }

        $password = [];

        foreach ($required as $set) {
            $password[] = $set[random_int(0, \strlen($set) - 1)];
        }

        for ($i = \count($password); $i < $length; $i++) {
            $password[] = $pool[random_int(0, \strlen($pool) - 1)];
        }

        // Fisher-Yates using the CSPRNG. shuffle() uses the non-cryptographic Mersenne-Twister,
        // which would leak the arrangement (and the fixed positions of the guaranteed chars).
        for ($i = \count($password) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$password[$i], $password[$j]] = [$password[$j], $password[$i]];
        }

        return implode('', $password);
    }

    public function strength(string $password): array
    {
        $score = 0;
        $len   = \strlen($password);

        if ($len >= 8)  $score++;
        if ($len >= 12) $score++;
        if ($len >= 16) $score++;
        if (preg_match('/[a-z]/', $password))        $score++;
        if (preg_match('/[A-Z]/', $password))        $score++;
        if (preg_match('/[0-9]/', $password))        $score++;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $score++;

        $label = match (true) {
            $score <= 2 => 'Très faible',
            $score <= 3 => 'Faible',
            $score <= 4 => 'Moyen',
            $score <= 5 => 'Fort',
            default     => 'Très fort',
        };

        return ['score' => $score, 'max' => 7, 'label' => $label];
    }
}
