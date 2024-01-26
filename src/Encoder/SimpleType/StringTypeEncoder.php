<?php
declare(strict_types=1);

namespace Soap\Encoding\Encoder\SimpleType;

use Soap\Encoding\Encoder\Context;
use Soap\Encoding\Encoder\XmlEncoder;
use VeeWee\Reflecta\Iso\Iso;

/**
 * @implements XmlEncoder<string, string>
 */
final class StringTypeEncoder implements XmlEncoder
{
    public function iso(Context $context): Iso
    {
        return (new Iso(
            static fn (string $value): string => $value,
            static fn (string $value): string => $value,
        ));
    }
}