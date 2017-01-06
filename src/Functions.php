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
 * @copyright 2017 Datto, Inc.
 */

namespace Datto\Cinnabari;

use Datto\Cinnabari\Php\Output;

/**
 * A class to model Cinnabari functions and their definitions
 *
 * @package Datto\Cinnabari
 */
class Functions
{

    /**
     * A list of function signatures, indexed by function name
     *
     * @var array
     */
    private $signatures;

    /**
     * Constructs a new Functions object with an optional list of signature definitions.
     *
     * @param array|null $signatures    If null, the signature definitions stored within this class will be used
     */
    public function __construct(array $signatures = null)
    {
        $this->signatures = ($signatures) ? $signatures : $this->getSignatureList();
    }

    /**
     * Gets the signature for a given function
     *
     * @param $functionName     The name of the function to get the signature for
     * @param $argumentTypes    An array of input argument types
     *
     * @return integer|null
     */
    public function getSignature($functionName, array $argumentTypes)
    {
        $signatures = (isset($this->signatures[$functionName])) ? $this->signatures[$functionName] : null;
        if (!$signatures) {
            return null;
        }

        $ret = null;
        foreach ($signatures as $signature) {
            if ($signature['arguments'] === $argumentTypes) {
                return $signature;
            }
        }

        return null;
    }

    /**
     * Gets the output type for the passed function
     *
     * @param array $function
     *
     * @return array|null
     */
    public function getOutputType(array $function)
    {
        $argTypes = array();
        $list = null;
        $arguments = $function[2];
        foreach ($arguments as $argument) {
            $argType = null;
            switch ($argument[0]) {
                case Parser::TYPE_FUNCTION: {
                    $argType = $argument[3];
                    break;
                }
                case Parser::TYPE_OBJECT:
                case Parser::TYPE_PARAMETER:
                case Parser::TYPE_PROPERTY: {
                    $argType = $argument[2];
                    break;
                }
            }
            if (is_array($argType)) {
                $list = $argType[1];
                $argType = $argType[0];
            }
            $argTypes[] = $argType;
        }

        $signature = $this->getSignature($function[1], $argTypes);
        if (!$signature) {
            return null;
        }

        if ($signature['return'] == Output::TYPE_LIST) {
            return array(Output::TYPE_LIST, $list);
        }

        return $signature['return'];
    }

    /**
     * Gets a list of function signatures
     *
     * @TODO Make more readable/maintainable
     *
     * @return array
     */
    private function getSignatureList()
    {
        $anythingToList = array(
            array('arguments' => array(Output::TYPE_LIST, Output::TYPE_BOOLEAN), 'return' => Output::TYPE_LIST),
            array('arguments' => array(Output::TYPE_LIST, Output::TYPE_INTEGER), 'return' => Output::TYPE_LIST),
            array('arguments' => array(Output::TYPE_LIST, Output::TYPE_FLOAT), 'return' => Output::TYPE_LIST),
            array('arguments' => array(Output::TYPE_LIST, Output::TYPE_STRING), 'return' => Output::TYPE_LIST)
        );

        $aggregator = array(
            array('arguments' => array(Output::TYPE_INTEGER), 'return' => Output::TYPE_FLOAT),
            array('arguments' => array(Output::TYPE_FLOAT), 'return' => Output::TYPE_FLOAT)
        );

        $unaryBoolean = array(
            array('arguments' => array(Output::TYPE_BOOLEAN), 'return' => Output::TYPE_BOOLEAN)
        );

        $plus = array(
            array(
                'arguments' => array(Output::TYPE_INTEGER, Output::TYPE_INTEGER),
                'return' => Output::TYPE_INTEGER
            ),
            array(
                'arguments' => array(Output::TYPE_FLOAT, Output::TYPE_INTEGER),
                'return' => Output::TYPE_FLOAT
            ),
            array(
                'arguments' => array(Output::TYPE_INTEGER, Output::TYPE_FLOAT),
                'return' => Output::TYPE_FLOAT
            ),
            array(
                'arguments' => array(Output::TYPE_FLOAT, Output::TYPE_FLOAT),
                'return' => Output::TYPE_FLOAT
            ),
            array(
                'arguments' => array(Output::TYPE_STRING, Output::TYPE_STRING),
                'return' => Output::TYPE_STRING
            )
        );

        $numeric = array(
            array(
                'arguments' => array(Output::TYPE_INTEGER, Output::TYPE_INTEGER),
                'return' => Output::TYPE_INTEGER
            ),
            array(
                'arguments' => array(Output::TYPE_FLOAT, Output::TYPE_INTEGER),
                'return' => Output::TYPE_FLOAT
            ),
            array(
                'arguments' => array(Output::TYPE_INTEGER, Output::TYPE_FLOAT),
                'return' => Output::TYPE_FLOAT
            ),
            array(
                'arguments' => array(Output::TYPE_FLOAT, Output::TYPE_FLOAT),
                'return' => Output::TYPE_FLOAT
            )
        );

        $divides = array(
            array(
                'arguments' => array(Output::TYPE_INTEGER, Output::TYPE_INTEGER),
                'return' => Output::TYPE_FLOAT
            ),

            array(
                'arguments' => array(Output::TYPE_FLOAT, Output::TYPE_INTEGER),
                'return' => Output::TYPE_FLOAT
            ),

            array(
                'arguments' => array(Output::TYPE_INTEGER, Output::TYPE_FLOAT),
                'return' => Output::TYPE_FLOAT
            ),

            array(
                'arguments' => array(Output::TYPE_FLOAT, Output::TYPE_FLOAT),
                'return' => Output::TYPE_FLOAT
            )
        );

        $strictComparison = array(
            array(
                'arguments' => array(Output::TYPE_BOOLEAN, Output::TYPE_BOOLEAN),
                'return' => Output::TYPE_BOOLEAN
            ),
            array(
                'arguments' => array(Output::TYPE_INTEGER, Output::TYPE_INTEGER),
                'return' => Output::TYPE_BOOLEAN
            ),
            array(
                'arguments' => array(Output::TYPE_FLOAT, Output::TYPE_FLOAT),
                'return' => Output::TYPE_BOOLEAN
            ),
            array(
                'arguments' => array(Output::TYPE_STRING, Output::TYPE_STRING),
                'return' => Output::TYPE_BOOLEAN
            )
        );

        $binaryBoolean = array(
            array(
                'arguments' => array(Output::TYPE_BOOLEAN, Output::TYPE_BOOLEAN),
                'return' => Output::TYPE_BOOLEAN
            )
        );

        $comparison = array(
            array(
                'arguments' => array(Output::TYPE_INTEGER, Output::TYPE_INTEGER),
                'return' => Output::TYPE_BOOLEAN
            ),
            array(
                'arguments' => array(Output::TYPE_FLOAT, Output::TYPE_INTEGER),
                'return' => Output::TYPE_BOOLEAN
            ),
            array(
                'arguments' => array(Output::TYPE_INTEGER, Output::TYPE_FLOAT),
                'return' => Output::TYPE_BOOLEAN
            ),
            array(
                'arguments' => array(Output::TYPE_FLOAT, Output::TYPE_FLOAT),
                'return' => Output::TYPE_BOOLEAN
            ),
            array(
                'arguments' => array(Output::TYPE_STRING, Output::TYPE_STRING),
                'return' => Output::TYPE_BOOLEAN
            )
        );

        $stringFunction = array(
            array(
                'arguments' => array(Output::TYPE_STRING),
                'return' => Output::TYPE_STRING
            )
        );

        return array(
            'get' => $anythingToList,
            'average' => $aggregator,
            'sum' => $aggregator,
            'min' => $aggregator,
            'max' => $aggregator,
            'filter' => array(
                array(
                    'arguments' => array(Output::TYPE_LIST, Output::TYPE_BOOLEAN),
                    'return' => Output::TYPE_LIST
                )
            ),
            'sort' => $anythingToList,
            'slice' => array(
                array(
                    'arguments' => array(Output::TYPE_LIST, Output::TYPE_INTEGER, Output::TYPE_INTEGER),
                    'return' => Output::TYPE_LIST
                )
            ),
            'not' => $unaryBoolean,
            'plus' => $plus,
            'minus' => $numeric,
            'times' => $numeric,
            'divides' => $divides,
            'equal' => $strictComparison,
            'and' => $binaryBoolean,
            'or' => $binaryBoolean,
            'notEqual' => $strictComparison,
            'less' => $comparison,
            'lessEqual' => $comparison,
            'greater' => $comparison,
            'greaterEqual' => $comparison,
            'match' => array(
                array(
                    'arguments' => array(Output::TYPE_STRING, Output::TYPE_STRING),
                    'return' => Output::TYPE_BOOLEAN
                )
            ),
            'lowercase' => $stringFunction,
            'uppercase' => $stringFunction,
            'substring' => array(
                array(
                    'arguments' => array(Output::TYPE_STRING, Output::TYPE_INTEGER, Output::TYPE_INTEGER),
                    'return' => Output::TYPE_STRING
                )
            ),
            'length' => array(
                array(
                    'arguments' => array(Output::TYPE_STRING),
                    'return' => Output::TYPE_INTEGER
                )
            ),
            // TODO: this function is used internally by the type inferer to handle sets/inserts
            'assign' => $strictComparison
        );
    }
}