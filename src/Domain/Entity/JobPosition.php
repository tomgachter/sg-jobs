<?php

declare(strict_types=1);

namespace SGJobs\Domain\Entity;

class JobPosition
{
    public function __construct(
        private int $bexioPositionId,
        private string $articleNumber,
        private string $title,
        private string $description,
        private float $quantity,
        private string $unit,
        private string $workType,
        private int $sort
    ) {
    }

    /**
     * @return array{bexio_position_id:int,article_no:string,title:string,description:string,qty:float,unit:string,work_type:string,sort:int}
     */
    public function toArray(): array
    {
        return [
            'bexio_position_id' => $this->bexioPositionId,
            'article_no' => $this->articleNumber,
            'title' => $this->title,
            'description' => $this->description,
            'qty' => $this->quantity,
            'unit' => $this->unit,
            'work_type' => $this->workType,
            'sort' => $this->sort,
        ];
    }
}
