<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Materialize runtime traits for schemas.
 */
final class SchemaTraitRegistry
{
    private const DEFAULT_KEYS = [
        'smithy.api#xmlName' => Schema::T_XML_NAME,
        'smithy.api#xmlNamespace' => Schema::T_XML_NAMESPACE,
        'smithy.api#xmlAttribute' => Schema::T_XML_ATTRIBUTE,
        'smithy.api#xmlFlattened' => Schema::T_XML_FLATTENED,
        'smithy.api#timestampFormat' => Schema::T_TIMESTAMP_FORMAT,
        'smithy.api#httpHeader' => Schema::T_HTTP_HEADER,
        'smithy.api#httpLabel' => Schema::T_HTTP_LABEL,
        'smithy.api#httpPayload' => Schema::T_HTTP_PAYLOAD,
        'smithy.api#httpPrefixHeaders' => Schema::T_HTTP_PREFIX_HEADERS,
        'smithy.api#httpQuery' => Schema::T_HTTP_QUERY,
        'smithy.api#httpQueryParams' => Schema::T_HTTP_QUERY_PARAMS,
        'smithy.api#httpResponseCode' => Schema::T_HTTP_RESPONSE_CODE,
        'smithy.api#jsonName' => Schema::T_JSON_NAME,
        'smithy.api#mediaType' => Schema::T_MEDIA_TYPE,
        'smithy.api#required' => Schema::T_REQUIRED,
        'smithy.api#sparse' => Schema::T_SPARSE,
        'smithy.api#streaming' => Schema::T_STREAMING,
        'smithy.api#http' => Schema::T_HTTP,
        'smithy.api#idempotencyToken' => Schema::T_IDEMPOTENCY_TOKEN,
        'smithy.api#sensitive' => Schema::T_SENSITIVE,
        'smithy.api#httpChecksumRequired' =>
            Schema::T_HTTP_CHECKSUM_REQUIRED,
        'smithy.api#requestCompression' => Schema::T_REQUEST_COMPRESSION,
        'smithy.api#error' => Schema::T_ERROR,
        'smithy.api#readonly' => Schema::T_READ_ONLY,
        'smithy.api#idempotent' => Schema::T_IDEMPOTENT,
        'smithy.api#retryable' => Schema::T_RETRYABLE,
        'smithy.api#paginated' => Schema::T_PAGINATED
    ];

    /**
     * @var array<string, array{
     *     key: int|null,
     *     materializer: \Closure(mixed): mixed
     * }>
     */
    private array $definitions = [];

    /**
     * Register the built-in trait definitions.
     */
    public function __construct()
    {
        foreach (self::DEFAULT_KEYS as $traitId => $traitKey) {
            $this->register(
                $traitId,
                $traitKey,
                self::materializeKnownTrait(...)
            );
        }
    }

    /**
     * Return the built-in dense key for a trait shape ID.
     */
    public static function getDefaultKey(string $traitId): ?int
    {
        return self::DEFAULT_KEYS[$traitId] ?? null;
    }

    /**
     * Return the built-in shape ID for a dense trait key.
     */
    public static function getDefaultId(int $traitKey): ?string
    {
        $traitId = \array_search(
            $traitKey,
            self::DEFAULT_KEYS,
            true
        );

        return $traitId === false ? null : $traitId;
    }

    /**
     * Register a trait materializer.
     *
     * @param \Closure(mixed): mixed|null $materializer Value materializer.
     *
     * @return void
     */
    public function register(
        string $traitId,
        ?int $traitKey = null,
        ?\Closure $materializer = null
    ): void
    {
        $traitKey ??= $this->definitions[$traitId]['key'] ?? null;
        $this->definitions[$traitId] = [
            'key' => $traitKey,
            'materializer' => $materializer
                ?? static fn(mixed $value): mixed => $value
        ];
    }

    /**
     * Materialize modeled traits.
     *
     * @param array<string, mixed> $traits Trait shape ID to raw value.
     *
     * @return array{
     *     dense: array<int, mixed>,
     *     byId: array<string, mixed>
     * } Materialized trait views.
     */
    public function materialize(array $traits): array
    {
        $dense = [];
        $byId = [];

        foreach ($traits as $traitId => $rawValue) {
            $definition = $this->definitions[$traitId] ?? null;
            if ($definition === null) {
                $byId[$traitId] = new DynamicTraitValue(
                    $traitId,
                    $rawValue
                );
                continue;
            }

            $value = ($definition['materializer'])($rawValue);
            $byId[$traitId] = $value;
            if ($definition['key'] !== null) {
                $dense[$definition['key']] = $value;
            }
        }

        return ['dense' => $dense, 'byId' => $byId];
    }

    /**
     * Normalize a built-in trait value.
     */
    private static function materializeKnownTrait(mixed $value): mixed
    {
        return $value === [] || $value === null ? true : $value;
    }
}
