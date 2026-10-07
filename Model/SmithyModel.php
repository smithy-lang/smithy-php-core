<?php

declare(strict_types=1);

namespace Smithy\Core\Model;

/**
 * Read a Smithy JSON AST model at runtime.
 */
final class SmithyModel
{
    /**
     * Matches URI-style schemes that PHP would interpret as stream wrappers.
     */
    private const STREAM_WRAPPER_PATH_PATTERN =
        '/\A[A-Za-z][A-Za-z0-9+.-]*:\/\//D';

    /**
     * Matches Windows UNC and device namespace paths.
     */
    private const WINDOWS_UNC_OR_DEVICE_PATH_PATTERN = '~\A[\\\\/]{2}~D';

    private const RESOURCE_OPERATION_BINDINGS = [
        'create',
        'put',
        'read',
        'update',
        'delete',
        'list'
    ];

    private const PRELUDE_TYPES = [
        'smithy.api#Blob' => 'blob',
        'smithy.api#Boolean' => 'boolean',
        'smithy.api#Byte' => 'byte',
        'smithy.api#Short' => 'short',
        'smithy.api#Integer' => 'integer',
        'smithy.api#Long' => 'long',
        'smithy.api#Float' => 'float',
        'smithy.api#Double' => 'double',
        'smithy.api#BigInteger' => 'bigInteger',
        'smithy.api#BigDecimal' => 'bigDecimal',
        'smithy.api#String' => 'string',
        'smithy.api#Timestamp' => 'timestamp',
        'smithy.api#Document' => 'document',
        'smithy.api#Unit' => 'structure',
        'smithy.api#PrimitiveBoolean' => 'boolean',
        'smithy.api#PrimitiveByte' => 'byte',
        'smithy.api#PrimitiveShort' => 'short',
        'smithy.api#PrimitiveInteger' => 'integer',
        'smithy.api#PrimitiveLong' => 'long',
        'smithy.api#PrimitiveFloat' => 'float',
        'smithy.api#PrimitiveDouble' => 'double'
    ];

    private const ORIGINAL_SHAPE_ID_TRAITS = [
        'smithy.synthetic#originalShapeId',
        'smithy.api#originalShapeId'
    ];

    private const MIXIN_TRAIT = 'smithy.api#mixin';

    /**
     * @param array<string, array<string, mixed>> $shapes Modeled shapes.
     * @param array<string, mixed> $metadata Model metadata.
     */
    private function __construct(
        private readonly array $shapes,
        private readonly array $metadata
    ) {}

    /**
     * Load a Smithy JSON AST from a local regular file.
     *
     * @throws \RuntimeException When the path is not a local regular file or
     *                           cannot be read.
     */
    public static function fromFile(string $path): self
    {
        if (\str_contains($path, "\0")
            || \preg_match(self::STREAM_WRAPPER_PATH_PATTERN, $path) === 1
            || (DIRECTORY_SEPARATOR === '\\'
                && \preg_match(self::WINDOWS_UNC_OR_DEVICE_PATH_PATTERN, $path) === 1)
        ) {
            throw new \RuntimeException(
                'Smithy model path must reference a local file.'
            );
        }

        $resolvedPath = \realpath($path);
        if ($resolvedPath === false || !\is_file($resolvedPath)) {
            throw new \RuntimeException(
                "Failed to read Smithy model file: {$path}"
            );
        }

        \set_error_handler(static fn(): bool => true);
        try {
            $contents = \file_get_contents($resolvedPath);
        } finally {
            \restore_error_handler();
        }

        if ($contents === false) {
            throw new \RuntimeException(
                "Failed to read Smithy model file: {$path}"
            );
        }

        return self::fromJson($contents);
    }

    /**
     * Load a Smithy JSON AST string.
     *
     * @throws \JsonException When the JSON is invalid.
     */
    public static function fromJson(string $json): self
    {
        $model = \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($model)) {
            throw new \InvalidArgumentException(
                'Smithy model must decode to an object.'
            );
        }

        return self::fromArray($model);
    }

    /**
     * Load a decoded Smithy JSON AST model.
     *
     * @param array<string, mixed> $model Decoded model.
     *
     * @throws \InvalidArgumentException When the model is invalid.
     */
    public static function fromArray(array $model): self
    {
        if (!\is_string($model['smithy'] ?? null)) {
            throw new \InvalidArgumentException(
                'Smithy model is missing its version.'
            );
        }

        $shapes = $model['shapes'] ?? [];
        $metadata = $model['metadata'] ?? [];
        if (!\is_array($shapes) || !\is_array($metadata)) {
            throw new \InvalidArgumentException(
                'Smithy model shapes and metadata must be objects.'
            );
        }

        foreach ($shapes as $shapeId => $shape) {
            if (!\is_string($shapeId) || !\is_array($shape)) {
                throw new \InvalidArgumentException(
                    'Smithy model contains an invalid shape.'
                );
            }
        }

        return new self($shapes, $metadata);
    }

    /**
     * Return every modeled shape.
     *
     * @return array<string, array<string, mixed>> Shape ID to definition.
     */
    public function getShapes(): array
    {
        return $this->shapes;
    }

    /**
     * Return model metadata.
     *
     * @return array<string, mixed> Model metadata.
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Return a modeled or prelude shape.
     *
     * @return array<string, mixed>|null Shape definition, or null when absent.
     */
    public function getShape(string $shapeId): ?array
    {
        if (isset($this->shapes[$shapeId])) {
            return $this->shapes[$shapeId];
        }

        $type = self::PRELUDE_TYPES[$shapeId] ?? null;
        if ($type === null) {
            return null;
        }

        return ['type' => $type];
    }

    /**
     * Require a modeled or prelude shape.
     *
     * @return array<string, mixed> Shape definition.
     * @throws \InvalidArgumentException When the shape is absent.
     */
    public function expectShape(string $shapeId): array
    {
        $shape = $this->getShape($shapeId);
        if ($shape === null) {
            throw new \InvalidArgumentException(
                "Smithy shape not found: {$shapeId}"
            );
        }

        return $shape;
    }

    /**
     * Return a shape's modeled type.
     */
    public function getShapeType(string $shapeId): ?string
    {
        return $this->getShape($shapeId)['type'] ?? null;
    }

    /**
     * Return a shape's effective members.
     *
     * Local members override members inherited from mixins.
     *
     * @return array<string, array<string, mixed>> Member definitions.
     */
    public function getMembers(string $shapeId): array
    {
        $visited = [];

        return $this->resolveMembers($shapeId, $visited);
    }

    /**
     * Return a shape's effective traits.
     *
     * Local traits override traits inherited from mixins.
     *
     * @return array<string, mixed> Trait shape ID to raw value.
     */
    public function getTraits(string $shapeId): array
    {
        $visited = [];

        return $this->resolveTraits($shapeId, $visited);
    }

    /**
     * Return the authentication schemes effective for an operation.
     *
     * @return list<string> Scheme IDs in priority order.
     */
    public function getEffectiveAuthSchemeIds(
        string $serviceId,
        string $operationId
    ): array {
        $this->expectShapeType($serviceId, 'service');
        $this->expectShapeType($operationId, 'operation');

        return EffectiveAuthSchemes::resolve(
            $this->shapes,
            $this->getTraits($serviceId),
            $this->getTraits($operationId)
        );
    }

    /**
     * Return the original modeled shape ID after transforms.
     */
    public function getOriginalShapeId(string $shapeId): string
    {
        $traits = $this->getTraits($shapeId);
        foreach (self::ORIGINAL_SHAPE_ID_TRAITS as $traitId) {
            $original = $traits[$traitId] ?? null;
            if (\is_array($original)) {
                $original = $original['value'] ?? $original['shapeId'] ?? null;
            }

            if (\is_string($original) && $original !== 'smithy.api#Unit') {
                return $original;
            }
        }

        return $shapeId;
    }

    /**
     * Return the short name from a fully-qualified shape ID.
     */
    public static function getShapeName(string $shapeId): string
    {
        $separator = \strpos($shapeId, '#');

        return $separator === false
            ? $shapeId
            : \substr($shapeId, $separator + 1);
    }

    /**
     * Return every modeled service shape ID.
     *
     * @return list<string> Service shape IDs.
     */
    public function getServiceIds(): array
    {
        $services = [];
        foreach ($this->shapes as $shapeId => $shape) {
            if (($shape['type'] ?? null) === 'service') {
                $services[] = $shapeId;
            }
        }

        return $services;
    }

    /**
     * Return operations contained by a service and its resources.
     *
     * @return list<string> Operation shape IDs.
     */
    public function getServiceOperationIds(string $serviceId): array
    {
        $service = $this->expectShapeType($serviceId, 'service');
        $operations = [];
        $seenOperations = [];

        foreach ($service['operations'] ?? [] as $operation) {
            $this->appendOperationReference(
                $operation,
                $operations,
                $seenOperations
            );
        }

        $seenResources = [];
        foreach ($service['resources'] ?? [] as $resource) {
            $resourceId = \is_array($resource)
                ? $resource['target'] ?? null
                : null;
            if (\is_string($resourceId)) {
                $this->collectResourceOperations(
                    $resourceId,
                    $operations,
                    $seenOperations,
                    $seenResources
                );
            }
        }

        return $operations;
    }

    /**
     * Return errors declared directly by a service.
     *
     * @return list<string> Error shape IDs.
     */
    public function getServiceErrorIds(string $serviceId): array
    {
        $service = $this->expectShapeType($serviceId, 'service');

        return $this->getReferenceTargets($service['errors'] ?? []);
    }

    /**
     * Return errors declared directly by an operation.
     *
     * @return list<string> Error shape IDs.
     */
    public function getOperationErrorIds(string $operationId): array
    {
        $operation = $this->expectShapeType($operationId, 'operation');

        return $this->getReferenceTargets($operation['errors'] ?? []);
    }

    /**
     * Return an operation's input shape ID.
     */
    public function getOperationInputId(string $operationId): string
    {
        $operation = $this->expectShapeType($operationId, 'operation');
        $input = $operation['input']['target'] ?? null;

        return \is_string($input) ? $input : 'smithy.api#Unit';
    }

    /**
     * Return an operation's output shape ID.
     */
    public function getOperationOutputId(string $operationId): string
    {
        $operation = $this->expectShapeType($operationId, 'operation');
        $output = $operation['output']['target'] ?? null;

        return \is_string($output) ? $output : 'smithy.api#Unit';
    }

    /**
     * Resolve members inherited through mixins.
     *
     * @param array<string, true> $visited Shapes visited during resolution.
     *
     * @return array<string, array<string, mixed>> Member definitions.
     */
    private function resolveMembers(
        string $shapeId,
        array &$visited
    ): array
    {
        if (isset($visited[$shapeId])) {
            return [];
        }
        $visited[$shapeId] = true;

        $shape = $this->getShape($shapeId);
        if ($shape === null) {
            return [];
        }

        $members = [];
        foreach ($shape['mixins'] ?? [] as $mixin) {
            $mixinId = \is_array($mixin) ? $mixin['target'] ?? null : null;
            if (\is_string($mixinId)) {
                $members = \array_merge(
                    $members,
                    $this->resolveMembers($mixinId, $visited)
                );
            }
        }

        $localMembers = $shape['members'] ?? [];
        if (\is_array($localMembers)) {
            $members = \array_merge($members, $localMembers);
        }

        unset($visited[$shapeId]);

        return $members;
    }

    /**
     * Resolve traits inherited through mixins.
     *
     * @param array<string, true> $visited Shapes visited during resolution.
     *
     * @return array<string, mixed> Trait shape ID to raw value.
     */
    private function resolveTraits(
        string $shapeId,
        array &$visited
    ): array
    {
        if (isset($visited[$shapeId])) {
            return [];
        }
        $visited[$shapeId] = true;

        $shape = $this->getShape($shapeId);
        if ($shape === null) {
            return [];
        }

        $traits = [];
        foreach ($shape['mixins'] ?? [] as $mixin) {
            $mixinId = \is_array($mixin) ? $mixin['target'] ?? null : null;
            if (\is_string($mixinId)) {
                $traits = \array_merge(
                    $traits,
                    $this->resolveMixinTraits($mixinId, $visited)
                );
            }
        }

        $localTraits = $shape['traits'] ?? [];
        if (\is_array($localTraits)) {
            $traits = \array_merge($traits, $localTraits);
        }

        unset($visited[$shapeId]);

        return $traits;
    }

    /**
     * Resolve inherited mixin traits, excluding traits local to the mixin.
     *
     * @param array<string, true> $visited Shapes visited during resolution.
     *
     * @return array<string, mixed> Inheritable trait shape ID to raw value.
     */
    private function resolveMixinTraits(
        string $mixinId,
        array &$visited
    ): array
    {
        $traits = $this->resolveTraits($mixinId, $visited);
        $mixinTrait = $traits[self::MIXIN_TRAIT] ?? null;
        unset($traits[self::MIXIN_TRAIT]);

        $localTraits = \is_array($mixinTrait)
            ? $mixinTrait['localTraits'] ?? []
            : [];
        if (!\is_array($localTraits)) {
            return $traits;
        }

        foreach ($localTraits as $traitId) {
            if (\is_string($traitId)) {
                unset($traits[$traitId]);
            }
        }

        return $traits;
    }

    /**
     * Require a shape with the expected type.
     *
     * @return array<string, mixed> Shape definition.
     */
    private function expectShapeType(
        string $shapeId,
        string $expectedType
    ): array
    {
        $shape = $this->expectShape($shapeId);
        if (($shape['type'] ?? null) !== $expectedType) {
            throw new \InvalidArgumentException(
                "Shape {$shapeId} is not a {$expectedType}."
            );
        }

        return $shape;
    }

    /**
     * Return valid target IDs from modeled references.
     *
     * @param mixed $references Modeled shape references.
     *
     * @return list<string> Target shape IDs.
     */
    private function getReferenceTargets(mixed $references): array
    {
        if (!\is_array($references)) {
            return [];
        }

        $targets = [];
        foreach ($references as $reference) {
            $target = \is_array($reference)
                ? $reference['target'] ?? null
                : null;
            if (\is_string($target)) {
                $targets[] = $target;
            }
        }

        return $targets;
    }

    /**
     * Add a valid operation reference once.
     *
     * @param list<string> $operations Collected operation IDs.
     * @param array<string, true> $seenOperations Seen operation IDs.
     *
     * @return void
     */
    private function appendOperationReference(
        mixed $reference,
        array &$operations,
        array &$seenOperations
    ): void
    {
        $operationId = \is_array($reference)
            ? $reference['target'] ?? null
            : null;
        if (!\is_string($operationId) || isset($seenOperations[$operationId])) {
            return;
        }

        $operation = $this->getShape($operationId);
        if ($operation === null
            || ($operation['type'] ?? null) !== 'operation'
        ) {
            return;
        }

        $seenOperations[$operationId] = true;
        $operations[] = $operationId;
    }

    /**
     * Collect lifecycle and nested operations from a resource closure.
     *
     * @param list<string> $operations Collected operation IDs.
     * @param array<string, true> $seenOperations Seen operation IDs.
     * @param array<string, true> $seenResources Seen resource IDs.
     *
     * @return void
     */
    private function collectResourceOperations(
        string $resourceId,
        array &$operations,
        array &$seenOperations,
        array &$seenResources
    ): void
    {
        if (isset($seenResources[$resourceId])) {
            return;
        }
        $seenResources[$resourceId] = true;

        $resource = $this->getShape($resourceId);
        if ($resource === null || ($resource['type'] ?? null) !== 'resource') {
            return;
        }

        foreach (self::RESOURCE_OPERATION_BINDINGS as $binding) {
            $this->appendOperationReference(
                $resource[$binding] ?? null,
                $operations,
                $seenOperations
            );
        }

        foreach (
            ['operations', 'collectionOperations'] as $operationList
        ) {
            foreach ($resource[$operationList] ?? [] as $operation) {
                $this->appendOperationReference(
                    $operation,
                    $operations,
                    $seenOperations
                );
            }
        }

        foreach ($resource['resources'] ?? [] as $nestedResource) {
            $nestedResourceId = \is_array($nestedResource)
                ? $nestedResource['target'] ?? null
                : null;
            if (\is_string($nestedResourceId)) {
                $this->collectResourceOperations(
                    $nestedResourceId,
                    $operations,
                    $seenOperations,
                    $seenResources
                );
            }
        }
    }
}
