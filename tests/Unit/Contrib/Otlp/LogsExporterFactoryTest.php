<?php

declare(strict_types=1);

namespace OpenTelemetry\Tests\Unit\Contrib\Otlp;

use AssertWell\PHPUnitGlobalState\EnvironmentVariables;
use OpenTelemetry\Contrib\Otlp\LogsExporterFactory;
use OpenTelemetry\SDK\Common\Configuration\KnownValues;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OpenTelemetry\Contrib\Otlp\LogsExporterFactory
 */
class LogsExporterFactoryTest extends TestCase
{
    use EnvironmentVariables;

    /** @var TransportFactoryInterface&MockObject $record */
    private TransportFactoryInterface $transportFactory;
    /** @var TransportInterface&MockObject $record */
    private TransportInterface $transport;

    public function setUp(): void
    {
        $this->transportFactory = $this->createMock(TransportFactoryInterface::class);
        $this->transport = $this->createMock(TransportInterface::class);
    }

    public function tearDown(): void
    {
        $this->restoreEnvironmentVariables();
    }

    public function test_unknown_protocol_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->setEnvironmentVariable(Variables::OTEL_EXPORTER_OTLP_PROTOCOL, 'foo');
        $factory = new LogsExporterFactory();
        $factory->create();
    }

    /**
     * @dataProvider configProvider
     */
    public function test_create(array $env, string $endpoint, string $protocol, string $compression, array $headerKeys = []): void
    {
        foreach ($env as $k => $v) {
            $this->setEnvironmentVariable($k, $v);
        }
        $factory = new LogsExporterFactory($this->transportFactory);
        // @phpstan-ignore-next-line
        $this->transportFactory
            ->expects($this->once())
            ->method('create')
            ->with(
                $this->equalTo($endpoint),
                $this->equalTo($protocol),
                $this->callback(function ($headers) use ($headerKeys) {
                    $this->assertEqualsCanonicalizing($headerKeys, array_keys($headers));

                    return true;
                }),
                $this->equalTo($compression)
            )
            ->willReturn($this->transport);
        // @phpstan-ignore-next-line
        $this->transport->method('contentType')->willReturn($protocol);

        $factory->create();
    }

    public static function configProvider(): array
    {
        $defaultHeaderKeys = ['User-Agent'];

        return [
            'signal-specific endpoint unchanged' => [
                'env' => [
                    Variables::OTEL_EXPORTER_OTLP_LOGS_PROTOCOL => KnownValues::VALUE_GRPC,
                    Variables::OTEL_EXPORTER_OTLP_LOGS_ENDPOINT => 'http://collector:4317/foo/bar', //should not be changed, per spec
                ],
                'endpoint' => 'http://collector:4317/foo/bar',
                'protocol' => 'application/x-protobuf',
                'compression' => 'none',
                'headerKeys' => $defaultHeaderKeys,
            ],
            'endpoint has path appended' => [
                'env' => [
                    Variables::OTEL_EXPORTER_OTLP_LOGS_PROTOCOL => KnownValues::VALUE_GRPC,
                    Variables::OTEL_EXPORTER_OTLP_ENDPOINT => 'http://collector:4317',
                ],
                'endpoint' => 'http://collector:4317/opentelemetry.proto.collector.logs.v1.LogsService/Export',
                'protocol' => 'application/x-protobuf',
                'compression' => 'none',
                'headerKeys' => $defaultHeaderKeys,
            ],
            'protocol' => [
                'env' => [
                    Variables::OTEL_EXPORTER_OTLP_PROTOCOL => KnownValues::VALUE_HTTP_NDJSON,
                ],
                'endpoint' => 'http://localhost:4318/v1/logs',
                'protocol' => 'application/x-ndjson',
                'compression' => 'none',
                'headerKeys' => $defaultHeaderKeys,
            ],
            'signal-specific protocol' => [
                'env' => [
                    Variables::OTEL_EXPORTER_OTLP_PROTOCOL => KnownValues::VALUE_HTTP_JSON,
                ],
                'endpoint' => 'http://localhost:4318/v1/logs',
                'protocol' => 'application/json',
                'compression' => 'none',
                'headerKeys' => $defaultHeaderKeys,
            ],
            'defaults' => [
                'env' => [],
                'endpoint' => 'http://localhost:4318/v1/logs',
                'protocol' => 'application/x-protobuf',
                'compression' => 'none',
                'headerKeys' => $defaultHeaderKeys,
            ],
            'compression' => [
                'env' => [
                    Variables::OTEL_EXPORTER_OTLP_COMPRESSION => 'gzip',
                ],
                'endpoint' => 'http://localhost:4318/v1/logs',
                'protocol' => 'application/x-protobuf',
                'compression' => 'gzip',
                'headerKeys' => $defaultHeaderKeys,
            ],
            'signal-specific compression' => [
                'env' => [
                    Variables::OTEL_EXPORTER_OTLP_LOGS_COMPRESSION => 'gzip',
                ],
                'endpoint' => 'http://localhost:4318/v1/logs',
                'protocol' => 'application/x-protobuf',
                'compression' => 'gzip',
                'headerKeys' => $defaultHeaderKeys,
            ],
            'headers' => [
                'env' => [
                    Variables::OTEL_EXPORTER_OTLP_HEADERS => 'key1=foo,key2=bar',
                ],
                'endpoint' => 'http://localhost:4318/v1/logs',
                'protocol' => 'application/x-protobuf',
                'compression' => 'none',
                'headerKeys' => array_merge($defaultHeaderKeys, ['key1', 'key2']),
            ],
            'signal-specific headers' => [
                'env' => [
                    Variables::OTEL_EXPORTER_OTLP_LOGS_HEADERS => 'key3=foo,key4=bar',
                ],
                'endpoint' => 'http://localhost:4318/v1/logs',
                'protocol' => 'application/x-protobuf',
                'compression' => 'none',
                'headerKeys' => array_merge($defaultHeaderKeys, ['key3', 'key4']),
            ],
        ];
    }
}
