<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Serde;

use Smithy\Core\Model\SmithyModel;
use Smithy\Core\Shape\ShapeType;

/**
 * Convert runtime Smithy model shapes into schemas.
 */
final class SchemaConverter
{
    /** @var array<string, Schema> Modeled shape ID to converted schema. */
    private array $schemas = [];

    /**
     * @var array<string, list<string>>|null Operation shape ID to service
     *     shape IDs.
     */
    private ?array $serviceIdsByOperation = null;

    /**
     * @param SmithyModel $model Runtime Smithy model.
     * @param SchemaTraitRegistry $traitRegistry Trait materializer.
     * @param array<string, string> $errorDiscriminatorTraits Error trait
     *     shape IDs mapped to their discriminator member names.
     */
    public function __construct(
        private readonly SmithyModel $model,
        private readonly SchemaTraitRegistry $traitRegistry =
            new SchemaTraitRegistry(),
        private readonly array $errorDiscriminatorTraits = []
    ) {}

    /**
     * Return a converted schema.
     */
    public function getSchema(string $shapeId): Schema
    {
        if (isset($this->schemas[$shapeId])) {
            return $this->schemas[$shapeId];
        }

        $building = [];

        return $this->convert($shapeId, $building);
    }

    /**
     * Return schemas converted so far.
     *
     * @return array<string, Schema> Modeled shape ID to schema.
     */
    public function getConvertedSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * Convert one modeled shape.
     *
     * @param array<string, true> $building Shapes currently being converted.
     */
    private function convert(
        string $shapeId,
        array &$building
    ): Schema
    {
        if (isset($this->schemas[$shapeId])) {
            return $this->schemas[$shapeId];
        }

        $shape = $this->model->expectShape($shapeId);
        $shapeType = $this->toShapeType($shape['type'] ?? null);
        $building[$shapeId] = true;

        $traitViews = $this->traitRegistry->materialize(
            $this->model->getTraits($shapeId)
        );
        if ($shapeType === ShapeType::Operation) {
            $this->prepareHttpTrait($shapeId, $traitViews);
            $this->prepareRequestCompressionTrait($traitViews);
            $errors = $this->createErrorMap($shapeId, $shape);
            if ($errors !== []) {
                $traitViews['dense'][Schema::T_ERRORS] = $errors;
            }
        }

        $schemaId = $this->model->getOriginalShapeId($shapeId);
        $members = [];
        $listMember = null;
        $mapKey = null;
        $mapValue = null;
        $enumValues = [];

        if ($shapeType === ShapeType::Structure
            || $shapeType === ShapeType::Union
        ) {
            $index = 0;
            foreach ($this->model->getMembers($shapeId) as $name => $member) {
                $members[] = $this->convertMember(
                    $schemaId,
                    $name,
                    $index++,
                    $member,
                    $building
                );
            }
        } elseif ($shapeType === ShapeType::List_) {
            $listMember = $this->convertMember(
                $schemaId,
                'member',
                0,
                $shape['member'] ?? [],
                $building
            );
        } elseif ($shapeType === ShapeType::Map) {
            $mapKey = $this->convertMember(
                $schemaId,
                'key',
                0,
                $shape['key'] ?? [],
                $building
            );
            $mapValue = $this->convertMember(
                $schemaId,
                'value',
                1,
                $shape['value'] ?? [],
                $building
            );
        } elseif (
            $shapeType === ShapeType::Enum
            || $shapeType === ShapeType::IntEnum
        ) {
            $enumValues = $this->convertEnumValues($shape);
        }

        $schema = new Schema(
            id: $schemaId,
            type: $shapeType,
            members: $members,
            listMember: $listMember,
            mapKey: $mapKey,
            mapValue: $mapValue,
            traits: $traitViews['dense'],
            traitsById: $traitViews['byId'],
            enumValues: $enumValues
        );

        $this->schemas[$shapeId] = $schema;
        unset($building[$shapeId]);

        return $schema;
    }

    /**
     * Convert a modeled member.
     *
     * @param array<string, mixed> $member Member definition.
     * @param array<string, true> $building Shapes currently being converted.
     */
    private function convertMember(
        string $containerId,
        string $memberName,
        int $memberIndex,
        array $member,
        array &$building
    ): Schema
    {
        $targetId = $member['target'] ?? null;
        if (!\is_string($targetId)) {
            throw new \InvalidArgumentException(
                "Member {$containerId}\${$memberName} has no target."
            );
        }

        $targetType = $this->toShapeType(
            $this->model->getShapeType($targetId)
        );
        $targetTraits = $this->model->getTraits($targetId);
        $memberTraits = $member['traits'] ?? [];
        if (!\is_array($memberTraits)) {
            throw new \InvalidArgumentException(
                "Member {$containerId}\${$memberName} has invalid traits."
            );
        }

        $inherited = $this->traitRegistry->materialize($targetTraits);
        unset($inherited['dense'][Schema::T_XML_NAME]);
        $direct = $this->traitRegistry->materialize($memberTraits);
        $effective = [
            'dense' => \array_replace(
                $inherited['dense'],
                $direct['dense']
            ),
            'byId' => \array_replace(
                $inherited['byId'],
                $direct['byId']
            )
        ];

        $target = isset($building[$targetId])
            ? new SchemaReference(
                fn(): Schema => $this->getSchema($targetId)
            )
            : $this->convert($targetId, $building);
        if ($target instanceof Schema) {
            $members = $target->getMembersByIndex();
            $listMember = $target->getListMember();
            $mapKey = $target->getMapKey();
            $mapValue = $target->getMapValue();
            $enumValues = $target->enumValues;
        } elseif ($targetType === ShapeType::List_
            || $targetType === ShapeType::Map
        ) {
            $members = [];
            $enumValues = [];
            [$listMember, $mapKey, $mapValue] =
                $this->convertRecursiveCollectionMembers(
                    $targetId,
                    $targetType,
                    $building
                );
        } else {
            $members = [];
            $listMember = null;
            $mapKey = null;
            $mapValue = null;
            $enumValues = [];
        }

        return new Schema(
            id: "{$containerId}\${$memberName}",
            type: $targetType,
            memberName: $memberName,
            memberIndex: $memberIndex,
            members: $members,
            listMember: $listMember,
            mapKey: $mapKey,
            mapValue: $mapValue,
            traits: $effective['dense'],
            directTraits: $direct['dense'],
            traitsById: $effective['byId'],
            directTraitsById: $direct['byId'],
            target: $target,
            enumValues: $enumValues
        );
    }

    /**
     * Convert shallow collection metadata for a recursive collection target.
     *
     * @param array<string, true> $building Shapes currently being converted.
     *
     * @return array{Schema|null, Schema|null, Schema|null}
     */
    private function convertRecursiveCollectionMembers(
        string $targetId,
        ShapeType $targetType,
        array &$building
    ): array
    {
        $shape = $this->model->expectShape($targetId);
        if ($targetType === ShapeType::List_) {
            $member = $shape['member'] ?? [];
            if (($member['target'] ?? null) === $targetId) {
                return [null, null, null];
            }

            return [
                $this->convertMember(
                    $targetId,
                    'member',
                    0,
                    $member,
                    $building
                ),
                null,
                null
            ];
        }
        if ($targetType === ShapeType::Map) {
            $key = $shape['key'] ?? [];
            $value = $shape['value'] ?? [];

            return [
                null,
                $this->convertMember(
                    $targetId,
                    'key',
                    0,
                    $key,
                    $building
                ),
                ($value['target'] ?? null) === $targetId
                    ? null
                    : $this->convertMember(
                        $targetId,
                        'value',
                        1,
                        $value,
                        $building
                    )
            ];
        }

        return [null, null, null];
    }

    /**
     * Convert an operation's modeled error references.
     *
     * @param string $operationId Operation shape ID.
     * @param array<string, mixed> $operation Operation definition.
     *
     * @return array<string, string> Discriminator to error shape ID.
     */
    private function createErrorMap(
        string $operationId,
        array $operation
    ): array
    {
        $references = [];
        foreach ($this->resolveServiceIdsForOperation($operationId)
            as $serviceId
        ) {
            $service = $this->model->getShape($serviceId);
            $serviceErrors = $service['errors'] ?? [];
            if (\is_array($serviceErrors)) {
                $references = \array_merge($references, $serviceErrors);
            }
        }

        $operationErrors = $operation['errors'] ?? [];
        if (\is_array($operationErrors)) {
            $references = \array_merge($references, $operationErrors);
        }

        $errors = [];
        foreach ($references as $error) {
            $targetId = \is_array($error) ? $error['target'] ?? null : null;
            if (!\is_string($targetId)) {
                continue;
            }

            $errors[$targetId] = $targetId;
            $separator = \strrpos($targetId, '#');
            if ($separator !== false) {
                $errors[\substr($targetId, $separator + 1)] = $targetId;
            }

            $traits = $this->model->getTraits($targetId);
            foreach ($this->errorDiscriminatorTraits
                as $traitId => $memberName
            ) {
                $trait = $traits[$traitId] ?? null;
                $discriminator = \is_array($trait)
                    ? $trait[$memberName] ?? null
                    : null;
                if (\is_string($discriminator)) {
                    $errors[$discriminator] = $targetId;
                }
            }
        }

        return $errors;
    }

    /**
     * Resolve services that contain an operation.
     *
     * @return list<string> Service shape IDs.
     */
    private function resolveServiceIdsForOperation(string $operationId): array
    {
        if ($this->serviceIdsByOperation === null) {
            $serviceIdsByOperation = [];
            foreach ($this->model->getServiceIds() as $serviceId) {
                foreach (
                    $this->model->getServiceOperationIds($serviceId)
                    as $containedOperationId
                ) {
                    $serviceIdsByOperation[$containedOperationId][] =
                        $serviceId;
                }
            }

            $this->serviceIdsByOperation = $serviceIdsByOperation;
        }

        return $this->serviceIdsByOperation[$operationId] ?? [];
    }

    /**
     * Compile runtime HTTP URI metadata for an operation.
     *
     * @param array{
     *     dense: array<int, mixed>,
     *     byId: array<string, mixed>
     * } $traitViews Materialized trait views.
     *
     * @return void
     */
    private function prepareHttpTrait(
        string $operationId,
        array &$traitViews
    ): void
    {
        $http = $traitViews['dense'][Schema::T_HTTP] ?? null;
        if (!\is_array($http)) {
            return;
        }

        $uri = $http['uri'] ?? null;
        if (!\is_string($uri)) {
            return;
        }

        [$path, $query] = \array_pad(
            \explode('?', $uri, 2),
            2,
            null
        );
        if (!isset($http['pathSegments']) && \str_contains($path, '{')) {
            $memberIndexes = [];
            $inputId = $this->model->getOperationInputId($operationId);
            $index = 0;
            foreach ($this->model->getMembers($inputId) as $name => $_) {
                $memberIndexes[$name] = $index++;
            }

            $http['pathSegments'] = $this->parsePathSegments(
                $path,
                $memberIndexes
            );
        }

        if (!isset($http['pathQueryString']) && $query !== null) {
            $fragments = [];
            foreach (\explode('&', $query) as $pair) {
                $parts = \explode('=', $pair, 2);
                $fragment = \rawurlencode($parts[0]);
                if (isset($parts[1])) {
                    $fragment .= '=' . \rawurlencode($parts[1]);
                }
                $fragments[] = $fragment;
            }
            $http['pathQueryString'] = \implode('&', $fragments);
        }

        $traitViews['dense'][Schema::T_HTTP] = $http;
        $traitViews['byId']['smithy.api#http'] = $http;
    }

    /**
     * Normalize request compression metadata to the generated schema shape.
     *
     * @param array{
     *     dense: array<int, mixed>,
     *     byId: array<string, mixed>
     * } $traitViews Materialized trait views.
     *
     * @return void
     */
    private function prepareRequestCompressionTrait(array &$traitViews): void
    {
        $requestCompression = $traitViews['dense'][
            Schema::T_REQUEST_COMPRESSION
        ] ?? null;
        if (!\is_array($requestCompression)) {
            return;
        }

        $encodings = $requestCompression['encodings'] ?? null;
        if (!\is_array($encodings)) {
            return;
        }

        $traitViews['dense'][Schema::T_REQUEST_COMPRESSION] = $encodings;
        $traitViews['byId']['smithy.api#requestCompression'] = $encodings;
    }

    /**
     * Parse an HTTP URI path into literal and label segments.
     *
     * @param array<string, int> $memberIndexes Input member indexes.
     *
     * @return list<array{?string, ?int, bool}> Parsed path segments.
     */
    private function parsePathSegments(
        string $path,
        array $memberIndexes
    ): array
    {
        $segments = [];
        $offset = 0;
        $length = \strlen($path);

        while ($offset < $length) {
            $open = \strpos($path, '{', $offset);
            if ($open === false) {
                $segments[] = [
                    \substr($path, $offset),
                    null,
                    false
                ];
                break;
            }

            if ($open > $offset) {
                $segments[] = [
                    \substr($path, $offset, $open - $offset),
                    null,
                    false
                ];
            }

            $close = \strpos($path, '}', $open);
            if ($close === false) {
                $segments[] = [
                    \substr($path, $open),
                    null,
                    false
                ];
                break;
            }

            $label = \substr($path, $open + 1, $close - $open - 1);
            $greedy = \str_ends_with($label, '+');
            if ($greedy) {
                $label = \substr($label, 0, -1);
            }
            if (!isset($memberIndexes[$label])) {
                throw new \InvalidArgumentException(
                    "HTTP label {$label} is not a member of the "
                    . "input for {$path}."
                );
            }

            $segments[] = [
                null,
                $memberIndexes[$label],
                $greedy
            ];
            $offset = $close + 1;
        }

        return $segments;
    }

    /**
     * Convert enum member declarations.
     *
     * @param array<string, mixed> $shape Enum definition.
     *
     * @return array<string, string|int> Member name to modeled value.
     */
    private function convertEnumValues(array $shape): array
    {
        $values = [];
        foreach ($shape['members'] ?? [] as $name => $member) {
            if (!\is_array($member)) {
                continue;
            }

            $value = ($member['traits'] ?? [])['smithy.api#enumValue']
                ?? $name;
            if (\is_string($value) || \is_int($value)) {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    /**
     * Convert a modeled shape type.
     *
     * @throws \InvalidArgumentException When the type is unsupported.
     */
    private function toShapeType(mixed $type): ShapeType
    {
        return match ($type) {
            'boolean' => ShapeType::Boolean,
            'byte' => ShapeType::Byte,
            'short' => ShapeType::Short,
            'integer' => ShapeType::Integer,
            'long' => ShapeType::Long,
            'float' => ShapeType::Float_,
            'double' => ShapeType::Double,
            'bigInteger' => ShapeType::BigInteger,
            'bigDecimal' => ShapeType::BigDecimal,
            'string' => ShapeType::String_,
            'blob' => ShapeType::Blob,
            'timestamp' => ShapeType::Timestamp,
            'list', 'set' => ShapeType::List_,
            'map' => ShapeType::Map,
            'structure' => ShapeType::Structure,
            'union' => ShapeType::Union,
            'enum' => ShapeType::Enum,
            'intEnum' => ShapeType::IntEnum,
            'document' => ShapeType::Document,
            'operation' => ShapeType::Operation,
            'service' => ShapeType::Service,
            'resource' => ShapeType::Resource,
            default => throw new \InvalidArgumentException(
                'Unsupported Smithy shape type: '
                . \var_export($type, true)
            )
        };
    }
}
