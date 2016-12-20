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

namespace Datto\Cinnabari\Tests;

use \Mockery;
use Datto\Cinnabari\Parser;
use Datto\Cinnabari\Schema;
use Datto\Cinnabari\Resolver\PropertyType;
use Datto\Cinnabari\Resolver\ResolverInterface;

/**
 * @coversDefaultClass \Datto\Cinnabari\Resolver\PropertyType
 */
class PropertyTypeTest extends \PHPUnit_Framework_TestCase
{

    /**
     * DataProvider for testApply
     *
     * @return array
     */
    public function applyProvider()
    {
        return array(
            'Simple...' => array(
                'schemaData' => array(
                    'devices' => array('Device', 'Devices'),
                ),
                'request' => array(
                    Parser::TYPE_FUNCTION,
                    'get',
                    array(
                        array(Parser::TYPE_PROPERTY, 'devices')
                    )
                ),
                'expected' => array(
                    Parser::TYPE_FUNCTION,
                    'get',
                    array(
                        array(Parser::TYPE_PROPERTY, 'devices', 'Device')
                    )
                ),
            ),
            'Deeper...' => array(
                'schemaData' => array(
                    'devices' => array('Device', 'Devices'),
                    'devices.id' => array(Schema::TYPE_INTEGER, 'Id')
                ),
                'request' => array(
                    Parser::TYPE_FUNCTION,
                    'get',
                    array(
                        array(
                            Parser::TYPE_FUNCTION,
                            'filter',
                            array(
                                array (Parser::TYPE_PROPERTY, 'devices')
                            ),
                            array(
                                array(
                                    Parser::TYPE_FUNCTION,
                                    'equals',
                                    array(
                                        array(Parser::TYPE_PROPERTY, 'id')
                                    ),
                                    array(
                                        array(Parser::TYPE_PARAMETER, 'id')
                                    ),
                                )
                            ),
                        )
                    )
                ),
                'expected' => array(
                    Parser::TYPE_FUNCTION,
                    'get',
                    array(
                        array(
                            Parser::TYPE_FUNCTION,
                            'filter',
                            array(
                                array (Parser::TYPE_PROPERTY, 'devices', 'Device')
                            ),
                            array(
                                array(
                                    Parser::TYPE_FUNCTION,
                                    'equals',
                                    array(
                                        array(Parser::TYPE_PROPERTY, 'id', Schema::TYPE_INTEGER)
                                    ),
                                    array(
                                        array(Parser::TYPE_PARAMETER, 'id')
                                    ),
                                )
                            ),
                        )
                    )
                ),
            )
        );
    }

    /**
     * @dataProvider applyProvider
     *
     * @covers ::apply
     *
     * @param array $schemaData
     * @param array $request
     * @param array $expected
     */
    public function testApply(array $schemaData, array $request, array $expected)
    {
        $schema = \Mockery::mock('\Datto\Cinnabari\Schema');
        foreach ($schemaData as $key => $response) {
            $schema->shouldReceive('getProperty')
                ->with($key)
                ->andReturn($response);
        }

        $resolver = new PropertyType($schema);
        $this->assertEquals($expected, $resolver->apply($request));
    }
}