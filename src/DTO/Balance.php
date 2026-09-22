<?php

declare(strict_types=1);

namespace Genkgo\Camt\DTO;

use DateTimeImmutable;
use Money\Money;

class Balance
{
    public const TYPE_OPENING = 'opening';

    public const TYPE_OPENING_AVAILABLE = 'opening_available';

    public const TYPE_CLOSING = 'closing';

    public const TYPE_CLOSING_AVAILABLE = 'closing_available';

    public const TYPE_FORWARD_AVAILABLE = 'forward_available';

    public const TYPE_INFORMATION = 'information';

    public const TYPE_INTERIM = 'interim';

    public const TYPE_INTERIM_AVAILABLE = 'interim_available';

    public const TYPE_EXPECTED_CREDIT = 'expected_credit';

    private Money $amount;

    private string $type;

    private DateTimeImmutable $date;

    private ?string $dateKind = null;

    private ?string $dateSource = null;

    private function __construct(string $type, Money $amount, DateTimeImmutable $date, ?string $dateKind = null, ?string $dateSource = null)
    {
        $this->type = $type;
        $this->amount = $amount;
        $this->date = $date;
        $this->dateKind = $dateKind;
        $this->dateSource = $dateSource;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    /** The XML choice: date (Dt), datetime (DtTm), or unknown for a legacy DTO. */
    public function getDateKind(): ?string
    {
        return $this->dateKind;
    }

    /** Original XML text, without inferred timezone or loss of fractional digits. */
    public function getDateSource(): ?string
    {
        return $this->dateSource;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public static function opening(Money $amount, DateTimeImmutable $date, ?string $dateKind = null, ?string $dateSource = null): self
    {
        return new self(self::TYPE_OPENING, $amount, $date, $dateKind, $dateSource);
    }

    public static function openingAvailable(Money $amount, DateTimeImmutable $date, ?string $dateKind = null, ?string $dateSource = null): self
    {
        return new self(self::TYPE_OPENING_AVAILABLE, $amount, $date, $dateKind, $dateSource);
    }

    public static function closing(Money $amount, DateTimeImmutable $date, ?string $dateKind = null, ?string $dateSource = null): self
    {
        return new self(self::TYPE_CLOSING, $amount, $date, $dateKind, $dateSource);
    }

    public static function closingAvailable(Money $amount, DateTimeImmutable $date, ?string $dateKind = null, ?string $dateSource = null): self
    {
        return new self(self::TYPE_CLOSING_AVAILABLE, $amount, $date, $dateKind, $dateSource);
    }

    public static function forwardAvailable(Money $amount, DateTimeImmutable $date, ?string $dateKind = null, ?string $dateSource = null): self
    {
        return new self(self::TYPE_FORWARD_AVAILABLE, $amount, $date, $dateKind, $dateSource);
    }

    public static function information(Money $amount, DateTimeImmutable $date, ?string $dateKind = null, ?string $dateSource = null): self
    {
        return new self(self::TYPE_INFORMATION, $amount, $date, $dateKind, $dateSource);
    }

    public static function interim(Money $amount, DateTimeImmutable $date, ?string $dateKind = null, ?string $dateSource = null): self
    {
        return new self(self::TYPE_INTERIM, $amount, $date, $dateKind, $dateSource);
    }

    public static function interimAvailable(Money $amount, DateTimeImmutable $date, ?string $dateKind = null, ?string $dateSource = null): self
    {
        return new self(self::TYPE_INTERIM_AVAILABLE, $amount, $date, $dateKind, $dateSource);
    }

    public static function expectedCredit(Money $amount, DateTimeImmutable $date, ?string $dateKind = null, ?string $dateSource = null): self
    {
        return new self(self::TYPE_EXPECTED_CREDIT, $amount, $date, $dateKind, $dateSource);
    }
}
