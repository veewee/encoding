<?php

declare(strict_types=1);

namespace Soap\Encoding\Benchmarks;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[BeforeMethods('setUp')]
#[Warmup(2)]
#[Iterations(5)]
#[Revs(1)]
class ComplexTypeListBench extends AbstractEncodingBench
{
    private object $singleItem;
    private array $items;
    private string $encodedXml;

    protected function schema(): string
    {
        return <<<'EOXML'
        <complexType name="addressType">
            <sequence>
                <element name="street" type="xsd:string"/>
                <element name="city" type="xsd:string"/>
                <element name="zip" type="xsd:string"/>
            </sequence>
        </complexType>
        <complexType name="itemType">
            <sequence>
                <element name="id" type="xsd:int"/>
                <element name="name" type="xsd:string"/>
                <element name="description" type="xsd:string"/>
                <element name="price" type="xsd:float"/>
                <element name="quantity" type="xsd:int"/>
                <element name="active" type="xsd:boolean"/>
                <element name="sku" type="xsd:string"/>
                <element name="category" type="xsd:string"/>
                <element name="address" type="tns:addressType"/>
            </sequence>
        </complexType>
        EOXML;
    }

    protected function type(): string
    {
        return 'type="tns:itemType"';
    }

    public function setUp(): void
    {
        $this->buildDriver();

        $this->singleItem = (object) [
            'id' => 42,
            'name' => 'Widget Pro',
            'description' => 'A high-quality widget for professional use',
            'price' => 29.99,
            'quantity' => 100,
            'active' => true,
            'sku' => 'WDG-PRO-001',
            'category' => 'Electronics',
            'address' => (object) [
                'street' => '123 Main St',
                'city' => 'Springfield',
                'zip' => '62701',
            ],
        ];

        $this->items = [];
        for ($i = 0; $i < 500; $i++) {
            $this->items[] = (object) [
                'id' => $i,
                'name' => 'Widget ' . $i,
                'description' => 'Description for widget ' . $i,
                'price' => 9.99 + $i,
                'quantity' => $i * 10,
                'active' => $i % 2 === 0,
                'sku' => 'WDG-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'category' => 'Category ' . ($i % 10),
                'address' => (object) [
                    'street' => $i . ' Elm St',
                    'city' => 'City ' . ($i % 50),
                    'zip' => str_pad((string) ($i % 99999), 5, '0', STR_PAD_LEFT),
                ],
            ];
        }

        // Pre-encode for decode benchmarks
        $this->encodedXml = $this->encode('test', [$this->singleItem]);
    }

    #[Revs(100)]
    public function benchEncodeSingle(): void
    {
        $this->encode('test', [$this->singleItem]);
    }

    public function benchDecodeSingle(): void
    {
        $this->decodeParam('test', $this->encodedXml);
    }

    public function benchEncodeList500(): void
    {
        foreach ($this->items as $item) {
            $this->encode('test', [$item]);
        }
    }

    public function benchDecodeList500(): void
    {
        foreach ($this->items as $item) {
            $xml = $this->encode('test', [$item]);
            $this->decodeParam('test', $xml);
        }
    }
}
