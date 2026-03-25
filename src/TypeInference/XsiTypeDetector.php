<?php
declare(strict_types=1);

namespace Soap\Encoding\TypeInference;

use DOMElement;
use Psl\Option\Option;
use Soap\Encoding\Encoder\Context;
use Soap\Encoding\Encoder\FixedIsoEncoder;
use Soap\Encoding\EncoderRegistry;
use Soap\Engine\Metadata\Model\XsdType;
use Soap\WsdlReader\Parser\Xml\QnameParser;
use Soap\Xml\Xmlns as SoapXmlns;
use VeeWee\Xml\Xmlns\Xmlns;
use WeakMap;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function Psl\Option\none;
use function Psl\Option\some;
use function sprintf;

final class XsiTypeDetector
{
    /** @var WeakMap<EncoderRegistry, array<string, \Soap\Encoding\Encoder\XmlEncoder<mixed, string>>> */
    private static WeakMap $encoderCache;

    private static function encoderCache(): WeakMap
    {
        /** @psalm-suppress RedundantPropertyInitializationCheck */
        return self::$encoderCache ??= new WeakMap();
    }
    /**
     * @psalm-param mixed $value
     */
    public static function detectFromValue(Context $context, mixed $value): string
    {
        return self::detectFromContext($context)->unwrapOrElse(
            static function () use ($context, $value) {
                $xsd = $context->namespaces->lookupNameFromNamespace(SoapXmlns::xsd()->value())->unwrap();

                return match (true) {
                    is_string($value) => $xsd . ':string',
                    is_int($value) => $xsd . ':int',
                    is_float($value) => $xsd . ':float',
                    is_bool($value) => $xsd . ':boolean',
                    default => $xsd . ':anyType',
                };
            }
        );
    }

    /**
     * @return Option<XsdType>
     */
    public static function detectXsdTypeFromXmlElement(Context $context, DOMElement $element): Option
    {
        $xsiType = $element->getAttributeNS(Xmlns::xsi()->value(), 'type') ?: $element->getAttribute('xsi:type');
        if (!$xsiType) {
            return none();
        }

        [$prefix, $localName] = (new QnameParser)($xsiType);
        if (!$localName) {
            return none();
        }

        $namespaceUri = $prefix
            ? $element->lookupNamespaceURI($prefix) ?? $context->namespaces->lookupNamespaceFromName($prefix)->unwrapOr(null)
            : $element->lookupNamespaceURI(null) ?? $element->namespaceURI;

        if ($namespaceUri === null || $namespaceUri === '') {
            return none();
        }

        return some(
            // We create a new type based on the detected xsi:type, but we keep the meta information of the original type.
            // This way we can still detect if the type is nullable, a union, used on an element, ...
            $context->type
                ->copy($localName)
                ->withXmlTypeName($localName)
                ->withXmlNamespace($namespaceUri)
        );
    }

    /**
     * @return Option<FixedIsoEncoder<mixed, string>>
     */
    public static function detectEncoderFromXmlElement(Context $context, DOMElement $element): Option
    {
        $requestedXsiType = self::detectXsdTypeFromXmlElement($context, $element);
        if (!$requestedXsiType->isSome()) {
            return none();
        }

        // Enhance context to avoid duplicate optionals, repeating elements, xsi:type detections, ...
        $type = $requestedXsiType->unwrap();

        $cache = self::encoderCache();
        $registryCache = $cache[$context->registry] ?? [];
        $cacheKey = $type->getXmlNamespace() . '|' . $type->getXmlTypeName();
        if (!isset($registryCache[$cacheKey])) {
            $encoderDetectorTypeMeta = $type->getMeta()
                ->withIsNullable(false)
                ->withIsRepeatingElement(false);
            $encoderDetectorContext = $context
                ->withType($type->withMeta(static fn () => $encoderDetectorTypeMeta))
                ->withSkipXsiTypeDetection(true);

            $registryCache[$cacheKey] = $context->registry->detectEncoderForContext($encoderDetectorContext);
            $cache[$context->registry] = $registryCache;
        }

        return some(
            new FixedIsoEncoder(
                $registryCache[$cacheKey]->iso(
                    $context->withType($type)
                ),
            )
        );
    }

    /**
     * @return Option<non-empty-string>
     */
    private static function detectFromContext(Context $context): Option
    {
        $type = $context->type;
        $isAny = $type->getXmlNamespace() === SoapXmlns::xsd()->value() && $type->getName() === 'anyType';
        $isUnion = $type->getMeta()->unions()->isSome();

        if ($isAny && !$isUnion) {
            return none();
        }

        return some(
            sprintf(
                '%s:%s',
                $context->namespaces->lookupNameFromNamespace($type->getXmlNamespace())->unwrap(),
                $type->getXmlTypeName()
            )
        );
    }
}
