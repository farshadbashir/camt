<?php

declare(strict_types=1);

namespace Genkgo\Camt\DTO;

use Money\Money;

final class Batch
{
    public function __construct(
        private int $groupIndex,
        private ?string $messageId,
        private ?string $paymentInformationId,
        private ?int $numberOfTransactions,
        private ?Money $totalAmount,
        private ?string $creditDebitIndicator,
    ) {
    }

    public function getGroupIndex(): int
    {
        return $this->groupIndex;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function getPaymentInformationId(): ?string
    {
        return $this->paymentInformationId;
    }

    public function getNumberOfTransactions(): ?int
    {
        return $this->numberOfTransactions;
    }

    public function getTotalAmount(): ?Money
    {
        return $this->totalAmount;
    }

    public function getCreditDebitIndicator(): ?string
    {
        return $this->creditDebitIndicator;
    }
}
