<?php

declare(strict_types=1);

namespace Genkgo\TestCamt\Unit;

use Genkgo\Camt\Config;
use Genkgo\Camt\Exception\InvalidMessageException;
use Genkgo\Camt\Exception\ReaderException;
use Genkgo\Camt\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class XmlBoundaryTest extends TestCase
{
    private static function xml(): string
    {
        return file_get_contents(__DIR__.'/../data/camt053.v2.minimal.xml');
    }

    public static function forbiddenXml(): iterable
    {
        $xml = self::xml();
        foreach ([
            'external DTD' => '<!DOCTYPE Document SYSTEM "https://example.invalid/private.dtd">',
            'external entity' => '<!DOCTYPE Document [<!ENTITY private SYSTEM "file:///must-not-be-read">]>',
            'parameter entity' => '<!DOCTYPE Document [<!ENTITY % private SYSTEM "https://example.invalid/private.dtd">%private;]>',
            'internal entity' => '<!DOCTYPE Document [<!ENTITY private "private-bank-reference">]>',
        ] as $name => $dtd) {
            yield $name => [str_replace('<Document ', $dtd.'<Document ', $xml)];
        }
        $withReference = str_replace('CAMT053RIB000000000001', '&private;', str_replace('<Document ', '<!DOCTYPE Document [<!ENTITY private SYSTEM "file:///must-not-be-read">]><Document ', $xml));
        yield 'entity reference' => [$withReference];
        yield 'UTF-16 DTD' => [mb_convert_encoding(str_replace('encoding="UTF-8"', 'encoding="UTF-16"', $withReference), 'UTF-16', 'UTF-8')];
        yield 'XInclude' => [str_replace('</Document>', '<xi:include xmlns:xi="http://www.w3.org/2001/XInclude" href="https://example.invalid/private.xml"/></Document>', $xml)];
    }

    #[DataProvider('forbiddenXml')]
    public function testUnsafeXmlNeverRequestsAnExternalResource(string $xml): void
    {
        $requests = [];
        $previous = libxml_get_external_entity_loader();
        libxml_set_external_entity_loader(static function ($public, $system, $context) use (&$requests) {
            $requests[] = $system;
            return null;
        });
        try {
            try {
                (new Reader(Config::getDefault()))->readString($xml);
                self::fail('Unsafe XML must be rejected');
            } catch (ReaderException $e) {
                self::assertStringNotContainsString('private-bank-reference', $e->getMessage());
            }
            self::assertSame([], $requests);
        } finally {
            libxml_set_external_entity_loader($previous);
        }
    }

    public function testSchemaHintCannotReplaceTheFixedLocalSchema(): void
    {
        $requests = [];
        $schema = realpath(__DIR__.'/../../assets/camt.053.001.02.xsd');
        $previous = libxml_get_external_entity_loader();
        libxml_set_external_entity_loader(static function ($public, $system, $context) use (&$requests, $schema) {
            $requests[] = $system;
            return $system === $schema ? fopen($schema, 'rb') : null;
        });
        try {
            $xml = str_replace('<Document ', '<Document xsi:schemaLocation="urn:iso:std:iso:20022:tech:xsd:camt.053.001.02 https://example.invalid/untrusted.xsd" ', self::xml());
            $message = (new Reader(Config::getDefault()))->readString($xml);
            self::assertCount(1, $message->getRecords());
            self::assertSame([$schema], $requests);
            try {
                (new Reader(Config::getDefault()))->readString(str_replace('2015-03-10T18:43:50+00:00', 'private-invalid-date', $xml));
                self::fail('The trusted schema must still reject invalid data');
            } catch (InvalidMessageException $e) {
                self::assertStringNotContainsString('private-invalid-date', $e->getMessage());
            }
            self::assertSame([$schema, $schema], $requests);
        } finally {
            libxml_set_external_entity_loader($previous);
        }
    }

    public static function errorModes(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('errorModes')]
    public function testErrorModeRestoredOnEveryOutcome(bool $mode): void
    {
        $previous = libxml_use_internal_errors($mode);
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        });
        try {
            foreach ([self::xml(), '<Document><private-bank-reference></Document>', '', str_replace('2015-03-10T18:43:50+00:00', 'private-invalid-date', self::xml())] as $xml) {
                try {
                    (new Reader(Config::getDefault()))->readString($xml);
                    self::assertSame(self::xml(), $xml);
                } catch (ReaderException|InvalidMessageException $e) {
                    self::assertStringNotContainsString('private-', $e->getMessage());
                }
                self::assertSame($mode, libxml_use_internal_errors());
            }
            self::assertSame([], $warnings);
        } finally {
            restore_error_handler();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function testNativeDepthLimitRemainsEnabled(): void
    {
        $this->expectException(ReaderException::class);
        (new Reader(Config::getDefault()))->readString(str_repeat('<x>', 300).str_repeat('</x>', 300));
    }
}
