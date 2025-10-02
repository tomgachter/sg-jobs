<?php

declare(strict_types=1);

namespace SGJobs\Domain\Entity;

class Team
{
    public function __construct(
        private int $id,
        private string $name,
        private string $principal,
        private string $calendarPath,
        private string $blockerPath
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function principal(): string
    {
        return $this->principal;
    }

    public function calendarPath(): string
    {
        return $this->calendarPath;
    }

    public function blockerPath(): string
    {
        return $this->blockerPath;
    }
}
