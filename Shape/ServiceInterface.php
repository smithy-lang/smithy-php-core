<?php

declare(strict_types=1);

namespace Smithy\Core\Shape;

/**
 * Interface implemented by generated Smithy service metadata classes.
 *
 * Each Smithy service produces a class that carries the service name, shape ID, version, and the
 * list of all operations it exposes.
 */
interface ServiceInterface
{
    /**
     * Return the service name (e.g., "STS", "DynamoDB").
     *
     * @return string Service name.
     */
    public static function name(): string;

    /**
     * Return the fully-qualified Smithy shape ID of the service.
     *
     * @return string Service shape ID.
     */
    public static function id(): string;

    /**
     * Return the service version string.
     *
     * @return string Service version.
     */
    public static function version(): string;

    /**
     * Return the list of all operation class names in this service.
     *
     * @return list<class-string<OperationInterface>> Operation class names.
     */
    public static function operations(): array;
}
