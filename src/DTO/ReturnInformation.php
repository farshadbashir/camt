<?php

declare(strict_types=1);

namespace Genkgo\Camt\DTO;

class ReturnInformation
{
    /** @param list<string> $additionalInformation */
    public function __construct(
        private ?string $code,
        private ?string $proprietary,
        private array $additionalInformation,
    ) {
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getProprietary(): ?string
    {
        return $this->proprietary;
    }

    /** @return list<string> */
    public function getAdditionalInformation(): array
    {
        return $this->additionalInformation;
    }
}
