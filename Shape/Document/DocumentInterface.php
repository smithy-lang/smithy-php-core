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
 * A document carries shape-graph data whose runtime type is not known at codegen time. Each
 * concrete implementation carries one Smithy data-model type (string, integer, list, map, struct,
 * etc.) and dispatches its own {@see SerializableShapeInterface::serialize()} into the matching
 * `serializeXxx()` on the codec's {@see ShapeSerializerInterface}.
 *
 * The interface formalizes the polymorphic `type()` / `serialize()` pair, the discriminator and
 * schema accessors, and the type-checked extraction surface (`asString`, `asInteger`, `asList`,
 * `getMember`, ...). Default throw-on-mismatch behavior for extraction lives in
 * {@see AbstractDocument}. Bespoke implementations can override.
 */
interface DocumentInterface extends SerializableShapeInterface
{
    /**
     * Return the Smithy data-model type carried by this document.
     *
     * Used by codecs that distinguish wire-equivalent representations. A JSON list and a JSON map
     * both decode into PHP arrays, but their Document type differs.
     *
     * @return ShapeType Smithy shape type carried by this document.
     */
    public function type(): ShapeType;

    /**
     * Return the optional type-discriminator string, or null when the document carries no
     * discriminator.
     *
     * The discriminator is the Smithy shape ID. Wire formats that support union-of-document
     * dispatch use it to route the document to the matching modeled shape.
     *
     * @return string|null Type discriminator, or null when absent.
     */
    public function discriminator(): ?string;

    /**
     * Return the schema this document was modeled against.
     *
     * Null for untyped documents (user-constructed data with no Smithy shape ID). Codec paths
     * consult this to resolve member types when the document holds a structured payload with a
     * matching schema.
     *
     * @return Schema|null Schema the document was modeled against, or null when the document is
     *                     untyped.
     */
    public function schema(): ?Schema;

    /**
     * Return the document's payload as a string.
     *
     * @return string String value.
     * @throws IncompatibleDocumentTypeException When the document does not carry a string value.
     */
    public function asString(): string;

    /**
     * Return the document's payload as a boolean.
     *
     * @return bool Boolean value.
     * @throws IncompatibleDocumentTypeException When the document does not carry a boolean value.
     */
    public function asBoolean(): bool;

    /**
     * Return the document's payload as a byte-typed integer.
     *
     * @return int Byte value.
     * @throws IncompatibleDocumentTypeException When the document does not carry a byte value.
     */
    public function asByte(): int;

    /**
     * Return the document's payload as a short-typed integer.
     *
     * @return int Short value.
     * @throws IncompatibleDocumentTypeException When the document does not carry a short value.
     */
    public function asShort(): int;

    /**
     * Return the document's payload as an integer.
     *
     * @return int Integer value.
     * @throws IncompatibleDocumentTypeException When the document does not carry an integer value.
     */
    public function asInteger(): int;

    /**
     * Return the document's payload as a long-typed integer.
     *
     * @return int Long value.
     * @throws IncompatibleDocumentTypeException When the document does not carry a long value.
     */
    public function asLong(): int;

    /**
     * Return the document's payload as a float.
     *
     * @return float Float value.
     * @throws IncompatibleDocumentTypeException When the document does not carry a float value.
     */
    public function asFloat(): float;

    /**
     * Return the document's payload as a double.
     *
     * @return float Double value.
     * @throws IncompatibleDocumentTypeException When the document does not carry a double value.
     */
    public function asDouble(): float;

    /**
     * Return the document's payload as a {@see BigInteger}.
     *
     * @return BigInteger Arbitrary-precision integer wrapper.
     * @throws IncompatibleDocumentTypeException When the document does not carry a bigInteger
     *                                           value.
     */
    public function asBigInteger(): BigInteger;

    /**
     * Return the document's payload as a {@see BigDecimal}.
     *
     * @return BigDecimal Arbitrary-precision decimal wrapper.
     * @throws IncompatibleDocumentTypeException When the document does not carry a bigDecimal
     *                                           value.
     */
    public function asBigDecimal(): BigDecimal;

    /**
     * Return the document's payload as blob bytes.
     *
     * @return string Raw bytes.
     * @throws IncompatibleDocumentTypeException When the document does not carry a blob value.
     */
    public function asBlob(): string;

    /**
     * Return the document's payload as an epoch-seconds timestamp.
     *
     * @return float Epoch seconds.
     * @throws IncompatibleDocumentTypeException When the document does not carry a timestamp value.
     */
    public function asTimestamp(): float;

    /**
     * Return the document's payload as a list of documents.
     *
     * @return list<self> Ordered list of member documents.
     * @throws IncompatibleDocumentTypeException When the document does not carry a list value.
     */
    public function asList(): array;

    /**
     * Return the document's payload as a string-keyed map of documents.
     *
     * @return array<string, self> Map from key to member document.
     * @throws IncompatibleDocumentTypeException When the document does not carry a map value.
     */
    public function asMap(): array;

    /**
     * Look up a single member of a map, struct, or union document.
     *
     * Returns null when the member is absent. Throws on document types that don't have named
     * members (lists, scalars, blobs, timestamps).
     *
     * @param string $name Member name to look up.
     *
     * @return self|null Member document, or null when the member is absent.
     * @throws IncompatibleDocumentTypeException When the underlying document type has no named
     *                                           members.
     */
    public function getMember(string $name): ?self;
}
