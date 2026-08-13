<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\SerializableShapeInterface;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Serde\ShapeType;
use Smithy\Core\Shape\BigDecimal;
use Smithy\Core\Shape\BigInteger;

/**
 * Contract for Smithy `document` shapes.
 *
 * A document carries shape-graph data whose runtime type is not known at
 * codegen time. Each concrete implementation carries one Smithy data-model
 * type (string, integer, list, map, struct, etc.) and dispatches its own
 * {@see SerializableShapeInterface::serialize()} into the matching
 * `serializeXxx()` on the codec's {@see ShapeSerializerInterface}.
 *
 * The interface formalizes the polymorphic `type()` / `serialize()` pair,
 * the discriminator and schema accessors, and the type-checked extraction
 * surface (`asString`, `asInteger`, `asList`, `getMember`, ...). Default
 * throw-on-mismatch behavior for extraction lives in
 * {@see AbstractDocument}. Bespoke implementations can override.
 */
interface DocumentInterface extends SerializableShapeInterface
{
    /**
     * Smithy data-model type carried by this document.
     *
     * Used by codecs that distinguish wire-equivalent representations. A
     * JSON list and a JSON map both decode into PHP arrays, but their
     * Document type differs.
     */
    public function type(): ShapeType;

    /**
     * Optional type-discriminator string (typically the Smithy shape ID)
     * used by some wire formats for union-of-document dispatch. Returns
     * null when the document carries no discriminator.
     */
    public function discriminator(): ?string;

    /**
     * Schema this document was modeled against.
     *
     * Null for untyped documents (user-constructed data with no Smithy
     * shape ID). Codec paths consult this to resolve member types when the
     * document holds a structured payload with a matching schema.
     */
    public function schema(): ?Schema;

    /** @throws IncompatibleDocumentTypeException */
    public function asString(): string;

    /** @throws IncompatibleDocumentTypeException */
    public function asBoolean(): bool;

    /** @throws IncompatibleDocumentTypeException */
    public function asByte(): int;

    /** @throws IncompatibleDocumentTypeException */
    public function asShort(): int;

    /** @throws IncompatibleDocumentTypeException */
    public function asInteger(): int;

    /** @throws IncompatibleDocumentTypeException */
    public function asLong(): int;

    /** @throws IncompatibleDocumentTypeException */
    public function asFloat(): float;

    /** @throws IncompatibleDocumentTypeException */
    public function asDouble(): float;

    /** @throws IncompatibleDocumentTypeException */
    public function asBigInteger(): BigInteger;

    /** @throws IncompatibleDocumentTypeException */
    public function asBigDecimal(): BigDecimal;

    /** @throws IncompatibleDocumentTypeException */
    public function asBlob(): string;

    /** @throws IncompatibleDocumentTypeException */
    public function asTimestamp(): float;

    /**
     * @return list<self>
     * @throws IncompatibleDocumentTypeException
     */
    public function asList(): array;

    /**
     * @return array<string, self>
     * @throws IncompatibleDocumentTypeException
     */
    public function asMap(): array;

    /**
     * Look up a single member of a map, struct, or union document.
     *
     * Returns null when the member is absent. Throws on document types
     * that don't have named members (lists, scalars, blobs, timestamps).
     *
     * @throws IncompatibleDocumentTypeException
     */
    public function getMember(string $name): ?self;
}
