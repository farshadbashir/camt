<?php

declare(strict_types=1);

namespace Genkgo\Camt\DTO;

use Money\Money;

class ChargesRecord
{
    private Money $amount;

    private ?bool $chargesIncludedIndicator = null;

    private ?string $identification = null;

    private ?string $creditDebitIndicator = null;

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function setAmount(Money $money): void
    {
        $this->amount = $money;
    }

    public function getChargesIncludedIndicator(): ?bool
    {
        return $this->chargesIncludedIndicator;
    }

    public function setChargesIncludedIndicator(bool $chargesIncludedIndicator): void
    {
        $this->chargesIncludedIndicator = $chargesIncludedIndicator;
    }

    public function getIdentification(): ?string
    {
        return $this->identification;
    }

    public function setIdentification(string $identification): void
    {
        $this->identification = $identification;
    }

    public function getCreditDebitIndicator(): ?string
    {
        return $this->creditDebitIndicator;
    }

    public function setCreditDebitIndicator(?string $creditDebitIndicator): void
    {
        $this->creditDebitIndicator = $creditDebitIndicator;
    }
}
