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
use Datto\Cinnabari\Resolver;
use Datto\Cinnabari\Resolver\ResolverInterface;

/**
 * @coversDefaultClass \Datto\Cinnabari\Resolver
 */
class ResolverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * {@inheritdoc}
     */
    public function tearDown()
    {
        \Mockery::close();
    }

    /**
     * @covers ::__construct
     * @covers ::addResolver
     * @covers ::resolve
     */
    public function testResolve()
    {
        $input = array('test', 'one', 'two', 'three');
        $output1 = array('four', 'five', 'six');
        $output2 = array('seven', 'eight', 'nine');

        $resolver1 = Mockery::mock('\Datto\Cinnabari\Resolver\ResolverInterface');
        $resolver1->shouldReceive('apply')
            ->with($input)
            ->andReturn($output1)
            ->mock();

        $resolver2 = Mockery::mock('\Datto\Cinnabari\Resolver\ResolverInterface');
        $resolver2->shouldReceive('apply')
            ->with($output1)
            ->andReturn($output2)
            ->mock();

        $resolver = new Resolver(array($resolver1, $resolver2));

        $this->assertEquals($output2, $resolver->resolve($input));
    }
}