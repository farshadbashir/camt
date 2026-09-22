<?php

declare(strict_types=1);

namespace Genkgo\TestCamt\Unit;

use Genkgo\Camt\Config;
use Genkgo\Camt\Exception\InvalidMessageException;
use Genkgo\Camt\Exception\ReaderException;
use Genkgo\Camt\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SourceMetadataTest extends TestCase
{
    private const NS = 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.08';

    private function read(string $xml): \Genkgo\Camt\DTO\Message
    {
        return (new Reader(Config::getDefault()))->readString($xml);
    }

    public function testAccountCurrencyIsPreservedWithoutInferringItFromEntries(): void
    {
        foreach (['<Othr><Id>TEST-ACCOUNT</Id></Othr>', '<IBAN>NL91ABNA0417164300</IBAN>'] as $identity) {
            foreach (['USD', null] as $currency) {
                $xml = self::document('', $identity, $currency);
                $record = $this->read($xml)->getRecords()[0];
                self::assertSame($currency, $record->getAccount()->getCurrencyCode());
                self::assertSame('EUR', $record->getEntries()[0]->getAmount()->getCurrency()->getCode());
            }
        }
    }

    #[DataProvider('returnInformation')]
    public function testReturnInformationRemainsDistinctAndComplete(string $xml, ?string $code, ?string $proprietary, array $additional): void
    {
        $detail = $this->read(self::document($xml))->getRecords()[0]->getEntries()[0]->getTransactionDetail();
        $return = $detail->getReturnInformation();
        self::assertNotNull($return);
        self::assertSame($code, $return->getCode());
        self::assertSame($proprietary, $return->getProprietary());
        self::assertSame($additional, $return->getAdditionalInformation());
    }

    public static function returnInformation(): array
    {
        return [
            ['<RtrInf><Rsn><Cd>AC01</Cd></Rsn><AddtlInf>First</AddtlInf><AddtlInf>Second</AddtlInf></RtrInf>', 'AC01', null, ['First', 'Second']],
            ['<RtrInf><Rsn><Prtry>BANK-RETURN</Prtry></Rsn><AddtlInf>One</AddtlInf><AddtlInf>One</AddtlInf></RtrInf>', null, 'BANK-RETURN', ['One', 'One']],
            ['<RtrInf><AddtlInf>Reason not supplied</AddtlInf></RtrInf>', null, null, ['Reason not supplied']],
            ['<RtrInf/>', null, null, []],
        ];
    }

    public function testAbsentReturnInformationStaysAbsent(): void
    {
        self::assertNull($this->read(self::document())->getRecords()[0]->getEntries()[0]->getTransactionDetail()->getReturnInformation());
    }

    #[DataProvider('returnInformation')]
    public function testOlderReturnInformationRemainsDistinctAndComplete(string $returnXml, ?string $code, ?string $proprietary, array $additional): void
    {
        $xml = file_get_contents(__DIR__.'/../data/camt053.v2.minimal.xml');
        $xml = str_replace('</TxDtls>', $returnXml.'</TxDtls>', $xml);
        $return = $this->read($xml)->getRecords()[0]->getEntries()[0]->getTransactionDetail()->getReturnInformation();
        self::assertNotNull($return);
        self::assertSame($code, $return->getCode());
        self::assertSame($proprietary, $return->getProprietary());
        self::assertSame($additional, $return->getAdditionalInformation());
    }

    #[DataProvider('namespaceFixtures')]
    public function testNamespacePrefixesDoNotChangeAnyDecodedValue(string $file): void
    {
        $xml = file_get_contents(__DIR__.'/../data/'.$file);
        $dumper = new Dumper();
        $expected = $dumper->dump($this->read($xml));
        foreach ([false, true] as $mixed) {
            self::assertSame($expected, $dumper->dump($this->read(self::prefix($xml, $mixed))));
        }
    }

    public static function namespaceFixtures(): array
    {
        return array_map(static fn (string $file): array => [$file], [
            'camt053.v2.minimal.xml', 'camt053.v3.xml', 'camt053.v4.xml', 'camt053.v8.xml',
            'camt052.v4.xml', 'camt054.v4.xml', 'camt054.v8.xml',
        ]);
    }

    public function testPrefixedMetadataAndUnqualifiedCurrencyAttributesRemainReadable(): void
    {
        $xml = self::prefix(self::document('<RtrInf><Rsn><Prtry>LOCAL</Prtry></Rsn><AddtlInf>First</AddtlInf><AddtlInf>Second</AddtlInf></RtrInf>'), true);
        $record = $this->read($xml)->getRecords()[0];
        self::assertSame('EUR', $record->getAccount()->getCurrencyCode());
        $detail = $record->getEntries()[0]->getTransactionDetail();
        self::assertSame('EUR', $detail->getAmount()->getCurrency()->getCode());
        self::assertSame('LOCAL', $detail->getReturnInformation()->getProprietary());
        self::assertSame(['First', 'Second'], $detail->getReturnInformation()->getAdditionalInformation());
    }

    public function testForeignNamespaceWithSameElementNameIsRejectedBySchema(): void
    {
        $xml = str_replace('<Ccy>EUR</Ccy>', '<x:Ccy xmlns:x="urn:foreign">EUR</x:Ccy>', self::document());
        $this->expectException(InvalidMessageException::class);
        $this->read($xml);
    }

    public function testUnknownPrefixedVersionIsNotRewrittenToSupportedVersion(): void
    {
        $this->expectException(ReaderException::class);
        $this->read(str_replace(self::NS, 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.10', self::prefix(self::document(), false)));
    }

    private static function prefix(string $xml, bool $mixed): string
    {
        $xml = preg_replace('/xmlns="([^"]+)"/', 'xmlns:c="$1" xmlns:d="$1" xmlns="urn:unrelated-default"', $xml, 1);
        return preg_replace_callback('/(<\/?)([A-Za-z][\w.-]*)(?=[\s>\/])/', static function (array $match) use ($mixed): string {
            $prefix = $mixed && strlen($match[2]) % 2 === 0 ? 'd' : 'c';
            return $match[1].$prefix.':'.$match[2];
        }, $xml);
    }

    private static function document(string $return = '', string $identity = '<Othr><Id>TEST-ACCOUNT</Id></Othr>', ?string $currency = 'EUR'): string
    {
        $ccy = $currency === null ? '' : '<Ccy>'.$currency.'</Ccy>';
        return '<?xml version="1.0"?><Document xmlns="'.self::NS.'"><BkToCstmrStmt><GrpHdr><MsgId>METADATA</MsgId><CreDtTm>2026-09-22T12:00:00Z</CreDtTm></GrpHdr><Stmt><Id>S1</Id><Acct><Id>'.$identity.'</Id>'.$ccy.'</Acct><Bal><Tp><CdOrPrtry><Cd>OPBD</Cd></CdOrPrtry></Tp><Amt Ccy="EUR">0.00</Amt><CdtDbtInd>CRDT</CdtDbtInd><Dt><Dt>2026-09-22</Dt></Dt></Bal><Ntry><NtryRef>E1</NtryRef><Amt Ccy="EUR">10.00</Amt><CdtDbtInd>DBIT</CdtDbtInd><Sts><Cd>BOOK</Cd></Sts><BkTxCd/><NtryDtls><TxDtls><Amt Ccy="EUR">10.00</Amt><CdtDbtInd>DBIT</CdtDbtInd>'.$return.'</TxDtls></NtryDtls></Ntry></Stmt></BkToCstmrStmt></Document>';
    }
}
