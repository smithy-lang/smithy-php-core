<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Serde\ShapeType;

/**
 * Document holding a string-keyed map of documents.
 *
 * Also serves as the natural representation for an open JSON object whose
 * shape is unknown at modeling time. Call `getMember()` to read entries by
 * name without going through `asMap()`.
 */
final class MapDocument extends AbstractDocument
{
    /**
     * @param array<string, AbstractDocument> $entries Key-to-document entries.
     * @param Schema|null $schema Schema to attach, or null for an untyped map.
     */
    public function __construct(
        public readonly array $entries,
        ?Schema $schema = null
    ) {
        parent::__construct($schema);
    }

    public function type(): ShapeType
    {
        return ShapeType::Map;
    }

    /**
     * @return array<string, AbstractDocument> Key-to-document entries.
     */
    public function asMap(): array
    {
        return $this->entries;
    }

    public function getMember(string $name): ?AbstractDocument
    {
        return $this->entries[$name] ?? null;
    }

    /** Dispatch into {@see ShapeSerializerInterface::serializeMap()}. */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeMap(
            $this->schema ?? PreludeSchemas::for($this->type()),
            $this->entries,
            $idx
        );
    }
}
