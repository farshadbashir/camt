<?php

declare(strict_types=1);

namespace Genkgo\Camt\DTO;

use DateTimeImmutable;

abstract class RecordWithBalances extends Record
{
    protected ?DateTimeImmutable $createdOn;

    public function __construct(string $id, ?DateTimeImmutable $createdOn, Account $account)
    {
        parent::__construct($id, $account);

        $this->createdOn = $createdOn;
    }

    public function getCreatedOn(): ?DateTimeImmutable
    {
        return $this->createdOn;
    }

    /**
     * @var Balance[]
     */
    private array $balances = [];

    public function addBalance(Balance $balance): void
    {
        $this->balances[] = $balance;
    }

    /**
     * @return Balance[]
     */
    public function getBalances(): array
    {
        return $this->balances;
    }
}
