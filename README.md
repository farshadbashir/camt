# Genkgo.CAMT fork by Farshad Bashir

## Over deze fork

Deze repository bevat de door Farshad Bashir onderhouden fork van
[genkgo/camt](https://github.com/genkgo/camt), gebaseerd op versie **2.10.3**
(commit [`56e047d`](https://github.com/genkgo/camt/commit/56e047d1599854ca34db0ccabce15230fcdd3f16)).
De aangepaste parserversie is **2.10.3-p3**. De wijzigingen zijn gemaakt voor
betrouwbaar inlezen en aansluiten van bankafschriften in een boekhoudapplicatie.
De oorspronkelijke MIT-licentie blijft behouden.

Ten opzichte van dat uitgangspunt zijn de volgende onderdelen gewijzigd:

| Onderdeel | Aanpassing in deze fork |
|---|---|
| Bedragen | Debetbedragen worden zonder omzetting naar een floating-pointgetal verwerkt. Bestaande MoneyPHP-afronding blijft behouden; geldige bedragen met een voorloopplus of XML-witruimte worden ondersteund. |
| Transactiedetails | Alle herhaalde detailgroepen worden in bronvolgorde ingelezen. Een expliciete debet-/creditrichting op een detail wordt gerespecteerd. |
| Status en batches | ISO-status en eigen bankstatus blijven onderscheiden. Batchgegevens worden per groep bewaard, los van individuele betalingsreferenties. |
| Kosten en booleans | Kosteninclusie komt uit het juiste XML-veld. `true`, `false`, `1` en `0` worden gelezen; ontbrekende kosteninclusie, richting en identificatie blijven onbekend (`null`). |
| Rekeningvaluta en retouren | Rekeningvaluta blijft afzonderlijk beschikbaar. Retourgegevens behouden ISO-redenen, eigen bankredenen en alle aanvullende toelichtingen. |
| Datums en tijdstippen | Datum en datum/tijd blijven onderscheiden. De oorspronkelijke XML-tekst bewaart fractieprecisie en tijdzonevermelding, naast de bestaande datumobjecten. Een ontbrekend optioneel aanmaaktijdstip van een .053.001.08-afschrift blijft `null`. |
| XML-inlezing | Namespaceherkenning werkt ook met prefixes. DTD en XInclude worden geweigerd; de eigen XML-inleesroute gebruikt netwerkbeperking en algemene parse-/schemafouten. |

De volledige parsersuite is lokaal opnieuw gecontroleerd op **4 oktober 2026**:
**272 tests en 2.013 assertions geslaagd**. De ondersteunde schema's en
dependencyvereisten zijn ongewijzigd. Er zijn wel gewijzigde API's, onder andere
voor batches, retourgegevens en nullable velden.

Zie [FORK_NOTES.md](FORK_NOTES.md) voor de precieze verschillen, migratiepunten,
wijzigingscommits en testgrenzen. De
[volledige vergelijking](https://github.com/farshadbashir/camt/compare/56e047d1599854ca34db0ccabce15230fcdd3f16...3ee652f0d7a89f8a6209acdca12d8e6fb1ead9f2)
toont de drie parsercommits.

## About this fork

Farshad Bashir maintains this fork of [genkgo/camt](https://github.com/genkgo/camt)
from upstream **2.10.3**, commit `56e047d1599854ca34db0ccabce15230fcdd3f16`.
The modified parser version is **2.10.3-p3**, developed for reliable bank-statement
imports and reconciliation in an accounting application. The MIT license is retained.

Compared with that baseline, it removes floating-point debit conversion, reads
all detail groups in source order, honors explicit detail directions, separates
ISO and proprietary statuses, preserves grouped batch references, corrects charge
flags and XML booleans, retains account currency and return information, and
adds source date kinds and exact date text. An absent optional .053.001.08
statement creation time remains `null`. XML reading handles namespace prefixes,
rejects DTD/XInclude, restricts network access on its own parsing path, and uses
generic parse/schema errors.

The full parser suite passed locally on **4 October 2026**: **272 tests / 2,013
assertions**. Supported schemas and dependency requirements are unchanged. Batch,
return-information and nullable-field APIs require migration where used; read
[FORK_NOTES.md](FORK_NOTES.md) for the detailed comparison, compatibility notes,
commits and verification limits.

## Library overview

Library to read CAMT files. Currently only CAMT.052, CAMT.053 and CAMT.054 are supported.

### Supported Versions

#### Camt 052

|     Version     |     Supported      |
|:---------------:|:------------------:|
| camt.052.001.01 | :heavy_check_mark: |
| camt.052.001.02 | :heavy_check_mark: |
| camt.052.001.03 |                    |
| camt.052.001.04 | :heavy_check_mark: |
| camt.052.001.05 |                    |
| camt.052.001.06 | :heavy_check_mark: |
| camt.052.001.08 | :heavy_check_mark: |
| camt.052.001.10 |                    |
| camt.052.001.11 |                    |

#### Camt 053

|     Version     |     Supported      |
|:---------------:|:------------------:|
| camt.053.001.01 |                    |
| camt.053.001.02 | :heavy_check_mark: |
| camt.053.001.03 | :heavy_check_mark: |
| camt.053.001.04 | :heavy_check_mark: |
| camt.053.001.05 |                    |
| camt.053.001.06 |                    |
| camt.053.001.08 | :heavy_check_mark: |
| camt.053.001.10 |                    |
| camt.053.001.11 |                    |

#### Camt 054

|     Version     |     Supported      |
|:---------------:|:------------------:|
| camt.054.001.01 |                    |
| camt.054.001.02 | :heavy_check_mark: |
| camt.054.001.03 |                    |
| camt.054.001.04 | :heavy_check_mark: |
| camt.054.001.05 |                    |
| camt.054.001.06 |                    |
| camt.054.001.08 | :heavy_check_mark: |
| camt.054.001.10 |                    |
| camt.054.001.11 |                    |

### Installation

The upstream package is available through Composer/Packagist:

```sh
composer require genkgo/camt
```

To select this fork, pin its reviewed source revision or immutable runtime
artifact as described in [FORK_NOTES.md](FORK_NOTES.md#distribution).

## Getting Started

Read a CAMT file, and loop through its statements and entries.

```php
<?php
use Genkgo\Camt\Config;
use Genkgo\Camt\Reader;

$reader = new Reader(Config::getDefault());
$message = $reader->readFile('test/data/camt053.v2.minimal.xml');
$statements = $message->getRecords();
foreach ($statements as $statement) {
    $entries = $statement->getEntries();
}
```



### XSD validation
   
This library provides a XSD validation for each supported CAMT format. The validation is executed by default. But in some cases, you might want to disable it.

```php
<?php
use Genkgo\Camt\Config;
use Genkgo\Camt\Reader;

$config = Config::getDefault();
$config->disableXsdValidation();

$reader = new Reader($config);
```
   

## Contributing

- Found a bug? Please try to solve it yourself first and issue a pull request. If you are not able to fix it, at least
  give a clear description what goes wrong. We will have a look when there is time.
- Want to see a feature added, issue a pull request and see what happens. You could also file a bug of the missing
  feature and we can discuss how to implement it.


### Quality

To check that everything is as it should be, run:

```sh
composer check
```

To fix code style, run:

```sh
composer check
```

### How to release

1. Create an annotated tag
    1. `git tag -a 1.2.3`
    1. Tag subject must be the version number, eg: `1.2.3`
    1. Tag body must be a copy-paste of the changelog entries
1. Push tag with `git push --tags`, then GitHub Actions will create a GitHub release automatically
