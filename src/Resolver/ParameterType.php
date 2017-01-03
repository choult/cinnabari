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
use Datto\Cinnabari\Schema;

/**
 * A Resolver to add type information to all Parameters and Functions in the passed request
 *
 * @package Datto\Cinnabari\Resolver
 */
class ParameterType implements ResolverInterface
{

    /**
     * {@inheritdoc}
     */
    public function apply(array $request)
    {
        return $this->applyDeep($request);
    }

    /**
     * Iterates over the passed array, applying this Resolver to each item in turn
     *
     * @param array $tokens
     *
     * @return array
     */
    private function applyChildren(array $tokens)
    {
        foreach ($tokens as $idx => $subToken) {
            $tokens[$idx] = $this->applyDeep($subToken);
        }

        return $tokens;
    }

    /**
     * Inspects the passed token, and applies this Resolver appropriately
     *
     * @param array $token
     *
     * @return array
     */
    private function applyDeep(array $token)
    {
        switch ($token[0]) {
            case Parser::TYPE_FUNCTION: {
                for ($i = 2; $i < count($token); $i++) {
                    $token[$i] = $this->applyChildren($token[$i]);
                }
                break;
            }
            case Parser::TYPE_OBJECT: {
                $token[1] = $this->applyChildren($token[1]);
                break;
            }
        }

        return $token;
    }

    private function getFunctionSignature($functionName)
    {

    }
}