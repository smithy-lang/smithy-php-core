<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

use Smithy\Core\Shape\ShapeType;

/**
 * Runtime schema for a Smithy shape.
 *
 * Structure and union schemas hold member schemas that reference their targets, forming a graph
 * that mirrors the Smithy shape graph. This drives serialization and deserialization without
 * reflection.
 *
 * `$type` is stored as `ShapeType::$value` (int) so codec dispatch can switch on a literal-int
 * discriminant. Pass a {@see \Smithy\Core\Shape\ShapeType} case to the constructor. The unwrap
 * happens once, there.
 *
 * Traits are int-keyed on the serde hot path. Runtime model schemas also
 * retain shape-ID keyed and direct-member trait views.
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
    public const T_HTTP_CHECKSUM = 28;

    /** @var array<string, int> */
    private const TRAIT_KEYS_BY_ID = [
        'smithy.api#xmlName' => self::T_XML_NAME,
        'smithy.api#xmlNamespace' => self::T_XML_NAMESPACE,
        'smithy.api#xmlAttribute' => self::T_XML_ATTRIBUTE,
        'smithy.api#xmlFlattened' => self::T_XML_FLATTENED,
        'smithy.api#timestampFormat' => self::T_TIMESTAMP_FORMAT,
        'smithy.api#httpHeader' => self::T_HTTP_HEADER,
        'smithy.api#httpLabel' => self::T_HTTP_LABEL,
        'smithy.api#httpPayload' => self::T_HTTP_PAYLOAD,
        'smithy.api#httpPrefixHeaders' => self::T_HTTP_PREFIX_HEADERS,
        'smithy.api#httpQuery' => self::T_HTTP_QUERY,
        'smithy.api#httpQueryParams' => self::T_HTTP_QUERY_PARAMS,
        'smithy.api#httpResponseCode' => self::T_HTTP_RESPONSE_CODE,
        'smithy.api#jsonName' => self::T_JSON_NAME,
        'smithy.api#mediaType' => self::T_MEDIA_TYPE,
        'smithy.api#required' => self::T_REQUIRED,
        'smithy.api#sparse' => self::T_SPARSE,
        'smithy.api#streaming' => self::T_STREAMING,
        'smithy.api#http' => self::T_HTTP,
        'smithy.api#idempotencyToken' => self::T_IDEMPOTENCY_TOKEN,
        'smithy.api#sensitive' => self::T_SENSITIVE,
        'smithy.api#httpChecksumRequired' =>
            self::T_HTTP_CHECKSUM_REQUIRED,
        'smithy.api#requestCompression' => self::T_REQUEST_COMPRESSION,
        'smithy.api#error' => self::T_ERROR,
        'smithy.api#readonly' => self::T_READ_ONLY,
        'smithy.api#idempotent' => self::T_IDEMPOTENT,
        'smithy.api#retryable' => self::T_RETRYABLE
    ];

    /** @var array<int, string> */
    private const TRAIT_IDS_BY_KEY = [
        self::T_XML_NAME => 'smithy.api#xmlName',
        self::T_XML_NAMESPACE => 'smithy.api#xmlNamespace',
        self::T_XML_ATTRIBUTE => 'smithy.api#xmlAttribute',
        self::T_XML_FLATTENED => 'smithy.api#xmlFlattened',
        self::T_TIMESTAMP_FORMAT => 'smithy.api#timestampFormat',
        self::T_HTTP_HEADER => 'smithy.api#httpHeader',
        self::T_HTTP_LABEL => 'smithy.api#httpLabel',
        self::T_HTTP_PAYLOAD => 'smithy.api#httpPayload',
        self::T_HTTP_PREFIX_HEADERS => 'smithy.api#httpPrefixHeaders',
        self::T_HTTP_QUERY => 'smithy.api#httpQuery',
        self::T_HTTP_QUERY_PARAMS => 'smithy.api#httpQueryParams',
        self::T_HTTP_RESPONSE_CODE => 'smithy.api#httpResponseCode',
        self::T_JSON_NAME => 'smithy.api#jsonName',
        self::T_MEDIA_TYPE => 'smithy.api#mediaType',
        self::T_REQUIRED => 'smithy.api#required',
        self::T_SPARSE => 'smithy.api#sparse',
        self::T_STREAMING => 'smithy.api#streaming',
        self::T_HTTP => 'smithy.api#http',
        self::T_IDEMPOTENCY_TOKEN => 'smithy.api#idempotencyToken',
        self::T_SENSITIVE => 'smithy.api#sensitive',
        self::T_HTTP_CHECKSUM_REQUIRED =>
            'smithy.api#httpChecksumRequired',
        self::T_REQUEST_COMPRESSION => 'smithy.api#requestCompression',
        self::T_ERROR => 'smithy.api#error',
        self::T_READ_ONLY => 'smithy.api#readonly',
        self::T_IDEMPOTENT => 'smithy.api#idempotent',
        self::T_RETRYABLE => 'smithy.api#retryable'
    ];

    /** @var array<string, Schema> Member name => member Schema (for structures/unions) */
    private array $membersByName = [];
    /** @var array<int, Schema> Position index => member Schema */
    private array $membersByIndex = [];
    /** @var array<int, Schema> Members ordered safely for serialization. */
    private array $serializationMembersByIndex;
    /** @var array<int, mixed> Lazily-computed extension data keyed by extension ID */
    private array $extensions = [];
    private int $memberCount;
    /**
     * Stored as `ShapeType::$value` (int) so codec dispatch can switch on a literal-int
     * discriminant. The constructor unwraps a {@see \Smithy\Core\Shape\ShapeType} case exactly
     * once.
     *
     * @var int
     */
    public readonly int $type;

    /**
     * @param string $id Fully-qualified ShapeId (e.g., "example.weather#GetForecastInput").
     * @param ShapeType $type Shape type. For members, this is the TARGET type.
     * @param string|null $memberName Member name for member schemas, null for root schemas.
     * @param int|null $memberIndex Position index for this member (used by switch-dispatched
     *                 deserializers).
     * @param list<Schema> $members Member schemas (for structures/unions).
     * @param Schema|null $listMember Schema for list item type.
     * @param Schema|null $mapKey Schema for map key type.
     * @param Schema|null $mapValue Schema for map value type.
     * @param array<int, mixed> $traits Serde-relevant traits (int key => trait value).
     * @param array<int, mixed> $directTraits Traits applied directly to this member.
     * @param array<string, mixed> $traitsById Effective traits keyed by shape ID.
     * @param array<string, mixed> $directTraitsById Direct traits keyed by shape ID.
     * @param Schema|SchemaReference|null $target Runtime target for model-derived references.
     * @param string|null $targetClass FQCN of the generated target shape class.
     * @param array<string, string|int> $enumValues Enum member name to modeled value.
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
        private readonly array $directTraits = [],
        private readonly array $traitsById = [],
        private readonly array $directTraitsById = [],
        public readonly Schema|SchemaReference|null $target = null,
        public readonly ?string $targetClass = null,
        public readonly array $enumValues = []
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
     * Return members in serialization order.
     *
     * XML attributes must precede child elements. Other schemas retain their
     * modeled declaration order.
     *
     * @return array<int, Schema> Position index => member Schema.
     */
    public function getSerializationMembersByIndex(): array
    {
        if (isset($this->serializationMembersByIndex)) {
            return $this->serializationMembersByIndex;
        }

        $attributes = [];
        $elements = [];
        foreach ($this->membersByIndex as $index => $member) {
            if ($member->hasTrait(self::T_XML_ATTRIBUTE)) {
                $attributes[$index] = $member;
            } else {
                $elements[$index] = $member;
            }
        }

        return $this->serializationMembersByIndex = $attributes === []
            ? $this->membersByIndex
            : $attributes + $elements;
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
     * Return true when an effective trait is present by shape ID.
     */
    public function hasTraitById(string $traitId): bool
    {
        if (isset($this->traitsById[$traitId])) {
            return true;
        }

        $traitKey = self::TRAIT_KEYS_BY_ID[$traitId] ?? null;

        return $traitKey !== null && isset($this->traits[$traitKey]);
    }

    /**
     * Return an effective trait by shape ID.
     */
    public function getTraitById(string $traitId): mixed
    {
        if (\array_key_exists($traitId, $this->traitsById)) {
            return $this->traitsById[$traitId];
        }

        $traitKey = self::TRAIT_KEYS_BY_ID[$traitId] ?? null;

        return $traitKey === null
            ? null
            : ($this->traits[$traitKey] ?? null);
    }

    /**
     * Return true when a trait was applied directly to this schema.
     */
    public function hasDirectTrait(int $traitKey): bool
    {
        if (isset($this->directTraits[$traitKey])) {
            return true;
        }

        return !$this->isMember() && isset($this->traits[$traitKey]);
    }

    /**
     * Return a trait applied directly to this schema.
     */
    public function getDirectTrait(int $traitKey): mixed
    {
        if (\array_key_exists($traitKey, $this->directTraits)) {
            return $this->directTraits[$traitKey];
        }

        return $this->isMember()
            ? null
            : ($this->traits[$traitKey] ?? null);
    }

    /**
     * Return a directly applied trait by shape ID.
     */
    public function getDirectTraitById(string $traitId): mixed
    {
        if (\array_key_exists($traitId, $this->directTraitsById)) {
            return $this->directTraitsById[$traitId];
        }

        if ($this->isMember()) {
            return null;
        }

        return $this->getTraitById($traitId);
    }

    /**
     * Return true when a trait shape ID was applied directly.
     */
    public function hasDirectTraitById(string $traitId): bool
    {
        if (isset($this->directTraitsById[$traitId])) {
            return true;
        }

        if (!$this->isMember()) {
            return $this->hasTraitById($traitId);
        }

        $traitKey = self::TRAIT_KEYS_BY_ID[$traitId] ?? null;

        return $traitKey !== null && isset(
            $this->directTraits[$traitKey]
        );
    }

    /**
     * Return directly applied dense traits.
     *
     * @return array<int, mixed> Dense direct-trait table.
     */
    public function getDirectTraits(): array
    {
        return $this->isMember() ? $this->directTraits : $this->traits;
    }

    /**
     * Return shape-ID keyed effective traits.
     *
     * @return array<string, mixed> Trait shape ID to value.
     */
    public function getTraitsById(): array
    {
        return $this->mergeDenseTraitsById(
            $this->traits,
            $this->traitsById
        );
    }

    /**
     * Return shape-ID keyed direct traits.
     *
     * @return array<string, mixed> Trait shape ID to value.
     */
    public function getDirectTraitsById(): array
    {
        if (!$this->isMember()) {
            return $this->getTraitsById();
        }

        return $this->mergeDenseTraitsById(
            $this->directTraits,
            $this->directTraitsById
        );
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
     * Runtime references resolve through their direct or lazy schema target.
     * Generated references call `targetClass::schema()`. Returns `$this` for
     * already-populated schemas.
     *
     * @return self Resolved schema, `$this` when already populated, otherwise
     *              `targetClass::schema()`.
     */
    public function resolveTarget(): self
    {
        if ($this->target instanceof SchemaReference) {
            return $this->target->resolve();
        }

        if ($this->target instanceof self) {
            return $this->target;
        }

        if ($this->targetClass !== null) {
            return ($this->targetClass)::schema();
        }

        return $this;
    }

    /**
     * Return true when this schema can resolve to another schema.
     *
     * @return bool True for runtime or generated target references.
     */
    public function hasTarget(): bool
    {
        return $this->target !== null || $this->targetClass !== null;
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
            traits: $this->traits,
            directTraits: $this->directTraits,
            traitsById: $this->traitsById,
            directTraitsById: $this->directTraitsById,
            target: $this->target,
            targetClass: $this->targetClass,
            enumValues: $this->enumValues
        );
    }

    /**
     * Merge dense built-in traits into a shape-ID keyed trait view.
     *
     * @param array<int, mixed> $dense Dense traits.
     * @param array<string, mixed> $byId Existing shape-ID keyed traits.
     *
     * @return array<string, mixed> Trait shape ID to value.
     */
    private function mergeDenseTraitsById(
        array $dense,
        array $byId
    ): array
    {
        foreach ($dense as $traitKey => $value) {
            if ($value === null) {
                continue;
            }

            $traitId = self::TRAIT_IDS_BY_KEY[$traitKey] ?? null;
            if ($traitId !== null && !\array_key_exists($traitId, $byId)) {
                $byId[$traitId] = $value;
            }
        }

        return $byId;
    }
}
