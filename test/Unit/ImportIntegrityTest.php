<?php

declare(strict_types=1);

namespace Genkgo\TestCamt\Unit;

use DOMDocument;
use Genkgo\Camt\Config;
use Genkgo\Camt\DTO\Entry;
use Genkgo\Camt\Exception\ReaderException;
use Genkgo\Camt\Exception\InvalidMessageException;
use Genkgo\Camt\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImportIntegrityTest extends TestCase
{
    private const NS = 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.08';

    private function read(string $xml): \Genkgo\Camt\DTO\Message
    {
        return (new Reader(Config::getDefault()))->readString($xml);
    }

    private function first(string $entry): Entry
    {
        return $this->read(self::document($entry))->getRecords()[0]->getEntries()[0];
    }

    public function testLargeDebitRemainsExact(): void
    {
        self::assertSame('-123456789012345', $this->first(self::entry('1234567890123.45', 'DBIT'))->getAmount()->getAmount());
        self::assertSame('123456789012345', $this->first(self::entry('1234567890123.45'))->getAmount()->getAmount());
        self::assertSame('0', $this->first(self::entry('0.00', 'DBIT'))->getAmount()->getAmount());
    }

    #[DataProvider('roundedAmounts')]
    public function testCurrencyRoundingIsExactAndSignSymmetric(string $currency, string $input, string $minor): void
    {
        foreach (['CRDT', 'DBIT'] as $direction) {
            $xml = str_replace('Ccy="EUR"', 'Ccy="'.$currency.'"', self::document(self::entry($input, $direction)));
            $entry = $this->read($xml)->getRecords()[0]->getEntries()[0];
            self::assertSame($direction === 'DBIT' && $minor !== '0' ? '-'.$minor : $minor, $entry->getAmount()->getAmount());
            self::assertSame($currency, $entry->getAmount()->getCurrency()->getCode());
            self::assertSame($direction, $entry->getCreditDebitIndicator());
        }
    }

    public static function roundedAmounts(): array
    {
        return [
            ['EUR', '+8.855', '886'], ['EUR', '  +1.23500  ', '124'],
            ['EUR', '+0', '0'], ['EUR', '+0.00001', '0'],
            ['EUR', '1.23456', '123'], ['EUR', '1.23500', '124'],
            ['EUR', '1.00499', '100'], ['EUR', '1.00500', '101'], ['EUR', '1.00501', '101'],
            ['EUR', '9.99999', '1000'], ['EUR', '1.23000', '123'],
            ['EUR', '0.00000', '0'], ['EUR', '0.00001', '0'],
            ['EUR', '1234567890123.455', '123456789012346'],
            ['JPY', '1.49999', '1'], ['JPY', '1.50000', '2'], ['JPY', '0.00001', '0'],
            ['KWD', '1.23449', '1234'], ['KWD', '1.23450', '1235'], ['KWD', '1.23451', '1235'],
        ];
    }

    public function testRoundingNeverReplacesEntryWithDetailSum(): void
    {
        $entry = $this->first(self::entry('0.01', 'CRDT', 'false', '<NtryDtls>'.self::detail('A', '0.005').self::detail('B', '0.005').'</NtryDtls>'));
        self::assertSame('1', $entry->getAmount()->getAmount());
        self::assertSame(['1', '1'], array_map(static fn ($d) => $d->getAmount()->getAmount(), $entry->getTransactionDetails()));
    }

    #[DataProvider('invalidAmounts')]
    public function testInvalidAmountNotationIsStillRejectedByXsd(string $amount): void
    {
        $this->expectException(InvalidMessageException::class);
        $this->first(self::entry($amount));
    }

    public static function invalidAmounts(): array
    {
        return [['++1.235'], ['+'], ['+1e3']];
    }

    public function testRoundingToZeroDoesNotDropTheEntry(): void
    {
        $entries = $this->read(self::document(self::entry('0.00001', 'DBIT')))->getRecords()[0]->getEntries();
        self::assertCount(1, $entries);
        self::assertSame('0', $entries[0]->getAmount()->getAmount());
        self::assertSame('DBIT', $entries[0]->getCreditDebitIndicator());
    }

    public function testEveryDetailGroupIsReadOnceInSourceOrder(): void
    {
        $entry = $this->first(self::entry('10.00', 'CRDT', 'false', '<NtryDtls>'.self::detail('A', '6.00').'</NtryDtls><NtryDtls>'.self::detail('B', '4.00').'</NtryDtls>'));
        self::assertSame('1000', $entry->getAmount()->getAmount());
        self::assertCount(2, $entry->getTransactionDetails());
        self::assertSame(['600', '400'], array_map(static fn ($d) => $d->getAmount()->getAmount(), $entry->getTransactionDetails()));
        self::assertSame(['A', 'B'], array_map(static fn ($d) => $d->getReference()->getTransactionId(), $entry->getTransactionDetails()));
    }

    public function testMixedDirectionBatchPreservesDetailDirectionAndAmount(): void
    {
        $entry = $this->first(self::entry('10.00', 'CRDT', 'false', '<NtryDtls>'.self::detail('A', '15.00').self::detail('B', '5.00', 'DBIT').'</NtryDtls>'));
        self::assertSame('1000', $entry->getAmount()->getAmount());
        self::assertSame(['1500', '-500'], array_map(static fn ($d) => $d->getAmount()->getAmount(), $entry->getTransactionDetails()));
        self::assertSame(['CRDT', 'DBIT'], array_map(static fn ($d) => $d->getCreditDebitIndicator(), $entry->getTransactionDetails()));
    }

    public function testMissingDetailDirectionUsesEntryDirection(): void
    {
        $detail = str_replace('<CdtDbtInd>CRDT</CdtDbtInd>', '', self::detail('A', '10.00'));
        $entry = $this->first(self::entry('10.00', 'DBIT', 'false', '<NtryDtls>'.$detail.'</NtryDtls>'));
        self::assertSame('-1000', $entry->getTransactionDetail()->getAmount()->getAmount());
        self::assertSame('DBIT', $entry->getTransactionDetail()->getCreditDebitIndicator());
    }

    public function testDetailAmountDetailsKeepOwnDirectionAndCurrency(): void
    {
        $detail = str_replace('</TxDtls>', '<AmtDtls><TxAmt><Amt Ccy="USD">5.005</Amt></TxAmt></AmtDtls></TxDtls>', self::detail('A', '4.00', 'DBIT'));
        $entry = $this->first(self::entry('4.00', 'CRDT', 'false', '<NtryDtls>'.$detail.'</NtryDtls>'));
        self::assertSame('400', $entry->getAmount()->getAmount());
        self::assertSame('-400', $entry->getTransactionDetail()->getAmount()->getAmount());
        self::assertSame('-501', $entry->getTransactionDetail()->getAmountDetails()->getAmount());
        self::assertSame('USD', $entry->getTransactionDetail()->getAmountDetails()->getCurrency()->getCode());
    }

    #[DataProvider('booleans')]
    public function testXmlBooleanSemantics(string $value, bool $expected): void
    {
        $message = $this->read(self::document(self::entry('10.00', 'CRDT', $value), $value));
        self::assertSame($expected, $message->getRecords()[0]->getEntries()[0]->getReversalIndicator());
        self::assertSame($expected, $message->getRecords()[0]->getPagination()->isLastPage());
    }

    #[DataProvider('booleans')]
    public function testSharedPaginationBooleans(string $value, bool $expected): void
    {
        foreach (['camt052.v4.xml', 'camt054.v4.xml', 'camt054.v8.xml'] as $file) {
            $xml = str_replace('<LastPgInd>true</LastPgInd>', '<LastPgInd>'.$value.'</LastPgInd>', file_get_contents(__DIR__.'/../data/'.$file));
            $message = $this->read($xml);
            self::assertSame($expected, $message->getGroupHeader()->getPagination()->isLastPage(), $file);
            self::assertSame($expected, $message->getRecords()[0]->getPagination()->isLastPage(), $file);
        }
    }

    public static function booleans(): array
    {
        return [['true', true], ['1', true], ['false', false], ['0', false], ['  true  ', true], ['  1  ', true]];
    }

    #[DataProvider('booleans')]
    public function testIncludedChargesAtBothLevels(string $value, bool $expected): void
    {
        $charges = '<Chrgs><Rcrd><Amt Ccy="EUR">1.00</Amt><CdtDbtInd>DBIT</CdtDbtInd><ChrgInclInd>'.$value.'</ChrgInclInd></Rcrd></Chrgs>';
        $detail = str_replace('</TxDtls>', $charges.'</TxDtls>', self::detail('A', '10.00'));
        $entry = $this->first(self::entry('10.00', 'CRDT', 'false', $charges.'<NtryDtls>'.$detail.'</NtryDtls>'));
        self::assertSame($expected, $entry->getCharges()->getRecords()[0]->getChargesIncludedIndicator());
        self::assertSame($expected, $entry->getTransactionDetail()->getCharges()->getRecords()[0]->getChargesIncludedIndicator());
        self::assertSame('-100', $entry->getCharges()->getRecords()[0]->getAmount()->getAmount());
    }

    public function testMissingOptionalStatementCreationDateStaysUnknown(): void
    {
        $xml = str_replace('<CreDtTm>2026-09-22T13:00:00Z</CreDtTm>', '', self::document(self::entry()));
        self::assertNull($this->read($xml)->getRecords()[0]->getCreatedOn());
    }

    public function testOlderSchemaStillRequiresCreationDate(): void
    {
        $xml = file_get_contents(__DIR__.'/../data/camt053.v2.minimal.xml');
        $xml = preg_replace('/(<Stmt>.*?)<CreDtTm>[^<]*<\/CreDtTm>/s', '$1', $xml);
        self::assertSame(1, substr_count($xml, '<CreDtTm>'));
        $this->expectException(InvalidMessageException::class);
        $this->read($xml);
    }

    public function testMissingChargesIncludedRemainsFalse(): void
    {
        $charges = '<Chrgs><Rcrd><Amt Ccy="EUR">0.005</Amt><CdtDbtInd>DBIT</CdtDbtInd></Rcrd></Chrgs>';
        $detail = str_replace('</TxDtls>', $charges.'</TxDtls>', self::detail('A', '10.00'));
        $entry = $this->first(self::entry('10.00', 'CRDT', 'false', $charges.'<NtryDtls>'.$detail.'</NtryDtls>'));
        foreach ([$entry->getCharges(), $entry->getTransactionDetail()->getCharges()] as $parsed) {
            self::assertFalse($parsed->getRecords()[0]->getChargesIncludedIndicator());
            self::assertSame('-1', $parsed->getRecords()[0]->getAmount()->getAmount());
        }
    }

    public function testInvalidBooleanIsNotSilentlyAccepted(): void
    {
        $this->expectException(InvalidMessageException::class);
        $this->read(self::document(self::entry('10.00', 'CRDT', 'yes')));
    }

    public function testReaderRestoresLibxmlErrorMode(): void
    {
        $previous = libxml_use_internal_errors(false);
        try {
            $this->read(self::document(self::entry()));
            self::assertFalse(libxml_use_internal_errors());
        } finally {
            libxml_use_internal_errors($previous);
            libxml_clear_errors();
        }
    }

    public function testDoctypeIsRejectedEvenWithoutEntityUse(): void
    {
        $this->expectException(ReaderException::class);
        $this->read(str_replace('?><Document', '?><!DOCTYPE Document SYSTEM "https://example.invalid/a.dtd"><Document', self::document(self::entry())));
    }

    public function testDoctypeAlsoRejectedWhenPassingDom(): void
    {
        $dom = new DOMDocument();
        $dom->loadXML(str_replace('?><Document', '?><!DOCTYPE Document><Document', self::document(self::entry())), LIBXML_NONET);
        $this->expectException(ReaderException::class);
        (new Reader(Config::getDefault()))->readDom($dom);
    }

    public function testMalformedXmlFailsWithoutLeakingXmlInWarnings(): void
    {
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        });
        try {
            try {
                $this->read('<Document><private-bank-reference></Document>');
                self::fail('Malformed input must fail');
            } catch (ReaderException $e) {
                self::assertStringNotContainsString('private-bank-reference', $e->getMessage());
            }
            self::assertSame([], $warnings);
        } finally {
            restore_error_handler();
        }
    }

    private static function detail(string $id, string $amount, string $direction = 'CRDT'): string
    {
        return '<TxDtls><Refs><TxId>'.$id.'</TxId></Refs><Amt Ccy="EUR">'.$amount.'</Amt><CdtDbtInd>'.$direction.'</CdtDbtInd></TxDtls>';
    }

    private static function entry(string $amount = '10.00', string $direction = 'CRDT', string $reversal = 'false', string $body = ''): string
    {
        return '<Ntry><NtryRef>ENTRY-1</NtryRef><Amt Ccy="EUR">'.$amount.'</Amt><CdtDbtInd>'.$direction.'</CdtDbtInd><RvslInd>'.$reversal.'</RvslInd><Sts><Cd>BOOK</Cd></Sts><BookgDt><Dt>2026-09-22</Dt></BookgDt><BkTxCd/>'.$body.'</Ntry>';
    }

    private static function document(string $entries, string $last = 'true'): string
    {
        return '<?xml version="1.0"?><Document xmlns="'.self::NS.'"><BkToCstmrStmt><GrpHdr><MsgId>FORK-REGRESSION</MsgId><CreDtTm>2026-09-22T12:00:00Z</CreDtTm></GrpHdr><Stmt><Id>STMT-1</Id><StmtPgntn><PgNb>1</PgNb><LastPgInd>'.$last.'</LastPgInd></StmtPgntn><CreDtTm>2026-09-22T13:00:00Z</CreDtTm><Acct><Id><Othr><Id>SYNTHETIC-ACCOUNT</Id></Othr></Id><Ccy>EUR</Ccy></Acct><Bal><Tp><CdOrPrtry><Cd>OPBD</Cd></CdOrPrtry></Tp><Amt Ccy="EUR">0.00</Amt><CdtDbtInd>CRDT</CdtDbtInd><Dt><Dt>2026-09-22</Dt></Dt></Bal>'.$entries.'</Stmt></BkToCstmrStmt></Document>';
    }
}
