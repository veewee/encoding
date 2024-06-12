<?php
declare(strict_types=1);

namespace Soap\Encoding\Xml\Reader;

use Soap\Encoding\Xml\Reader\Cache\XmlCache;
use function VeeWee\Xml\Dom\Locator\Attribute\attributes_list;
use function VeeWee\Xml\Dom\Locator\Element\children as readChildElements;

final class DocumentToLookupArrayReader
{
    /**
     * @param non-empty-string $xml
     * @return array<string, string>
     */
    public function __invoke(string $xml, XmlCache $xmlCache): array
    {
        $doc = $xmlCache->read($xml);
        $root = $doc->locateDocumentElement();
        $nodes = [];

        // Read all child elements.
        // The key is the name of the elements
        // The value is the raw XML for those element(s)
        $elements = readChildElements($root);
        foreach ($elements as $element) {
            $key = $element->localName ?? 'unknown';
            $nodeValue = $xmlCache->stringify($element);
            // For list-nodes, a concatenated string of the xml nodes will be generated.
            $value = array_key_exists($key, $nodes) ? $nodes[$key].$nodeValue : $nodeValue;
            $nodes[$key] = $value;
        }

        // It might be possible that the child is a regular textNode.
        // In that case, we use '_' as the key and the value of the textNode as value.
        $content = trim($root->textContent);
        if (!$elements->count() && $content) {
            $nodes['_'] = $content;
        }

        // All attributes also need to be added as key => value pairs.
        foreach (attributes_list($root) as $attribute) {
            $key = $attribute->localName ?? 'unkown';
            $nodes[$key] = $attribute->value;
        }

        return $nodes;
    }
}
