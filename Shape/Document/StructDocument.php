<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\SerializableStructInterface;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Serde\ShapeType;

/**
 * Represent a modeled structure with a schema-backed document.
 *
 * Runtime schemas use {@see fromMembers()} to retain raw positional values
 * and create child documents only when inspected.
 */
final class StructDocument extends AbstractDocument
    implements SerializableStructInterface
{
    /**
     * @var array<int, AbstractDocument|null>
     */
    private array $documents = [];

    /**
     * @param Schema $valueSchema Canonical structure schema.
     * @param array<int, mixed> $members Positional member values.
     * @param Schema $serializationSchema Schema used at the parent
     *        serialization boundary.
     */
    private function __construct(
        private readonly Schema $valueSchema,
        private readonly array $members,
        Schema $serializationSchema
    )
    {
        parent::__construct($serializationSchema);
    }

    /**
     * Build a structure document from positional member values.
     *
     * @param Schema $schema Structure schema or member reference.
     * @param array<int, mixed> $members Positional member values.
     *
     * @return self Schema-backed structure document.
     */
    public static function fromMembers(Schema $schema, array $members): self
    {
        return new self(
            $schema->resolveTarget(),
            $members,
            $schema
        );
    }

    /**
     * Convert an existing modeled structure into a document.
     *
     * @return self Schema-backed structure document.
     */
    public static function fromStruct(
        SerializableStructInterface $value,
        ?Schema $schema = null
    ): self
    {
        $valueSchema = $value->getSchema();
        $members = [];
        foreach ($valueSchema->getMembersByIndex() as $index => $member) {
            $members[$index] = $value->getMemberValue($member);
        }

        return new self(
            $valueSchema,
            $members,
            $schema ?? $valueSchema
        );
    }

    /**
     * Return this value with a different parent serialization schema.
     *
     * @return self Schema-backed structure document.
     */
    public function withSerializationSchema(Schema $schema): self
    {
        if ($this->schema === $schema) {
            return $this;
        }

        return new self(
            $this->valueSchema,
            $this->members,
            $schema
        );
    }

    /**
     * Return the document type.
     *
     * @return ShapeType Structure document type.
     */
    public function type(): ShapeType
    {
        return ShapeType::Structure;
    }

    /**
     * Return the canonical structure schema.
     *
     * @return Schema Structure schema.
     */
    public function getSchema(): Schema
    {
        return $this->valueSchema;
    }

    /**
     * Return a raw positional member value.
     *
     * @return mixed Raw member value, or null when absent.
     */
    public function getMemberValue(Schema $member): mixed
    {
        return $this->members[$member->memberIndex] ?? null;
    }

    /**
     * Return a copy with one positional member replaced.
     *
     * @return static Copied structure document.
     */
    public function withMemberValue(Schema $member, mixed $value): static
    {
        $members = $this->members;
        $members[$member->memberIndex] = $value;

        return new self(
            $this->valueSchema,
            $members,
            $this->schema
        );
    }

    /**
     * Return one member as a schema-backed document.
     *
     * @return AbstractDocument|null Member document, or null when absent.
     */
    public function getMember(string $name): ?AbstractDocument
    {
        $member = $this->valueSchema->getMember($name);
        if ($member === null) {
            return null;
        }

        return $this->getMemberDocument($member);
    }

    /**
     * Return every modeled member as a schema-backed document.
     *
     * @return array<string, AbstractDocument|null> Modeled member documents.
     */
    public function asMap(): array
    {
        $result = [];
        foreach ($this->valueSchema->getMembersByIndex() as $member) {
            $result[$member->memberName] = $this->getMemberDocument($member);
        }

        return $result;
    }

    /**
     * Serialize every present member.
     *
     * @return void
     */
    public function serializeMembers(
        ShapeSerializerInterface $serializer
    ): void
    {
        foreach ($this->valueSchema->getMembersByIndex() as $index => $member) {
            $document = $this->getMemberDocument($member);
            $document?->serialize($serializer, $index);
        }
    }

    /**
     * Dispatch into {@see ShapeSerializerInterface::serializeStruct()}.
     *
     * @return void
     */
    public function serialize(
        ShapeSerializerInterface $serializer,
        int $idx
    ): void
    {
        $serializer->serializeStruct(
            $this->schema,
            $this,
            $idx
        );
    }

    /**
     * Resolve and cache one positional member document.
     *
     * @return AbstractDocument|null Member document, or null when absent.
     */
    private function getMemberDocument(Schema $member): ?AbstractDocument
    {
        $index = $member->memberIndex;
        if (!\array_key_exists($index, $this->documents)) {
            $this->documents[$index] = AbstractDocument::fromSchemaValue(
                $member,
                $this->members[$index] ?? null
            );
        }

        return $this->documents[$index];
    }
}
