<?php

declare(strict_types=1);

namespace SGJobs\Domain\ValueObjects;

class Address
{
    public function __construct(
        private string $street,
        private string $zip,
        private string $city,
        private string $country
    ) {
    }

    public function city(): string
    {
        return $this->city;
    }

    public function formatted(): string
    {
        return sprintf('%s, %s %s, %s', $this->street, $this->zip, $this->city, $this->country);
    }
}
