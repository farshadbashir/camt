# Fork changes in 2.10.3-p2

This fork is based on genkgo/camt 2.10.3, commit
`56e047d1599854ca34db0ccabce15230fcdd3f16`. The upstream MIT license,
supported schema files and dependency requirements are retained.

## Import and XML changes

- Parse decimal amounts with the existing MoneyPHP parser before negating debits,
  avoiding floating-point precision loss. Preserve existing subunit rounding.
- Accept one leading plus and surrounding XML whitespace on schema-valid amounts.
- Read every transaction-detail group in source order and honor explicit detail
  debit/credit directions; use the parent direction when absent.
- Read XML booleans `true`, `false`, `1` and `0`, and use `ChrgInclInd` for
  charge inclusion separately from `CdtDbtInd`.
- Keep the optional .053.001.08 statement creation time nullable.
- Retain optional account currency separately from amount currencies.
- Preserve return-information presence, separate ISO/proprietary reasons and
  an ordered list of all additional information, including duplicates.
- Resolve elements by namespace URI, including prefixed namespaces.
- Restrict network access on the reader's own XML parsing path, reject DTD
  and XInclude, restore libxml error mode, and omit input values from guarded
  parse/schema errors. A caller-supplied DOM still needs safe prior parsing.

The parent entry amount remains the bank mutation amount. The application owns
upload limits, authorization, account matching, deduplication, bookkeeping and
reconciliation. Parser checks alone do not establish coverage of every bank
export. Return-information APIs and nullable creation times require migration
where used.

## Status, batches and charge metadata

- Distinguish ISO status codes from proprietary bank statuses, including `0`.
- Replace the single batch-payment field with ordered Batch objects, preserving
  group index, message/payment references, transaction count, optional amount
  and direction. Keep individual detail references separate.
- Keep absent charge inclusion, direction and identification nullable.

Consumers must migrate removed batch-payment getters/setters to `getBatches()`
and `addBatch()`, and handle unknown charge fields separately from false.

## Verification

The complete parser test directory includes `test/Unit` and `test/Util`.
With the fork's development autoload and an appropriate PHPUnit runner:

```sh
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --no-progress --colors=never --do-not-cache-result test
```

Recorded parser verification: 187 tests / 1,209 assertions passed.
