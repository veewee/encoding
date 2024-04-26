<?php
declare(strict_types=1);

namespace Soap\Encoding;

use Soap\Encoding\Encoder\Context;
use Soap\Engine\Decoder as SoapDecoder;
use Soap\Engine\HttpBinding\SoapResponse;
use Soap\Engine\Metadata\Metadata;

final class Decoder implements SoapDecoder
{
    public function __construct(
        private readonly Metadata $metadata,
        private readonly EncoderRegistry $registry
    ) {
    }

    /**
     * @return mixed
     */
    public function decode(string $method, SoapResponse $response)
    {
        // TODO  : invariants
        // | inputBindingUsage  | literal                                                                                                |
        // | bindingStyle       | document                                                                                               |

        $methodInfo = $this->metadata->getMethods()->fetchByName($method);
        $meta = $methodInfo->getMeta();
        $returnType = $methodInfo->getReturnType();
        $context = new Context($returnType, $this->metadata, $this->registry);


        $decoder = $this->registry->findByXsdType($returnType);

        // TODO : Unwind envelope

        return $decoder->iso($context)->from($response->getPayload());
    }
}