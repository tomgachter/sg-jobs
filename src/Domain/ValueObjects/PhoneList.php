<?php

declare(strict_types=1);

namespace SGJobs\Domain\ValueObjects;

class PhoneList
{
    /** @var string[] */
    private array $phones;

    /**
     * @param string[] $phones
     */
    public function __construct(array $phones)
    {
        $this->phones = array_values(array_filter(array_map([$this, 'normalise'], $phones)));
    }

    public function toArray(): array
    {
        return $this->phones;
    }

    private function normalise(string $phone): string
    {
        $clean = preg_replace('/[^0-9+]/', '', $phone) ?? '';
        if ($clean === '' || $clean[0] !== '+') {
            $clean = '+' . ltrim($clean, '+');
        }

        return $clean;
    }

    public function __toString(): string
    {
        return implode('; ', $this->phones);
    }
}
