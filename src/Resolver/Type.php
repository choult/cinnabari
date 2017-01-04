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

use Datto\Cinnabari\Schema;
use Datto\Cinnabari\Parser;
use Datto\Cinnabari\Functions;

/**
 * A Resolver to add type information to the passed request
 *
 * @package Datto\Cinnabari\Resolver
 */
class Type implements ResolverInterface
{

    /**
     * The schema to be applied
     *
     * @var \Datto\Cinnabari\Schema
     */
    private $schema;

    /**
     * The definition for the language's functions
     *
     * @var \Datto\Cinnabari\Functions
     */
    private $functions;

    /**
     * Constructs a new Type Resolver
     *
     * @param \Datto\Cinnabari\Schema $schema       The main schema
     * @param \Datto\Cinnabari\Functions $functions The list of functions to support
     */
    public function __construct(Schema $schema, Functions $functions)
    {
        $this->schema = $schema;
        $this->functions = $functions;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(array $request)
    {
        return $this->applyDeep($request, array());
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
    private function applyDeep(array $token, array $context)
    {
        switch ($token[0]) {
            case Parser::TYPE_FUNCTION: {
                $context = $this->getFunctionContext($token, $context);
                $token[2] = $this->applyChildren($token[2], $context);
                /*if (count($token) > 3) {
                    // The context for a function's trailing arguments is the property path of the first argument
                    $context = $this->getFunctionContext($token, $context);
                    for ($i = 3; $i < count($token); $i++) {
                        $token[$i] = $this->applyChildren($token[$i], $context);
                    }
                }*/

                break;
            }
            case Parser::TYPE_OBJECT: {
                $token[1] = $this->applyChildren($token[1], $context);
                break;
            }
            case Parser::TYPE_PROPERTY: {
                $token[2] = $this->getPropertyType($token[1], $context);
                break;
            }
        }

        return $token;
    }

    /**
     * Gets the context for a given function
     *
     * @param array $function   The function for which to determine the context
     * @param array $context    The context that the function is already operating in
     *
     * @return array
     */
    private function getFunctionContext(array $function, array $context)
    {
        if (!count($function[2])) {
            return $context;
        }

        $leftMost = $function[2][0];

        switch ($leftMost[0]) {
            case Parser::TYPE_FUNCTION: {
                return $this->getFunctionContext($leftMost, $context);
                break;
            }
            case Parser::TYPE_PROPERTY: {
                \array_unshift($context, $leftMost[1]);
                return $context;
            }
        }

        return $context;
    }

    private function getFunctionType(array $function)
    {

    }

    /**
     * Gets the type of the passed token in the given context
     *
     * @param string $propertyName  The name of the property
     * @param array $context        The property context, descending through the tree
     *
     * @return mixed    Returns null when no property type is found
     */
    private function getPropertyType($propertyName, array $context)
    {
        $context[] = $propertyName;
        $property = $this->schema->getProperty(\implode('.', $context));
        return ($property !== null) ? $property[0] : null;
    }
}