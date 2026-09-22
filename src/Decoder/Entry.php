<?php

declare(strict_types=1);

namespace Genkgo\Camt\Decoder;

use Genkgo\Camt\DTO;
use Genkgo\Camt\Util\MoneyFactory;
use SimpleXMLElement;

class Entry
{
    private EntryTransactionDetail $entryTransactionDetailDecoder;

    public function __construct(EntryTransactionDetail $entryTransactionDetailDecoder)
    {
        $this->entryTransactionDetailDecoder = $entryTransactionDetailDecoder;
    }

    public function addTransactionDetails(DTO\Entry $entry, SimpleXMLElement $xmlEntry): void
    {
        $groupIndex = 0;
        foreach ($xmlEntry->NtryDtls as $xmlGroup) {
            if (isset($xmlGroup->Btch)) {
                $batch = $xmlGroup->Btch;
                $entry->addBatch(new DTO\Batch(
                    $groupIndex,
                    isset($batch->MsgId) ? (string) $batch->MsgId : null,
                    isset($batch->PmtInfId) ? (string) $batch->PmtInfId : null,
                    isset($batch->NbOfTxs) ? (int) (string) $batch->NbOfTxs : null,
                    isset($batch->TtlAmt) ? (new MoneyFactory())->create($batch->TtlAmt, $batch->CdtDbtInd) : null,
                    isset($batch->CdtDbtInd) ? (string) $batch->CdtDbtInd : null,
                ));
            }
            foreach ($xmlGroup->TxDtls as $xmlDetail) {
                $direction = isset($xmlDetail->CdtDbtInd) ? $xmlDetail->CdtDbtInd : $xmlEntry->CdtDbtInd;
                $detail = new DTO\EntryTransactionDetail();
                $detail->setGroupIndex($groupIndex);
                $this->entryTransactionDetailDecoder->addCreditDebitIdentifier($detail, $direction);
                $this->entryTransactionDetailDecoder->addReference($detail, $xmlDetail);
                $this->entryTransactionDetailDecoder->addRelatedParties($detail, $xmlDetail);
                $this->entryTransactionDetailDecoder->addRelatedAgents($detail, $xmlDetail);
                $this->entryTransactionDetailDecoder->addRemittanceInformation($detail, $xmlDetail);
                $this->entryTransactionDetailDecoder->addRelatedDates($detail, $xmlDetail);
                $this->entryTransactionDetailDecoder->addReturnInformation($detail, $xmlDetail);
                $this->entryTransactionDetailDecoder->addAdditionalTransactionInformation($detail, $xmlDetail);
                $this->entryTransactionDetailDecoder->addBankTransactionCode($detail, $xmlDetail);
                $this->entryTransactionDetailDecoder->addCharges($detail, $xmlDetail);
                $this->entryTransactionDetailDecoder->addAmountDetails($detail, $xmlDetail, $direction);
                $this->entryTransactionDetailDecoder->addAmount($detail, $xmlDetail, $direction);

                $entry->addTransactionDetail($detail);
            }
            ++$groupIndex;
        }
    }
}
