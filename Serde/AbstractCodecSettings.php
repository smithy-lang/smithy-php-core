<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Shared codec settings.
 *
 * Carries service-level metadata that multiple wire formats may need,
 * along with the codec's fallback timestamp format when no explicit
 * `@timestampFormat` trait is present on the schema path being
 * serialized or deserialized, or when the protocol ignores that trait.
 */
abstract class AbstractCodecSettings
{
    public readonly ?string $serviceNamespace;

    public readonly TimestampFormat $defaultTimestampFormat;

    public readonly bool $honorTimestampFormatTrait;

    /**
     * @param string|null $serviceNamespace Service namespace for codec consumers.
     * @param TimestampFormat|null $defaultTimestampFormat Fallback timestamp format.
     * @param bool $honorTimestampFormatTrait Whether to honor `timestampFormat`.
     */
    public function __construct(
        ?string $serviceNamespace = null,
        ?TimestampFormat $defaultTimestampFormat = null,
        bool $honorTimestampFormatTrait = true
    ) {
        $this->serviceNamespace = $serviceNamespace;
        $this->defaultTimestampFormat = $defaultTimestampFormat
            ?? TimestampFormat::EpochSeconds;
        $this->honorTimestampFormatTrait = $honorTimestampFormatTrait;
    }
}
