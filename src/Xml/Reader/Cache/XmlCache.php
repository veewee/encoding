<?php
declare(strict_types=1);

namespace Soap\Encoding\Xml\Reader\Cache;

use VeeWee\Xml\Dom\Document;

final class XmlCache
{
    /**
     * @var array<string, Document>
     */
    private array $cache = [];

    /**
     * @param non-empty-string $part
     */
    public function read(string $part): Document
    {
        $key = $this->createKey($part);
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = Document::fromXmlString($part);
        }

        return $this->cache[$key];
    }

    public function store(\DOMElement $element): Document
    {
        $doc = Document::fromXmlNode($element);

        $this->cache[$this->createKey($doc->stringifyDocumentElement())] = $doc;

        return $doc;
    }

    public function stringify(\DOMElement $element): string
    {
        $doc = Document::fromXmlNode($element);
        $xml = $doc->stringifyDocumentElement();

        $this->cache[$this->createKey($xml)] = $doc;

        return $xml;
    }

    private function createKey(string $xml): string
    {
        return md5($xml);
    }
}
