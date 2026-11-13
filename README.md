# Smithy PHP Core

Runtime schemas, shape contracts, document values, and model conversion for
Smithy PHP. Requires PHP 8.2 or later.

## Installation

```shell
composer require smithy/core
```

## Runtime Schemas

`Schema` supplies runtime metadata for a Smithy shape. It stores the shape ID,
shape type, members, traits, target references, and generated target class.

```php
use Smithy\Core\Serde\Schema;
use Smithy\Core\Shape\ShapeType;

$schema = new Schema(
    id: 'example.weather#City',
    type: ShapeType::String_,
);
```

Schema extensions attach cached metadata used by codecs, protocols, endpoint
resolution, and pagination.

## Documents

The document hierarchy represents every Smithy data-model type. Empty lists
and empty maps remain distinct values.

```php
use Smithy\Core\Shape\Document\AbstractDocument;

$document = AbstractDocument::fromValue('Raleigh');

echo $document->asString();
```

Runtime-loaded structures and unions use `StructDocument` and
`UnionDocument`. Dynamic clients validate caller values against operation
schemas before creating these documents.

## Shape Contracts

| Contract | Responsibility |
|---|---|
| `SerializableShapeInterface` | Dispatch a value to a serializer |
| `SerializableStructInterface` | Expose structure schemas and members |
| `UnionInterface` | Expose a union tag, member index, and value |
| `EnumInterface` | Expose modeled enum values |
| `OperationInterface` | Describe operation schemas and runtime metadata |
| `ServiceInterface` | Describe a Smithy service |

`ShapeSerializerInterface` and `ShapeDeserializerInterface` define the typed
codec call surface.

## Transport and Endpoint Contracts

| Namespace | Responsibility |
|---|---|
| `Smithy\Core\Transport` | Transport, message exchange, and request/response contracts |
| `Smithy\Core\Endpoint` | Resolved `Endpoint` and `EndpointAuthScheme` values |

Protocols and transports depend on these contracts without depending on the
client runtime. Endpoint resolvers live in `Smithy\Client\Endpoint`.

## Sensitive Diagnostic Output

Generated structures and unions that can contain `@sensitive` data provide
schema-aware diagnostic representations. String casts, `print_r()`,
`var_dump()`, `json_encode()`, and PHP serialization redact sensitive members,
including values nested in collections and unions. Redacted PHP serialization
cannot be restored into a modeled value.

`var_export()`, reflection, and direct property inspection bypass these hooks
and can expose public sensitive members. Do not pass modeled objects containing
sensitive data to those APIs or to logging systems that inspect properties
directly.

## Runtime Models

`SmithyModel` loads a Smithy JSON AST. `SchemaConverter` turns model shapes
into runtime schemas. `TypeRegistry` resolves runtime shapes and errors from
those schemas. `SmithyModel::fromFile()` accepts only local regular files; use
`SmithyModel::fromJson()` for model contents supplied directly by callers. PHP
stream-wrapper URIs are not accepted as model file paths.

## Numeric Types

`BigInteger` and `BigDecimal` preserve values outside native PHP integer and
floating-point ranges. Codecs emit their validated numeric strings according
to the target wire format.

## Dependencies

This package depends only on PHP.

## Design

See [Core Runtime Design](../../docs/design/core/core-runtime-design.md).

## Contributing

Run the component tests from the monorepo root:

```shell
vendor/bin/phpunit tests/Core
```
