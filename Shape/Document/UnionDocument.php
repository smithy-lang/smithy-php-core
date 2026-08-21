<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Shape\ShapeType;
use Smithy\Core\Shape\UnionInterface;

/**
 * Represent a modeled union with a schema-backed document.
 *
 * Runtime schemas use {@see create()} or {@see unknown()}.
 */
final class UnionDocument extends AbstractDocument implements UnionInterface
{
    private bool $documentResolved = false;
    private ?AbstractDocument $document = null;

    /**
     * @param Schema $valueSchema Canonical union schema.
     * @param string $tag Active variant tag.
     * @param mixed $value Raw active variant value.
     * @param Schema $serializationSchema Schema used at the parent
     *        serialization boundary.
     */
    private function __construct(
        private readonly Schema $valueSchema,
        private readonly string $tag,
        private readonly mixed $value,
        private readonly int $memberIndex,
        Schema $serializationSchema
    )
    {
        parent::__construct($serializationSchema);
    }

    /**
     * Build a union document from a known variant.
     *
     * @return self Schema-backed union document.
     */
    public static function create(
        Schema $schema,
        string $tag,
        mixed $value
    ): self
    {
        $valueSchema = $schema->resolveTarget();

        return new self(
            $valueSchema,
            $tag,
            $value,
            $valueSchema->getMember($tag)?->memberIndex ?? -1,
            $schema
        );
    }

    /**
     * Build a union document for an unknown variant.
     *
     * @return self Schema-backed union document.
     */
    public static function unknown(
        Schema $schema,
        string $tag,
        mixed $value
    ): self
    {
        return self::create($schema, $tag, $value);
    }

    /**
     * Convert an existing modeled union into a document.
     *
     * @return self Schema-backed union document.
     */
    public static function fromUnion(
        UnionInterface $value,
        ?Schema $schema = null
    ): self
    {
        $valueSchema = $value->getSchema();

        return new self(
            $valueSchema,
            $value->tag(),
            $value->value(),
            $value->getMemberIndex(),
            $schema ?? $valueSchema
        );
    }

    /**
     * Return this value with a different parent serialization schema.
     *
     * @return self Schema-backed union document.
     */
    public function withSerializationSchema(Schema $schema): self
    {
        if ($this->schema === $schema) {
            return $this;
        }

        return new self(
            $this->valueSchema,
            $this->tag,
            $this->value,
            $this->memberIndex,
            $schema
        );
    }

    /**
     * Return the document type.
     *
     * @return ShapeType Union document type.
     */
    public function type(): ShapeType
    {
        return ShapeType::Union;
    }

    /**
     * Return the canonical union schema.
     */
    public function getSchema(): Schema
    {
        return $this->valueSchema;
    }

    /**
     * Return the active variant's schema member index.
     */
    public function getMemberIndex(): int
    {
        return $this->memberIndex;
    }

    /**
     * Return the active variant tag.
     */
    public function tag(): string
    {
        return $this->tag;
    }

    /**
     * Return the raw active variant value.
     */
    public function value(): mixed
    {
        return $this->value;
    }

    /**
     * Return the active variant as a schema-backed document.
     *
     * @return AbstractDocument|null Variant document, or null for another
     *                               name.
     */
    public function getMember(string $name): ?AbstractDocument
    {
        if ($name !== $this->tag) {
            return null;
        }

        if (!$this->documentResolved) {
            $member = $this->valueSchema->getMember($this->tag);
            $this->document = $member === null
                ? AbstractDocument::fromValue($this->value)
                : AbstractDocument::fromSchemaValue(
                    $member,
                    $this->value
                );
            $this->documentResolved = true;
        }

        return $this->document;
    }

    /**
     * Return the active variant as a single-entry map.
     *
     * @return array<string, AbstractDocument|null> Active variant document.
     */
    public function asMap(): array
    {
        return [$this->tag => $this->getMember($this->tag)];
    }

    /**
     * Dispatch into {@see ShapeSerializerInterface::serializeUnion()}.
     *
     * @return void
     */
    public function serialize(
        ShapeSerializerInterface $serializer,
        int $idx
    ): void
    {
        $serializer->serializeUnion(
            $this->schema,
            $this,
            $idx
        );
    }
}
