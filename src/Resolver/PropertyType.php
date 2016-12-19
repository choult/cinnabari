<?php

/**
 * Copyright (C) 2016 Datto, Inc.
 *
 * This file is part of Cinnabari.
 *
 * Cinnabari is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * Cinnabari is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with Cinnabari. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Christopher Hoult <choult@datto.com>
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 * @copyright 2016 Datto, Inc.
 */

namespace Datto\Cinnabari\Resolver;

use Datto\Cinnabari\Parser;

/**
 * A Resolver to add type information to all Property tags in the passed request
 *
 * @package Datto\Cinnabari\Resolver
 */
class PropertyType implements ResolverInterface
{

    /**
     * The schema to be applied
     *
     * @var array
     */
    private $schema;

    /**
     * A flattened list of types
     *
     * @var array|null
     */
    private $typeTree;

    /**
     * Constructs a new PropertyType Resolver
     *
     * @param array $schema The main schema; as this class will work with the data directly, there's no need to abstract
     *                      just the property information when passing it in.
     */
    public function __construct(array $schema)
    {
        $this->schema = $schema;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(array $request)
    {
        $this->applyDeep($request, array());
    }

    /**
     * Iterates over the passed array, applying this Resolver to each item in turn
     *
     * @param array $tokens
     * @param array $context
     *
     * @return array
     */
    private function applyChildren(array $tokens, array $context)
    {
        foreach ($tokens as $idx => $subToken) {
            $tokens[$idx] = $this->applyDeep($subToken, $context);
        }

        return $tokens;
    }

    /**
     * Inspects the passed token, and applies this Resolver appropriately
     *
     * @param array $token
     * @param array $context
     *
     * @return array
     */
    public function applyDeep(array $token, array $context)
    {
        switch ($token[0]) {
            case Parser::TYPE_FUNCTION: {
                $token[2] = $this->applyChildren($token[2], array());
                break;
            }
            case Parser::TYPE_OBJECT: {
                $token[1] = $this->applyChildren($token[1], array());
                break;
            }
            case Parser::TYPE_PROPERTY: {
                $token[2] = $this->getType($token[1], $context);
                break;
            }
        }

        return $token;
    }

    /**
     * Gets the type of the passed token in the given context
     *
     * @param string $propertyName  The name of the property
     * @param array $context        The property context, descending through the tree
     *
     * @return mixed    Returns null when no property type is found
     */
    private function getType($propertyName, array $context)
    {
        $position = $this->getTypeTree();
        foreach ($context as $node) {
            if (!isset($position[\strtolower($node)])) {
                return null;
            }
            $position = $position[\strtolower($node)];
        }

        return (isset($position[\strtolower($propertyName)])) ? $position[\strtolower($propertyName)] : null;
    }

    /**
     * Processes the class schema into a more normalized form for walking
     *
     * @return array
     */
    private function getTypeTree()
    {
        if ($this->typeTree === null) {
            $types = array();
            foreach ($this->schema['classes'] as $class => $properties) {
                $types[\strtolower($class)] = array();
                foreach ($properties as $property => $type) {
                    $types[\strtolower($class)][\strtolower($property)] = $type;
                }
            }
            $this->typeTree = $types;
        }

        return $this->typeTree;
    }
}