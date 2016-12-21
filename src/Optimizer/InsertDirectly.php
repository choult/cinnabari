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

namespace Datto\Cinnabari\Optimizer;

use Datto\Cinnabari\Parser;

/**
 * An optimizer to collapse sort, slice and filter functions when the request method is "insert"
 *
 * Rule: "insert(f(a, b), c) => insert(a, c)" when "f" is the "sort", "slice", or "filter" function
 *   Note: this rule may apply more than once during the simplification of a request
 *   Note: if this rule doesn't apply, then the request should be left unchanged
*/
class InsertDirectly
{
    public function optimize($token)
    {
        switch ($token[0]) {
            case Parser::TYPE_FUNCTION: {
                if ($token[1] == 'insert' && count($token[2]) > 1) {
                    $token[2][0] = $this->collapse($token[2][0]);
                }
                break;
            }
            case Parser::TYPE_OBJECT: {
                foreach ($token[1] as $idx => $subtoken) {
                    $token[1][$idx] = $this->optimize($subtoken);
                }
                break;
            }
        }

        return $token;
    }

    private function collapse($token)
    {
        if (
            $token[0] == Parser::TYPE_FUNCTION
            && in_array($token[1], array("sort", "slice", "filter"))
            && $this->isValid($token)
        ) {
            return $this->collapse($token[2][0]);
        }

        return $token;
    }

    private function isValid($token)
    {
        switch ($token[0]) {
            case Parser::TYPE_OBJECT: {
                foreach ($token[1] as $subToken) {
                    if (!$this->isValid($subToken)) {
                        return false;
                    }
                }
                return true;
            }
            case Parser::TYPE_FUNCTION: {
                if (count($token) < 3) {
                    return false;
                }
                for ($i = 2; $i < count($token); $i++) {
                    if (!count($token[$i])) {
                        return false;
                    }
                    foreach ($token[$i] as $subToken) {
                        if (!$this->isValid($subToken)) {
                            return false;
                        }
                    }
                }
                return true;
            }
            case Parser::TYPE_PROPERTY:
            case Parser::TYPE_PARAMETER: {
                return (count($token) == 2 && $token[1]);
            }
        }

        return true;
    }
}
