# Fork changes in 2.10.3-p3

This document compares Farshad Bashir's fork with **upstream 2.10.3**, the
version from which the local modifications started. The [README](README.md)
provides a Dutch and English overview. Genkgo remains the upstream author;
the original [MIT license](LICENSE) is retained.

| Reference | Revision |
|---|---|
| Upstream baseline | [`56e047d1599854ca34db0ccabce15230fcdd3f16`](https://github.com/genkgo/camt/commit/56e047d1599854ca34db0ccabce15230fcdd3f16), tag `2.10.3` |
| Maintained parser | [`3ee652f0d7a89f8a6209acdca12d8e6fb1ead9f2`](https://github.com/farshadbashir/camt/commit/3ee652f0d7a89f8a6209acdca12d8e6fb1ead9f2), manifest version `2.10.3-p3` |
| Complete parser diff | [Compare baseline with p3](https://github.com/farshadbashir/camt/compare/56e047d1599854ca34db0ccabce15230fcdd3f16...3ee652f0d7a89f8a6209acdca12d8e6fb1ead9f2) |

The parser commits were made on 22 September 2026 and published to this fork
on 4 October 2026. Later documentation commits explain that parser revision without changing
its PHP source. The comparison is with the recorded baseline, not a claim about
the current upstream development branch.

## Why these changes were made

Bank imports need enough source information to explain a transaction and
reconcile an account. The changes address precision loss, omitted detail
groups, ambiguous metadata and XML handling found in the baseline. Missing
information stays missing where the new nullable APIs expose that distinction.
The parser leaves bookkeeping decisions and reconciliation to its caller.

## Detailed differences

### 1. Decimal amounts and debit signs

Upstream converted a debit amount to a PHP float before negating it. This
could lose decimal precision for large amounts. The fork parses the decimal
text with the existing MoneyPHP parser and negates the resulting Money value.

Schema-valid amounts with one leading `+` and surrounding XML whitespace are
accepted. Invalid forms such as `++1.235` or `+1e3` remain rejected with XSD
validation enabled. Existing currency-subunit rounding is retained: nearest
minor unit, with halves away from zero. Exchange rates and bookkeeping VAT
rounding are outside this change.

### 2. Repeated detail groups and directions

The baseline read details through a single `NtryDtls` path. The fork reads
every repeated `NtryDtls` group, then every `TxDtls`, in XML source order.
Each detail exposes its group index. An explicit detail `CdtDbtInd` determines
the detail direction and amount sign; when absent, the parent entry direction
continues to apply.

The parent `Ntry/Amt` remains the bank entry amount. Details and charges are
kept as source evidence; the parser does not add them to that amount or
automatically resolve differences.

### 3. ISO status versus proprietary bank status

`Entry::getStatusType()` distinguishes `code` from `proprietary` while
`getStatus()` retains the value. A proprietary value spelled `BOOK` can
therefore be distinguished from the ISO booked status. A proprietary string
`0` is preserved instead of being lost to a truthiness check.

### 4. Batch references

The single batch-payment field could be overwritten by an individual payment
reference. It is replaced with ordered `Batch` objects exposed through
`Entry::getBatches()`.

Each Batch retains the group index, message ID, payment-information ID,
transaction count, optional total amount and optional debit/credit indicator.
Individual transaction references remain separate. An entry can contain
multiple batches, and absent batch facts are nullable.

### 5. Charge fields and XML booleans

The baseline checked the debit/credit field when reading charge inclusion.
The fork reads `ChrgInclInd` for that flag, separately from `CdtDbtInd`.
Missing inclusion, direction and charge identification remain `null`.
A charge amount without a direction does not establish a signed bank leg.

Boolean reading accepts the XML forms `true`, `false`, `1` and `0`, including
schema-permitted whitespace. The shared reader is used for reversal,
pagination and charge-inclusion indicators.

### 6. Account currency and return information

Accounts expose their optional `Acct/Ccy` through `getCurrencyCode()`,
independently of the currencies on individual amounts.

Return information is retained whenever `RtrInf` exists, including a return
without a reason. ISO `Cd` and proprietary `Prtry` reasons remain separate
nullable values. Every `AddtlInf` is preserved in an ordered list, including
duplicates, rather than collapsed into one string.

### 7. Optional statement creation time

For CAMT.053.001.08, an absent optional statement creation time remains
`null`. `RecordWithBalances::getCreatedOn()` is nullable. Creation dates that
the older schemas require remain protected by XSD validation.

### 8. Source dates, precision and timezones

Existing DateTimeImmutable getters remain available. Additional getters keep
the XML date choice and text, so a date-only `Dt` can be distinguished from a
midnight `DtTm`, and a time such as `00:00:00.0000001` retains its fraction.

| Object | Added source getters |
|---|---|
| Balance | `getDateKind()`, `getDateSource()` |
| Entry | `getBookingDateKind()`, `getBookingDateSource()`, `getValueDateKind()`, `getValueDateSource()` |
| Record | `getFromDateSource()`, `getToDateSource()` for `FrToDt` |

Kind is `date` or `datetime`. Source text retains all fractional digits,
trailing zeroes, `Z` or numeric offset spelling, and absence of a timezone.
It is the parsed XML text, not the original file bytes or character-reference
spelling. Creation, acceptance and birth dates receive no new source metadata.

Legacy constructors and setters leave source metadata `null`. Replacing a
date without providing metadata clears earlier metadata; removing an entry
date clears its metadata too. The existing date decoder and Money calculations
are unchanged by this p3 addition.

### 9. XML parsing and namespaces

Namespace recognition uses namespace URIs, so equivalent default and prefixed
namespaces work. Unqualified currency attributes are read separately.
Unsupported namespaces and foreign lookalike elements remain rejected by the
reader/schema boundary; namespaces and schema versions are not rewritten.

`Reader::readString()` uses `LIBXML_NONET`, plus `LIBXML_NO_XXE` where available.
It does not enable entity substitution, DTD loading, XInclude processing or
`PARSEHUGE`. DTD and XInclude documents are rejected. The previous libxml error
mode is restored after parsing. Guarded parse/schema errors omit input values,
and the detected message format is reset before a new read.

A DOM supplied by a caller may already have been parsed unsafely;
`readDom()` cannot undo earlier parsing. Callers still need bounded input,
XSD validation, safe errors and explicit allowed message formats.

## Compatibility and migration

This fork changes several public DTO APIs. Review consumers of these methods
before switching from upstream 2.10.3:

| Baseline API | Fork API / migration |
|---|---|
| `Entry::getBatchPaymentId()` / `setBatchPaymentId()` | Removed. Read `getBatches()` or add explicit `Batch` objects with `addBatch()`. Keep per-detail references separate. |
| `ReturnInformation::fromUnstructured()` | Removed. Use `ReturnInformation::__construct(?string $code, ?string $proprietary, array $additionalInformation)`. |
| `ReturnInformation::getAdditionalInformation(): string` | Returns an ordered `array` of strings. |
| `ReturnInformation::getCode(): string` | Returns `?string`; `getProprietary()` is a separate nullable reason. |
| `ChargesRecord::getChargesIncludedIndicator(): bool` | Returns `?bool`; handle unknown separately from false. |
| `ChargesRecord::getIdentification(): string` | Returns `?string`. |
| `RecordWithBalances::getCreatedOn(): DateTimeImmutable` | Returns `?DateTimeImmutable`. |
| Status value alone | Use `getStatusType()` with `getStatus()` when interpreting status. |
| Date object alone | Use the new kind/source getters when source precision or timezone presence matters. |

The supported schema files and the Composer dependency requirements are
unchanged. Shared decoder fixes also apply to supported CAMT.052 and .054
paths. CAMT.053.001.10 remains unsupported. The accounting application's
registered import formats are limited separately to .053.001.02/.03/.04/.08.

## Change history

| Version | Commit | Scope |
|---|---|---|
| `2.10.3-p1` | [`13dcab1`](https://github.com/farshadbashir/camt/commit/13dcab14e8dc173e1349327dba8aab5dd40a5d8b) | Decimal/debit precision, repeated groups, detail directions, booleans and charge flag, optional creation time, XML boundaries, namespaces, account currency and return metadata. |
| `2.10.3-p2` | [`d20896a`](https://github.com/farshadbashir/camt/commit/d20896ad36ec5878d3a8e75320a25d629579e444) | Explicit status kind, grouped batches and missing charge-field distinctions. |
| `2.10.3-p3` | [`3ee652f`](https://github.com/farshadbashir/camt/commit/3ee652f0d7a89f8a6209acdca12d8e6fb1ead9f2) | Source date kinds, exact date text, fractional precision, timezone spelling and stale-metadata protection. |

## Verification

The complete parser suite was repeated locally on **4 October 2026** before
publication: **272 tests / 2,013 assertions passed**, using the existing
accounting application's PHPUnit runner and locked runtime dependencies,
without Laravel bootstrap or database access. Earlier independent source
reviews and repeated parser runs were recorded on 22 September 2026.

Coverage includes amount precision and subunit rounding, multiple detail groups,
direction overrides, optional fields, batches, statuses, return data,
namespace equivalence, XML boundaries, source date precision and legacy setters.
Regression snapshots were checked against their XML; changes include deliberate
corrections where the upstream expected detail direction disagreed with it.

The upstream `phpunit.xml` selects only `test/Unit`. A full run must also include
`test/Util/MoneyFactoryTest.php` (six cases). With the fork's development
autoload and an appropriate PHPUnit runner available, the complete directory
can be selected explicitly:

```sh
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --no-progress --colors=never --do-not-cache-result test
```

The reported local results do not establish GitHub Actions success, coverage
of every real bank export, or release approval for an entire accounting system.

## Distribution

This repository publishes the maintenance source and regression tests for
`genkgo/camt` version `2.10.3-p3`. Select the exact maintained parser revision
listed above when integrating this fork. The README's upstream Packagist
command selects the original package.

A reproducible runtime source archive can be created from that revision:

```sh
git archive --format=zip --output=genkgo-camt-2.10.3-p3.zip PARSER_REVISION src assets composer.json LICENSE README.md FORK_NOTES.md
```

Replace `PARSER_REVISION` with the maintained parser commit in the reference
table. This exports runtime source and license/documentation files without
test fixtures or development tooling. Installing or changing a consuming
application's dependencies is a separate integration decision.

## Application and maintenance responsibilities

The application owns upload limits, authorization, administration isolation,
account matching, deduplication, replay protection, bookkeeping, reconciliation,
corrections and retention. It must explicitly register supported formats and
keep sensitive input out of public errors and logs.

The fork maintainer owns security monitoring, upstream review and future
releases. New parser changes need regression checks and an exact reviewed
revision. A source commit or parser test result alone does not approve changes
to the accounting application's dependency graph or financial behavior.
