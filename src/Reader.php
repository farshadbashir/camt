<?php

declare(strict_types=1);

namespace Genkgo\Camt;

use DOMDocument;
use Genkgo\Camt\DTO\Message;
use Genkgo\Camt\Exception\ReaderException;

class Reader
{
    private Config $config;

    private ?MessageFormatInterface $messageFormat = null;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function readDom(DOMDocument $document): Message
    {
        $this->messageFormat = null;

        if ($document->documentElement === null) {
            throw new ReaderException('Empty document');
        }

        if ($document->doctype !== null) {
            throw new ReaderException('Document type declarations are not allowed');
        }

        if ($document->getElementsByTagNameNS('http://www.w3.org/2001/XInclude', '*')->length !== 0) {
            throw new ReaderException('XInclude is not allowed');
        }

        $xmlNs = $document->documentElement->namespaceURI ?? '';
        $this->messageFormat = $this->getMessageFormatForXmlNs($xmlNs);

        return $this->messageFormat->getDecoder()->decode($document, $this->config->getXsdValidation());
    }

    public function readString(string $string): Message
    {
        $this->messageFormat = null;
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            libxml_clear_errors();
            $options = LIBXML_NONET;
            if (defined('LIBXML_NO_XXE')) {
                $options |= LIBXML_NO_XXE;
            }
            if ($string === '' || !$dom->loadXML($string, $options)) {
                throw new ReaderException('Provided XML could not be parsed');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $this->readDom($dom);
    }

    public function readFile(string $file): Message
    {
        if (!file_exists($file)) {
            throw new ReaderException("{$file} does not exists");
        }

        $string = file_get_contents($file);
        if ($string === false) {
            throw new ReaderException("Could not read file {$file}");
        }

        return $this->readString($string);
    }

    private function getMessageFormatForXmlNs(string $xmlNs): MessageFormatInterface
    {
        $messageFormats = $this->config->getMessageFormats();
        foreach ($messageFormats as $messageFormat) {
            if ($messageFormat->getXmlNs() === $xmlNs) {
                return $messageFormat;
            }
        }

        throw new ReaderException('Unsupported document namespace');
    }

    public function getMessageFormat(): ?MessageFormatInterface
    {
        return $this->messageFormat;
    }
}
