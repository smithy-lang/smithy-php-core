<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Runtime schema for a Smithy shape.
 *
 * Structure and union schemas hold member schemas that reference their targets, forming a graph
 * that mirrors the Smithy shape graph. This drives serialization and deserialization without
 * reflection.
 *
 * `$type` is stored as `ShapeType::$value` (int) so codec dispatch can switch on a literal-int
 * discriminant. Pass a {@see ShapeType} case to the constructor. The unwrap happens once, there.
 *
 * Traits are int-keyed. Access with `getTrait($key)` / `hasTrait($key)` using the `T_*` class
 * constants below. The packed storage layout is implementation detail.
 *
 * Schemas MUST store their ShapeId and ShapeType (per SEP requirements) and MUST NOT reference
 * generated data-object classes.
 */
final class Schema
{
    public const T_XML_NAME = 0;
    public const T_XML_NAMESPACE = 1;
    public const T_XML_ATTRIBUTE = 2;
    public const T_XML_FLATTENED = 3;
    public const T_TIMESTAMP_FORMAT = 4;
    public const T_HTTP_HEADER = 5;
    public const T_HTTP_LABEL = 6;
    public const T_HTTP_PAYLOAD = 7;
    public const T_HTTP_PREFIX_HEADERS = 8;
    public const T_HTTP_QUERY = 9;
    public const T_HTTP_QUERY_PARAMS = 10;
    public const T_HTTP_RESPONSE_CODE = 11;
    public const T_JSON_NAME = 12;
    public const T_MEDIA_TYPE = 13;
    public const T_REQUIRED = 14;
    public const T_SPARSE = 15;
    public const T_STREAMING = 16;
    public const T_HTTP = 17;
    public const T_ERRORS = 18;
    public const T_IDEMPOTENCY_TOKEN = 19;
    public const T_SENSITIVE = 20;
    public const T_HTTP_CHECKSUM_REQUIRED = 21;
    public const T_REQUEST_COMPRESSION = 22;
    public const T_ERROR = 23;
    public const T_READ_ONLY = 24;
    public const T_IDEMPOTENT = 25;
    public const T_RETRYABLE = 26;
    public const T_EC2_QUERY_NAME = 27;

    /** @var array<string, Schema> Member name => member Schema (for structures/unions) */
    private array $membersByName = [];
    /** @var array<int, Schema> Position index => member Schema */
    private array $membersByIndex = [];
    /** @var array<int, mixed> Lazily-computed extension data keyed by extension ID */
    private array $extensions = [];
    private int $memberCount;

    /**
     * Stored as `ShapeType::$value` (int) so codec dispatch can switch on a literal-int
     * discriminant. The constructor unwraps a {@see ShapeType} case exactly once.
     *
     * @var int
     */
    public readonly int $type;

    /**
     * @param string $id Fully-qualified ShapeId (e.g., "com.amazonaws.sts#AssumeRoleRequest").
     * @param ShapeType $type Shape type. For members, this is the TARGET type.
     * @param string|null $memberName Member name for member schemas, null for root schemas.
     * @param int|null $memberIndex Position index for this member (used by switch-dispatched
     *                 deserializers).
     * @param list<Schema> $members Member schemas (for structures/unions).
     * @param Schema|null $listMember Schema for list item type.
     * @param Schema|null $mapKey Schema for map key type.
     * @param Schema|null $mapValue Schema for map value type.
     * @param array<int, mixed> $traits Serde-relevant traits (int key => trait value).
     * @param string|null $targetClass FQCN of the target shape class for recursive references.
     *
     * @return void
     */
    public function __construct(
        public readonly string $id,
        ShapeType $type,
        public readonly ?string $memberName = null,
        public readonly ?int $memberIndex = null,
        array $members = [],
        public readonly ?Schema $listMember = null,
        public readonly ?Schema $mapKey = null,
        public readonly ?Schema $mapValue = null,
        private readonly array $traits = [],
        public readonly ?string $targetClass = null
    )
    {
        $this->type = $type->value;
        foreach ($members as $member) {
            $this->membersByName[$member->memberName] = $member;
            // Key by the member's own memberIndex rather than the
            // foreach key. The constructor accepts a list of member
            // schemas in any order (e.g. filtered subsets via
            // `withMembers`), so the positional index in `$members`
            // is unreliable. The member's intrinsic `memberIndex`
            // is what every downstream lookup is keyed on.
            $this->membersByIndex[$member->memberIndex] = $member;
        }
    }

    /**
     * Return true when this schema represents a member rather than a root shape.
     *
     * @return bool True when `$memberName` is set.
     */
    public function isMember(): bool
    {
        return $this->memberName !== null;
    }

    /**
     * Return the member schema for `$name`, or null when the struct has no such member.
     *
     * @param string $name Member name from the Smithy model.
     *
     * @return Schema|null Member schema, or null when absent.
     */
    public function getMember(string $name): ?Schema
    {
        return $this->membersByName[$name] ?? null;
    }

    /**
     * Return the member schema at position `$index`, or null when no member lives at that index.
     *
     * @param int $index Zero-based member position.
     *
     * @return Schema|null Member schema, or null when absent.
     */
    public function getMemberByIndex(int $index): ?Schema
    {
        return $this->membersByIndex[$index] ?? null;
    }

    /**
     * Return the name-keyed member map for this shape.
     *
     * @return array<string, Schema> Member name => Schema.
     */
    public function getMembers(): array
    {
        return $this->membersByName;
    }

    /**
     * Return the index-keyed member map for this shape.
     *
     * @return array<int, Schema> Position index => Schema.
     */
    public function getMembersByIndex(): array
    {
        return $this->membersByIndex;
    }

    /**
     * Return the list item schema. Null for non-list shapes.
     *
     * @return Schema|null List item schema, or null when the shape is not a list.
     */
    public function getListMember(): ?Schema
    {
        return $this->listMember;
    }

    /**
     * Return the map key schema. Null for non-map shapes.
     *
     * @return Schema|null Map key schema, or null when the shape is not a map.
     */
    public function getMapKey(): ?Schema
    {
        return $this->mapKey;
    }

    /**
     * Return the map value schema. Null for non-map shapes.
     *
     * @return Schema|null Map value schema, or null when the shape is not a map.
     */
    public function getMapValue(): ?Schema
    {
        return $this->mapValue;
    }

    /**
     * Return true when this schema carries the trait identified by `$traitKey`.
     *
     * @param int $traitKey Trait constant (Schema::T_*).
     *
     * @return bool True when a value is stored under `$traitKey`.
     */
    public function hasTrait(int $traitKey): bool
    {
        return isset($this->traits[$traitKey]);
    }

    /**
     * Return the trait value, or null when the trait is absent.
     *
     * @param int $traitKey Trait constant (Schema::T_*).
     *
     * @return mixed Trait value, or null when absent.
     */
    public function getTrait(int $traitKey): mixed
    {
        return $this->traits[$traitKey] ?? null;
    }

    /**
     * Return the full trait map for this schema.
     *
     * @return array<int, mixed> Trait key => trait value.
     */
    public function getTraits(): array
    {
        return $this->traits;
    }

    /**
     * Return the wire name for this member. Uses the `@jsonName` trait if present, otherwise the
     * member name.
     *
     * @return string Wire name for this member, or empty string when the schema is a root shape
     *                with no `@jsonName` trait.
     */
    public function getWireName(): string
    {
        return $this->traits[self::T_JSON_NAME] ?? $this->memberName ?? '';
    }

    /**
     * Return true when the `@required` trait is set on this member.
     *
     * @return bool True when `T_REQUIRED` is present in the trait map.
     */
    public function isRequired(): bool
    {
        return isset($this->traits[self::T_REQUIRED]);
    }

    /**
     * Return the number of members in this schema.
     *
     * @return int Member count (0 for non-aggregate shapes).
     */
    public function getMemberCount(): int
    {
        return $this->memberCount ??= \count($this->membersByName);
    }

    /**
     * Resolve the target schema for recursive references.
     *
     * When this member has `targetClass` set but no inline members, calls `targetClass::schema()`
     * to fetch the populated schema. Returns `$this` for already-populated schemas.
     *
     * @return self Resolved schema, `$this` when already populated, otherwise
     *              `targetClass::schema()`.
     */
    public function resolveTarget(): self
    {
        if ($this->targetClass !== null) {
            return ($this->targetClass)::schema();
        }

        return $this;
    }

    /**
     * Return lazily-computed extension data for `$key`.
     *
     * The key carries its own provider. Computed on first access, then cached. Null results are
     * cached correctly.
     *
     * @param SchemaExtensionKey $key Extension key identifying the provider that supplies the data.
     *
     * @return mixed Provider-computed extension data. May be null.
     */
    public function getExtension(SchemaExtensionKey $key): mixed
    {
        return $this->extensions[$key->id] ??= $key->provider->provide($this);
    }

    /**
     * Return a new Schema containing only the members at `$indices`.
     *
     * The protocol layer uses this to build a body-only schema that excludes HTTP-bound members.
     *
     * @param list<int> $indices Member indices to keep.
     *
     * @return self New Schema instance carrying only the requested members. Unknown indices are
     *              silently skipped.
     */
    public function withMembers(array $indices): self
    {
        $filtered = [];
        foreach ($indices as $idx) {
            if (isset($this->membersByIndex[$idx])) {
                $filtered[] = $this->membersByIndex[$idx];
            }
        }

        return new self(
            id: $this->id,
            type: ShapeType::from($this->type),
            memberName: $this->memberName,
            memberIndex: $this->memberIndex,
            members: $filtered,
            listMember: $this->listMember,
            mapKey: $this->mapKey,
            mapValue: $this->mapValue,
            traits: $this->traits
        );
    }
}
