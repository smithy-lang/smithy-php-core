<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Shape\ShapeType;

/**
 * Document holding an ordered list of documents.
 *
 * Items may be heterogeneous when the document is untyped. Entries may also
 * be null. When a schema is attached, callers SHOULD supply items whose type
 * matches the schema's `listMember`, but the data-model does not enforce
 * homogeneity at construction.
 */
final class ListDocument extends AbstractDocument implements DocumentSettingsAwareInterface
{
    /**
     * @param list<AbstractDocument|null> $items Ordered member documents.
     * @param Schema|null $schema Schema to attach, or null for an untyped list.
     * @param DocumentSettingsInterface|null $settings Settings of the codec that read the
     *                    document, or null for user-built documents.
     */
    public function __construct(
        public readonly array $items,
        ?Schema $schema = null,
        private readonly ?DocumentSettingsInterface $settings = null
    )
    {
        parent::__construct($schema);
    }

    /**
     * Return the document type.
     *
     * @return ShapeType List document type.
     */
    public function type(): ShapeType
    {
        return ShapeType::List_;
    }

    /**
     * Return the settings of the codec that read this document.
     *
     * @return DocumentSettingsInterface|null Settings, or null for user-built documents.
     */
    public function getDocumentSettings(): ?DocumentSettingsInterface
    {
        return $this->settings;
    }

    /**
     * Return the list entries.
     *
     * @return list<AbstractDocument|null> Ordered member documents.
     */
    public function asList(): array
    {
        return $this->items;
    }

    /**
     * Dispatch into {@see ShapeSerializerInterface::serializeList()}.
     *
     * @return void
     */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeList(
            $this->schema ?? PreludeSchemas::for(ShapeType::List_),
            $this->items,
            $idx
        );
    }
}
