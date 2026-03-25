<?php declare(strict_types=1);

namespace Soap\Encoding\Encoder;

use Soap\Encoding\TypeInference\XsiTypeDetector;
use Soap\Encoding\Xml\Node\Element;
use VeeWee\Reflecta\Iso\Iso;
use function Psl\Type\non_empty_string;

/**
 * @implements XmlEncoder<mixed, string>
 */
final readonly class XsiTypeEncoder implements Feature\ElementAware, XmlEncoder
{
    /**
     * @param XmlEncoder<mixed, string> $encoder
     */
    public function __construct(
        private XmlEncoder $encoder
    ) {
    }

    /**
     * @return Iso<mixed, string>
     */
    public function iso(Context $context): Iso
    {
        $innerIso = $this->encoder->iso($context);

        return new Iso(
            static fn (mixed $value): string => $innerIso->to($value),
            function (string|Element $value) use ($context, $innerIso) : mixed {
                $element = $value instanceof Element ? $value : Element::fromString(non_empty_string()->assert($value));

                /** @var XmlEncoder<string, mixed> $encoder */
                $encoder = match (true) {
                    $this->encoder instanceof Feature\DisregardXsiInformation => $this->encoder,
                    default => XsiTypeDetector::detectEncoderFromXmlElement($context, $element->element())->unwrapOr($this->encoder)
                };

                $iso = $encoder === $this->encoder ? $innerIso : $encoder->iso($context);

                return $iso->from($element);
            }
        );
    }
}
