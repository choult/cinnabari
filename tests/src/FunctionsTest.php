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

namespace Datto\Cinnabari\Tests;

use \Mockery;
use Datto\Cinnabari\Functions;
use Datto\Cinnabari\Php\Output;
use Datto\Cinnabari\Parser;

/**
 * @coversDefaultClass \Datto\Cinnabari\Functions
 */
class FunctionsTest extends \PHPUnit_Framework_TestCase
{

    /**
     * DataProvider for testGetSignature
     *
     * @return array
     */
    public function getSignatureProvider()
    {
        return array(
            'Sort' => array(
                'functionName' => 'sort',
                'argumentTypes' => array(Output::TYPE_LIST, Output::TYPE_BOOLEAN),
                'expected' => array(
                    'arguments' => array(Output::TYPE_LIST, Output::TYPE_BOOLEAN),
                    'return' => Output::TYPE_LIST
                )
            ),
            'Equals' => array(
                'functionName' => 'equal',
                'argumentTypes' => array(Output::TYPE_BOOLEAN, Output::TYPE_BOOLEAN),
                'expected' => array(
                    'arguments' => array(Output::TYPE_BOOLEAN, Output::TYPE_BOOLEAN),
                    'return' => Output::TYPE_BOOLEAN
                )
            ),
            'Integer Equals' => array(
                'functionName' => 'equal',
                'argumentTypes' => array(Output::TYPE_INTEGER, Output::TYPE_INTEGER),
                'expected' => array(
                    'arguments' => array(Output::TYPE_INTEGER, Output::TYPE_INTEGER),
                    'return' => Output::TYPE_BOOLEAN
                )
            ),
            'Less Than mixed' => array(
                'functionName' => 'less',
                'argumentTypes' => array(Output::TYPE_INTEGER, Output::TYPE_FLOAT),
                'expected' => array(
                    'arguments' => array(Output::TYPE_INTEGER, Output::TYPE_FLOAT),
                    'return' => Output::TYPE_BOOLEAN
                )
            ),
            'Equals type mismatch' => array(
                'functionName' => 'equal',
                'argumentTypes' => array(Output::TYPE_BOOLEAN, Output::TYPE_STRING),
                'expected' => null
            )
        );
    }

    /**
     * @dataProvider getSignatureProvider
     *
     * @covers ::getSignature
     * @covers ::getSignatureList
     *
     * @param string $functionName
     * @param array $argumentTypes
     * @param mixed $expected
     */
    public function testGetSignature($functionName, array $argumentTypes, $expected)
    {
        $functions = new Functions();
        $this->assertEquals($expected, $functions->getSignature($functionName, $argumentTypes));
    }

    /**
     * DataProvider for testGetOutputType
     *
     * @return array
     */
    public function getOutputTypeProvider()
    {
        return array(
            'Happy path 1' => array(
                'signatures' => array(
                    'equal' => array(array(
                        'arguments' => array(Output::TYPE_INTEGER, Output::TYPE_INTEGER),
                        'return' => Output::TYPE_BOOLEAN
                    ))
                ),
                'function' => array(
                    Parser::TYPE_FUNCTION,
                    'equal',
                    array(
                        array(Parser::TYPE_PROPERTY, 'one', Output::TYPE_INTEGER),
                        array(Parser::TYPE_PROPERTY, 'two', Output::TYPE_INTEGER),
                    )
                ),
                'expected' => Output::TYPE_BOOLEAN
            ),
            'Happy list path' => array(
                'signatures' => array(
                    'sort' => array(array(
                        'arguments' => array(Output::TYPE_LIST, Output::TYPE_INTEGER),
                        'return' => Output::TYPE_LIST
                    ))
                ),
                'function' => array(
                    Parser::TYPE_FUNCTION,
                    'sort',
                    array(
                        array(
                            Parser::TYPE_PROPERTY,
                            'listname',
                            array(Output::TYPE_LIST, array('one' => 'two', 'three' => 'four'))
                        ),
                        array(Parser::TYPE_PROPERTY, 'two', Output::TYPE_INTEGER),
                    )
                ),
                'expected' => array(Output::TYPE_LIST, array('one' => 'two', 'three' => 'four'))
            )
        );
    }

    /**
     * @dataProvider getOutputTypeProvider
     *
     * @covers ::getOutputType
     * @covers ::__construct
     *
     * @param array $signatures
     * @param array $function
     * @param mixed $expected
     */
    public function testGetOutputType(array $signatures, array $function, $expected)
    {
        $functions = new Functions($signatures);
        $this->assertEquals($expected, $functions->getOutputType($function));
    }
}