<?php

declare(strict_types=1);

namespace Genkgo\TestCamt\Unit;

use Genkgo\Camt\Config;
use Genkgo\Camt\DTO\Entry;
use Genkgo\Camt\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SourceDistinctionsTest extends TestCase
{
    public function testProprietaryBookIsNotTheIsoBookedStatus(): void
    {
        foreach (['Cd' => 'code', 'Prtry' => 'proprietary'] as $tag => $kind) {
            $entry = $this->entry('<'.$tag.'>BOOK</'.$tag.'>');
            self::assertSame('BOOK', $entry->getStatus());
            self::assertSame($kind, $entry->getStatusType());
        }
        self::assertSame('0', $this->entry('<Prtry>0</Prtry>')->getStatus());
    }

    public function testBatchGroupsAndDetailReferencesRemainSeparateAndOrdered(): void
    {
        $groups = '<NtryDtls><Btch><MsgId>M1</MsgId><PmtInfId>BATCH-ONE</PmtInfId><NbOfTxs>1</NbOfTxs><TtlAmt Ccy="EUR">15.00</TtlAmt><CdtDbtInd>CRDT</CdtDbtInd></Btch>'
            .'<TxDtls><Refs><PmtInfId>DETAIL-ONE</PmtInfId></Refs><Amt Ccy="EUR">15.00</Amt><CdtDbtInd>CRDT</CdtDbtInd></TxDtls></NtryDtls>'
            .'<NtryDtls><TxDtls><Refs><PmtInfId>DETAIL-TWO</PmtInfId></Refs><Amt Ccy="EUR">5.00</Amt><CdtDbtInd>DBIT</CdtDbtInd></TxDtls></NtryDtls>'
            .'<NtryDtls><Btch><PmtInfId>BATCH-THREE</PmtInfId></Btch><TxDtls/></NtryDtls>';
        $entry = $this->entry(groups: $groups);
        self::assertCount(2, $entry->getBatches());
        self::assertSame('BATCH-ONE', $entry->getBatches()[0]->getPaymentInformationId());
        self::assertSame('M1', $entry->getBatches()[0]->getMessageId());
        self::assertSame(1, $entry->getBatches()[0]->getNumberOfTransactions());
        self::assertSame('1500', $entry->getBatches()[0]->getTotalAmount()->getAmount());
        self::assertSame('CRDT', $entry->getBatches()[0]->getCreditDebitIndicator());
        self::assertSame(0, $entry->getBatches()[0]->getGroupIndex());
        self::assertSame(2, $entry->getBatches()[1]->getGroupIndex());
        self::assertNull($entry->getBatches()[1]->getTotalAmount());
        self::assertNull($entry->getBatches()[1]->getCreditDebitIndicator());
        self::assertSame([0, 1, 2], array_map(fn ($detail) => $detail->getGroupIndex(), $entry->getTransactionDetails()));
        self::assertSame('DETAIL-ONE', $entry->getTransactionDetails()[0]->getReference()->getPaymentInformationId());
        self::assertSame('DETAIL-TWO', $entry->getTransactionDetails()[1]->getReference()->getPaymentInformationId());
    }

    #[DataProvider('costIndicators')]
    public function testMissingCostIndicatorsRemainUnknownAtBothLevels(string $xml, ?bool $included, ?string $direction, string $signed): void
    {
        $charge = '<Chrgs><Rcrd><Amt Ccy="EUR">1.00</Amt>'.$xml.'</Rcrd></Chrgs>';
        $entry = $this->entry(charges: $charge, groups: '<NtryDtls><TxDtls>'.$charge.'</TxDtls></NtryDtls>');
        foreach ([$entry->getCharges(), $entry->getTransactionDetail()->getCharges()] as $charges) {
            $record = $charges->getRecords()[0];
            self::assertSame($included, $record->getChargesIncludedIndicator());
            self::assertSame($direction, $record->getCreditDebitIndicator());
            self::assertSame($signed, $record->getAmount()->getAmount());
            self::assertNull($record->getIdentification());
        }
    }

    public static function costIndicators(): array
    {
        return [
            ['', null, null, '100'],
            ['<CdtDbtInd>DBIT</CdtDbtInd><ChrgInclInd>false</ChrgInclInd>', false, 'DBIT', '-100'],
            ['<CdtDbtInd>CRDT</CdtDbtInd><ChrgInclInd>true</ChrgInclInd>', true, 'CRDT', '100'],
        ];
    }

    private function entry(string $status = '<Cd>BOOK</Cd>', string $charges = '', string $groups = ''): Entry
    {
        $xml = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.08"><BkToCstmrStmt>'
            .'<GrpHdr><MsgId>DISTINCTIONS</MsgId><CreDtTm>2026-09-22T12:00:00Z</CreDtTm></GrpHdr><Stmt><Id>S1</Id>'
            .'<Acct><Id><Othr><Id>TEST-ACCOUNT</Id></Othr></Id><Ccy>EUR</Ccy></Acct>'
            .'<Bal><Tp><CdOrPrtry><Cd>OPBD</Cd></CdOrPrtry></Tp><Amt Ccy="EUR">0.00</Amt><CdtDbtInd>CRDT</CdtDbtInd><Dt><Dt>2026-09-22</Dt></Dt></Bal>'
            .'<Ntry><Amt Ccy="EUR">10.00</Amt><CdtDbtInd>CRDT</CdtDbtInd><Sts>'.$status.'</Sts><BkTxCd/>'.$charges.$groups.'</Ntry>'
            .'</Stmt></BkToCstmrStmt></Document>';

        return (new Reader(Config::getDefault()))->readString($xml)->getRecords()[0]->getEntries()[0];
    }
}
