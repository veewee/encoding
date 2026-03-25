<?php
declare(strict_types=1);

namespace Soap\Encoding\Encoder\SimpleType;

use Soap\Encoding\Encoder\Context;
use Soap\Encoding\Encoder\XmlEncoder;
use Soap\Encoding\Exception\RestrictionException;
use VeeWee\Reflecta\Iso\Iso;
use function Psl\Type\scalar;

/**
 * @implements XmlEncoder<mixed, string|null>
 */
final class AttributeValueEncoder implements XmlEncoder
{
    /**
     * @param XmlEncoder<mixed, string> $typeEncoder
     */
    public function __construct(
        private readonly XmlEncoder $typeEncoder
    ) {
    }

    /**
     * @return Iso<mixed, string|null>
     */
    public function iso(Context $context): Iso
    {
        $typeIso = $this->typeEncoder->iso($context);

        return (new Iso(
            function (mixed $value) use ($context, $typeIso): ?string {
                $meta = $context->type->getMeta();
                $fixed = $meta->fixed()
                    ->map(static fn (string $fixed): mixed => $typeIso->from($fixed))
                    ->unwrapOr(null);

                if ($fixed !== null && $value !== $fixed) {
                    throw RestrictionException::invalidFixedValue(
                        scalar()->assert($fixed),
                        scalar()->assert($value)
                    );
                }

                return $value !== null ? $typeIso->to($value) : null;
            },
            static function (?string $value) use ($context, $typeIso): mixed {
                if ($value !== null) {
                    return $typeIso->from($value);
                }

                $meta = $context->type->getMeta();
                $default = $meta->fixed()->or($meta->default())->unwrapOr(null);

                return $default !== null ? $typeIso->from($default) : null;
            },
        ));
    }
}
