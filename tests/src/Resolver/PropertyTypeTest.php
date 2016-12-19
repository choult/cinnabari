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
            'Happy Path 1' => array(
                'schema' => $this->getSchema(),
                'request' => array(
                    Parser::TYPE_FUNCTION,
                    'get',
                    array(
                        array(
                            Parser::TYPE_PROPERTY,
                            'device'
                        )
                    )
                ),
                'expected' => array(
                    Parser::TYPE_FUNCTION,
                    'get',
                    array(
                        array(
                            Parser::TYPE_PROPERTY,
                            'device',
                            'Device'
                        )
                    )
                ),
            )
        );
    }

    /**
     * @dataProvider applyProvider
     *
     * @param array $schema
     * @param array $request
     * @param array $expected
     *
     * @covers ::apply
     */
    public function testApply(array $schema, array $request, array $expected)
    {
        $resolver = new PropertyType($schema);
        $this->assertEquals($expected, $resolver->apply($request));
    }

    /**
     * Gets a shared schema for testApply, above
     *
     * @return array
     */
    private function getSchema()
    {
        return array(
            'classes' => array(
                'Agent' => array(
                    'type' => array(
                        'type' => 4,
                        'path' => array('AgentType'),
                        'description' => 'The type of this agent'
                    ),
                    'version' => array(
                        'type' => 4,
                        'path' => array('AgenctVersion')
                    )
                ),
                'ProtectedMachine' => array(
                    'agent' => array(
                        'type' => 'Agent',
                        'path' => array(),
                        'description' => 'The software that sends backups from this protected machine to the device.'
                    ),
                    'hostname' => array(
                        'type' => 4,
                        'path' => array('Hostname'),
                        'description' => 'The hostname of this protected machine.'
                    ),
			        'operatingSystem' => array(
                        'type' => 4,
				        'path' => array('OperatingSystem'),
				        'description' => array('The operating system of this protected machine.')
                    ),
			        'volumes' => array(
                        'type' => 'Volume',
				        'path' => array('Volumes'),
				        'description' => array('The volumes that exist on this protected machine.')
                    )
                )
            )
        );
    }
}