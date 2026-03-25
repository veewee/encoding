<?php

declare(strict_types=1);

namespace Soap\Encoding\Benchmarks;

use Soap\Encoding\Driver;
use Soap\Encoding\Encoder\Context;
use Soap\Encoding\EncoderRegistry;
use Soap\Encoding\Xml\Node\Element;
use Soap\Encoding\Xml\Reader\OperationReader;
use Soap\Engine\Metadata\Metadata;
use Soap\Wsdl\Loader\CallbackLoader;
use Soap\WsdlReader\Metadata\Wsdl1MetadataProvider;
use Soap\WsdlReader\Model\Definitions\BindingUse;
use Soap\WsdlReader\Model\Definitions\Namespaces;
use Soap\WsdlReader\Wsdl1Reader;
use function Psl\Iter\first;
use function Psl\Vec\map;

abstract class AbstractEncodingBench
{
    protected Driver $driver;
    protected Metadata $metadata;
    protected EncoderRegistry $registry;
    protected Namespaces $namespaces;

    abstract protected function schema(): string;

    abstract protected function type(): string;

    protected function style(): string
    {
        return 'rpc';
    }

    protected function use(): string
    {
        return 'encoded';
    }

    protected function buildDriver(): void
    {
        $schema = $this->schema();
        $type = $this->type();
        $style = $this->style();
        $use = $this->use();

        $wsdl = <<<EOF
        <definitions name="BenchTest"
            xmlns:xsd="http://www.w3.org/2001/XMLSchema"
            xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
            xmlns:tns="http://test-uri/"
            xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
            xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/"
            xmlns="http://schemas.xmlsoap.org/wsdl/"
            targetNamespace="http://test-uri/"
            >
          <types>
          <schema xmlns="http://www.w3.org/2001/XMLSchema" targetNamespace="http://test-uri/">
           <xsd:import namespace="http://schemas.xmlsoap.org/soap/encoding/" />
           <xsd:import namespace="http://schemas.xmlsoap.org/wsdl/" />
            {$schema}
          </schema>
          </types>
          <message name="testMessage">
            <part name="testParam" {$type}/>
          </message>
            <portType name="testPortType">
                <operation name="test">
                    <input message="testMessage"/>
                    <output message="testMessage"/>
                </operation>
            </portType>
            <binding name="testBinding" type="testPortType">
                <soap:binding style="rpc" transport="http://schemas.xmlsoap.org/soap/http"/>
                <operation name="test">
                    <soap:operation soapAction="#test" style="{$style}"/>
                    <input>
                        <soap:body use="{$use}" namespace="http://test-uri/" encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"/>
                    </input>
                    <output>
                        <soap:body use="{$use}" namespace="http://test-uri/" encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"/>
                    </output>
                </operation>
            </binding>
            <service name="testService">
           <port name="testPort" binding="tns:testBinding">
             <soap:address location="test://" />
           </port>
         </service>
        </definitions>
        EOF;

        $wsdlObject = (new Wsdl1Reader(
            new CallbackLoader(static fn (): string => $wsdl)
        ))('file.wsdl');

        $this->registry = EncoderRegistry::default();
        $metadataProvider = new Wsdl1MetadataProvider($wsdlObject);
        $this->metadata = $metadataProvider->getMetadata();
        $this->namespaces = $wsdlObject->namespaces;
        $this->driver = Driver::createFromMetadata($this->metadata, $this->namespaces, $this->registry);
    }

    protected function encode(string $method, array $params): string
    {
        return $this->driver->encode($method, $params)->getRequest();
    }

    protected function decodeParam(string $method, string $encodedXml): mixed
    {
        $methodMeta = $this->metadata->getMethods()->fetchByName($method);
        $param = first($methodMeta->getParameters());
        $decodeContext = new Context(
            $param->getType(),
            $this->metadata,
            $this->registry,
            $this->namespaces,
            $methodMeta->getMeta()
                ->inputBindingUsage()
                ->map(BindingUse::from(...))
                ->unwrapOr(BindingUse::LITERAL)
        );

        $decoder = $this->registry->detectEncoderForContext($decodeContext);
        $params = (new OperationReader($methodMeta->getMeta()))($encodedXml);
        $paramXml = implode('', map($params->elements(), static fn (Element $element): string => $element->value()));

        return $decoder->iso($decodeContext)->from($paramXml);
    }
}
