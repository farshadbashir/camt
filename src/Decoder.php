<?php

declare(strict_types=1);

namespace Genkgo\Camt;

use DOMDocument;
use Genkgo\Camt\DTO\Message;
use Genkgo\Camt\Exception\InvalidMessageException;
use SimpleXMLElement;

class Decoder implements DecoderInterface
{
    private SimpleXMLElement $document;

    private Decoder\Message $messageDecoder;

    /**
     * Path to the schema definition.
     */
    protected string $schemeDefinitionPath;

    public function __construct(Decoder\Message $messageDecoder, string $schemeDefinitionPath)
    {
        $this->messageDecoder = $messageDecoder;
        $this->schemeDefinitionPath = $schemeDefinitionPath;
    }

    private function validate(DOMDocument $document): void
    {
        $previous = libxml_use_internal_errors(true);
        try {
            libxml_clear_errors();
            $valid = $document->schemaValidate(dirname(__DIR__) . $this->schemeDefinitionPath);
            if (!$valid) {
                // Schema diagnostics can contain private values from the bank file.
                throw new InvalidMessageException('Provided XML is not valid according to the XSD');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function decode(DOMDocument $document, bool $xsdValidation = true): Message
    {
        if ($xsdValidation === true) {
            $this->validate($document);
        }

        $namespace = $document->documentElement?->namespaceURI ?? '';
        $document = simplexml_import_dom($document);
        if ($document === false) {
            throw new InvalidMessageException('Provided XML could not be parsed');
        }

        // Select by URI so equivalent namespace prefixes keep identical meaning.
        $this->document = $document->children($namespace);

        $message = new Message();
        $this->messageDecoder->addGroupHeader($message, $this->document);
        $this->messageDecoder->addRecords($message, $this->document);

        return $message;
    }
}
