<?php

declare(strict_types=1);

namespace Genkgo\TestCamt\Unit;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Genkgo\Camt\Config;
use Genkgo\Camt\DTO\Balance;
use Genkgo\Camt\DTO\Record;
use Genkgo\Camt\Reader;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SourceDatesTest extends TestCase
{
    #[DataProvider('dateChoices')]
    public function testDateChoiceRetainsItsKindAndExactSource(string $version, string $tag, string $source): void
    {
        $record = $this->read($this->document($version, $tag, $source));
        $balance = $record->getBalances()[0];
        $entry = $record->getEntries()[0];
        $kind = $tag === 'Dt' ? 'date' : 'datetime';
        self::assertSame($kind, $balance->getDateKind());
        self::assertSame($source, $balance->getDateSource());
        self::assertSame($kind, $entry->getBookingDateKind());
        self::assertSame($source, $entry->getBookingDateSource());
        self::assertSame($kind, $entry->getValueDateKind());
        self::assertSame($source, $entry->getValueDateSource());
        // Existing date and money projections retain their established meaning.
        self::assertEquals(new DateTimeImmutable($source), $balance->getDate());
        self::assertEquals(new DateTimeImmutable($source), $entry->getBookingDate());
        self::assertEquals(new DateTimeImmutable($source), $entry->getValueDate());
        self::assertSame('0', $balance->getAmount()->getAmount());
        self::assertSame('-1234', $entry->getAmount()->getAmount());
    }

    public static function dateChoices(): iterable
    {
        foreach (['02', '03', '04', '08'] as $version) {
            foreach ([
                ['Dt', '2026-09-22'],
                ['Dt', '2026-09-22Z'],
                ['Dt', '2026-09-22+02:00'],
                ['DtTm', '2026-09-22T00:00:00'],
                ['DtTm', '2026-09-22T00:00:00Z'],
                ['DtTm', '2026-09-22T00:00:00.000000000+02:00'],
                ['DtTm', '2026-09-22T00:00:00.0000001+02:00'],
                ['DtTm', '2026-09-22T23:59:59.123456789-03:30'],
                ['DtTm', '2026-09-22T12:34:56.12345678901234567890'],
                ['DtTm', '2026-09-22T12:34:56.100000000Z'],
            ] as [$tag, $source]) {
                yield $version.' '.$tag.' '.trim($source) => [$version, $tag, $source];
            }
        }
    }

    #[DataProvider('periodSources')]
    public function testStatementPeriodRetainsExactLexicalBounds(string $version, string $from, string $to): void
    {
        $record = $this->read($this->document($version, from: $from, to: $to));
        self::assertSame($from, $record->getFromDateSource());
        self::assertSame($to, $record->getToDateSource());
        self::assertEquals(new DateTimeImmutable($from), $record->getFromDate());
        self::assertEquals(new DateTimeImmutable($to), $record->getToDate());
    }

    public static function periodSources(): iterable
    {
        foreach (['02', '03', '04', '08'] as $version) {
            yield $version.' nonzero submicrosecond' => [$version, '2026-09-22T00:00:00.0000001+02:00', '2026-09-23T23:59:59.999999999+02:00'];
            yield $version.' explicit exact midnight' => [$version, '2026-09-22T00:00:00.000000000Z', '2026-09-23T00:00:00Z'];
            yield $version.' unknown timezone' => [$version, '2026-09-22T00:00:00', '2026-09-23T00:00:00.000000000'];
        }
    }

    public function testAbsentDatesAndPeriodRemainUnknown(): void
    {
        $xml = $this->document('08');
        $xml = preg_replace('~<(BookgDt|ValDt|FrToDt)>.*?</\1>~s', '', $xml);
        $record = $this->read($xml);
        $entry = $record->getEntries()[0];
        self::assertNull($entry->getBookingDate());
        self::assertNull($entry->getBookingDateKind());
        self::assertNull($entry->getBookingDateSource());
        self::assertNull($entry->getValueDate());
        self::assertNull($entry->getValueDateKind());
        self::assertNull($entry->getValueDateSource());
        self::assertNull($record->getFromDate());
        self::assertNull($record->getFromDateSource());
        self::assertNull($record->getToDate());
        self::assertNull($record->getToDateSource());
    }

    public function testLegacyDateSettersClearPriorSourceMetadataWithoutGuessing(): void
    {
        $record = $this->read($this->document('08', 'DtTm', '2026-09-22T00:00:00.0000001Z'));
        $entry = $record->getEntries()[0];
        $replacement = new DateTimeImmutable('2026-09-24T12:00:00Z');
        $entry->setBookingDate($replacement);
        $entry->setValueDate($replacement);
        $record->setFromDate($replacement);
        $record->setToDate($replacement);
        self::assertSame($replacement, $entry->getBookingDate());
        self::assertSame($replacement, $entry->getValueDate());
        self::assertSame($replacement, $record->getFromDate());
        self::assertSame($replacement, $record->getToDate());
        self::assertNull($entry->getBookingDateKind());
        self::assertNull($entry->getBookingDateSource());
        self::assertNull($entry->getValueDateKind());
        self::assertNull($entry->getValueDateSource());
        self::assertNull($record->getFromDateSource());
        self::assertNull($record->getToDateSource());
        $entry->setBookingDate(null);
        $entry->setValueDate(null);
        self::assertNull($entry->getBookingDate());
        self::assertNull($entry->getValueDate());
        self::assertNull($entry->getBookingDateSource());
        self::assertNull($entry->getValueDateSource());
    }

    public function testLegacyBalanceFactoriesDoNotInferSourceKindFromMidnight(): void
    {
        foreach (['opening', 'openingAvailable', 'closing', 'closingAvailable', 'forwardAvailable', 'information', 'interim', 'interimAvailable', 'expectedCredit'] as $factory) {
            $date = new DateTimeImmutable('2026-09-22T00:00:00Z');
            $balance = Balance::$factory(Money::EUR('1234'), $date);
            self::assertSame($date, $balance->getDate());
            self::assertNull($balance->getDateKind());
            self::assertNull($balance->getDateSource());
        }
    }

    public function testSourceWithoutTimezoneDoesNotAcquireThePhpDefaultTimezone(): void
    {
        $previous = date_default_timezone_get();
        try {
            date_default_timezone_set('Pacific/Auckland');
            $record = $this->read($this->document('08', 'DtTm', '2026-09-22T00:00:00.0000001'));
            self::assertSame('2026-09-22T00:00:00.0000001', $record->getBalances()[0]->getDateSource());
            self::assertSame('2026-09-22T00:00:00.0000001', $record->getEntries()[0]->getBookingDateSource());
        } finally {
            date_default_timezone_set($previous);
        }
    }

    #[DataProvider('sharedMessageFixtures')]
    public function testShared052And054DecoderPreservesEntryDateSource(string $fixture): void
    {
        $xml = file_get_contents(__DIR__.'/../data/'.$fixture);
        $source = '2026-09-22T00:00:00.000000123+02:00';
        $xml = preg_replace('~<BookgDt>.*?</BookgDt>~s', '<BookgDt><DtTm>'.$source.'</DtTm></BookgDt>', $xml, 1, $replacements);
        self::assertSame(1, $replacements);
        $entry = $this->read($xml)->getEntries()[0];
        self::assertSame('datetime', $entry->getBookingDateKind());
        self::assertSame($source, $entry->getBookingDateSource());
    }

    public static function sharedMessageFixtures(): array
    {
        return [['camt052.v2.xml'], ['camt054.v2.xml']];
    }

    #[DataProvider('sourceFixtures')]
    public function testEveryExistingFixtureMetadataMatchesTheXmlWithoutUsingDateObjects(string $fixture): void
    {
        $xml = file_get_contents($fixture);
        $message = (new Reader(Config::getDefault()))->readString($xml);
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($xml, LIBXML_NONET));
        $path = new DOMXPath($document);
        $path->registerNamespace('c', $document->documentElement->namespaceURI);
        $xmlRecords = $path->query('/*/*/c:Stmt | /*/*/c:Rpt | /*/*/c:Ntfctn');
        self::assertCount($xmlRecords->length, $message->getRecords());
        $choice = static function (DOMElement $parent, string $field) use ($path): array {
            $element = $path->query('./c:'.$field.'/*', $parent)->item(0);

            return $element === null ? [null, null] : [$element->localName === 'Dt' ? 'date' : 'datetime', $element->textContent];
        };
        foreach ($message->getRecords() as $index => $record) {
            $xmlRecord = $xmlRecords->item($index);
            self::assertSame($path->query('./c:FrToDt/c:FrDtTm', $xmlRecord)->item(0)?->textContent, $record->getFromDateSource());
            self::assertSame($path->query('./c:FrToDt/c:ToDtTm', $xmlRecord)->item(0)?->textContent, $record->getToDateSource());
            $xmlEntries = $path->query('./c:Ntry', $xmlRecord);
            self::assertCount($xmlEntries->length, $record->getEntries());
            foreach ($record->getEntries() as $entryIndex => $entry) {
                self::assertSame($choice($xmlEntries->item($entryIndex), 'BookgDt'), [$entry->getBookingDateKind(), $entry->getBookingDateSource()]);
                self::assertSame($choice($xmlEntries->item($entryIndex), 'ValDt'), [$entry->getValueDateKind(), $entry->getValueDateSource()]);
            }
            if (method_exists($record, 'getBalances')) {
                $xmlBalances = [];
                foreach ($path->query('./c:Bal', $xmlRecord) as $xmlBalance) {
                    if (in_array($path->evaluate('string(./c:Tp/c:CdOrPrtry/c:Cd)', $xmlBalance), ['OPBD', 'PRCD', 'OPAV', 'CLBD', 'CLAV', 'FWAV', 'INFO', 'ITAV', 'ITBD', 'XPCD'], true)) {
                        $xmlBalances[] = $xmlBalance;
                    }
                }
                self::assertCount(count($xmlBalances), $record->getBalances());
                foreach ($record->getBalances() as $balanceIndex => $balance) {
                    self::assertSame($choice($xmlBalances[$balanceIndex], 'Dt'), [$balance->getDateKind(), $balance->getDateSource()]);
                }
            }
        }
    }

    public static function sourceFixtures(): iterable
    {
        foreach (glob(__DIR__.'/../data/*.xml') as $file) {
            if (is_file(str_replace('.xml', '.json', $file))) {
                yield basename($file) => [$file];
            }
        }
    }

    private function read(string $xml): Record
    {
        return (new Reader(Config::getDefault()))->readString($xml)->getRecords()[0];
    }

    private function document(string $version, string $tag = 'Dt', string $source = '2026-09-22', string $from = '2026-09-22T00:00:00Z', string $to = '2026-09-23T00:00:00Z'): string
    {
        $status = $version === '08' ? '<Cd>BOOK</Cd>' : 'BOOK';
        $choice = '<'.$tag.'>'.$source.'</'.$tag.'>';

        return '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.'.$version.'"><BkToCstmrStmt>'
            .'<GrpHdr><MsgId>SOURCE-DATES</MsgId><CreDtTm>2026-09-22T12:00:00Z</CreDtTm></GrpHdr>'
            .'<Stmt><Id>S1</Id><CreDtTm>2026-09-22T12:00:00Z</CreDtTm><FrToDt><FrDtTm>'.$from.'</FrDtTm><ToDtTm>'.$to.'</ToDtTm></FrToDt>'
            .'<Acct><Id><Othr><Id>TEST-ACCOUNT</Id></Othr></Id><Ccy>EUR</Ccy></Acct>'
            .'<Bal><Tp><CdOrPrtry><Cd>CLBD</Cd></CdOrPrtry></Tp><Amt Ccy="EUR">0.00</Amt><CdtDbtInd>CRDT</CdtDbtInd><Dt>'.$choice.'</Dt></Bal>'
            .'<Ntry><Amt Ccy="EUR">12.34</Amt><CdtDbtInd>DBIT</CdtDbtInd><Sts>'.$status.'</Sts><BookgDt>'.$choice.'</BookgDt><ValDt>'.$choice.'</ValDt><BkTxCd/></Ntry>'
            .'</Stmt></BkToCstmrStmt></Document>';
    }
}
