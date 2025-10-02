<?php

declare(strict_types=1);

namespace SGJobs\Domain\Entity;

use SGJobs\Domain\Entity\JobPosition;
use SGJobs\Domain\Entity\Team;
use SGJobs\Domain\EmojiStatus;
use SGJobs\Domain\ValueObjects\Address;
use SGJobs\Domain\ValueObjects\PhoneList;
use SGJobs\Domain\ValueObjects\TimeRange;

class Job
{
    public function __construct(
        private int $id,
        private int $deliveryNoteId,
        private string $deliveryNoteNumber,
        private ?string $salesOrderNumber,
        private Team $team,
        private TimeRange $timeRange,
        private Address $address,
        private PhoneList $phones,
        private string $customerName,
        private string $status,
        private string $timezone,
        private ?string $caldavEventUid,
        private string $publicUrl,
        private array $positions,
        private ?string $notes
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function deliveryNoteNumber(): string
    {
        return $this->deliveryNoteNumber;
    }

    public function customerName(): string
    {
        return $this->customerName;
    }

    public function phones(): PhoneList
    {
        return $this->phones;
    }

    public function address(): Address
    {
        return $this->address;
    }

    public function timeRange(): TimeRange
    {
        return $this->timeRange;
    }

    public function team(): Team
    {
        return $this->team;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function statusEmoji(): string
    {
        return EmojiStatus::fromStatus($this->status);
    }

    public function salesOrderNumber(): ?string
    {
        return $this->salesOrderNumber;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function caldavEventUid(): ?string
    {
        return $this->caldavEventUid;
    }

    public function publicUrl(): string
    {
        return $this->publicUrl;
    }

    /** @return JobPosition[] */
    public function positions(): array
    {
        return $this->positions;
    }

    public function withStatus(string $status): self
    {
        return new self(
            $this->id,
            $this->deliveryNoteId,
            $this->deliveryNoteNumber,
            $this->salesOrderNumber,
            $this->team,
            $this->timeRange,
            $this->address,
            $this->phones,
            $this->customerName,
            $status,
            $this->timezone,
            $this->caldavEventUid,
            $this->publicUrl,
            $this->positions,
            $this->notes
        );
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function deliveryNoteId(): int
    {
        return $this->deliveryNoteId;
    }

    public function timeRangeUtc(): TimeRange
    {
        return $this->timeRange->toUtc($this->timezone);
    }
}
