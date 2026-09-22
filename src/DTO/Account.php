<?php

declare(strict_types=1);

namespace Genkgo\Camt\DTO;

abstract class Account
{
    private ?string $currencyCode = null;

    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(?string $currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    abstract public function getIdentification(): string;
}
