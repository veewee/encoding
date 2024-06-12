<?php
declare(strict_types=1);

namespace Soap\Encoding\Encoder;

use Soap\Encoding\Xml\Writer\NilAttributeBuilder;
use Soap\Encoding\Xml\Writer\XsdTypeXmlElementWriter;
use VeeWee\Reflecta\Iso\Iso;
use VeeWee\Xml\Dom\Document;
use VeeWee\Xml\Xmlns\Xmlns;

/**
 * @template T of mixed
 * @implements XmlEncoder<T, string>
 */
final class OptionalElementEncoder implements XmlEncoder
{
    /**
     * @param XmlEncoder<T, string> $elementEncoder
     */
    public function __construct(
        private readonly XmlEncoder $elementEncoder
    ) {
    }

    /**
     * @return Iso<T, string>
     */
    public function iso(Context $context): Iso
    {
        $type = $context->type;
        $meta = $type->getMeta();
        $elementIso = $this->elementEncoder->iso($context);

        $isNullable = $meta->isNullable()->unwrapOr(false);
        if (!$isNullable) {
            return $elementIso;
        }

        $isNillable = $meta->isNil()->unwrapOr(false);
        $elementIso = $this->elementEncoder->iso($context);

        return new Iso(
            /**
             * @param T|null $raw
             */
            static fn (mixed $raw): string => match (true) {
                $raw === null && $isNillable => (new XsdTypeXmlElementWriter())($context, new NilAttributeBuilder()),
                $raw === null => '',
                default => $elementIso->to($raw),
            },
            /**
             * @return T|null
             */
            static function (string $xml) use ($context, $elementIso) : mixed {
                if ($xml === '') {
                    return null;
                }

                $documentElement = $context->xmlCache->read($xml)->locateDocumentElement();
                if ($documentElement->getAttributeNS(Xmlns::xsi()->value(), 'nil') === 'true') {
                    return null;
                }

                return $elementIso->from($xml);
            }
        );
    }
}
