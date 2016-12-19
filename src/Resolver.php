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

namespace Datto\Cinnabari;

use Datto\Cinnabari\Resolver\ResolverInterface;

/**
 * A class to contain all Resolvers, applying them to the passed request in the order in which they were added
 */
class Resolver
{
    /**
     * Constructs a new Resolver from the passed list of ResolverInterfaces
     *
     * @param \Datto\Cinnabari\Resolver\ResolverInterface[] $resolvers
     */
    public function __construct(array $resolvers)
    {
        foreach ($resolvers as $resolver) {
            $this->addResolver($resolver);
        }
    }

    /**
     * Add a Resolver to this collection
     *
     * @param ResolverInterface $resolver
     */
    public function addResolver(ResolverInterface $resolver)
    {
        $this->resolvers[] = $resolver;
    }

    /**
     * Applies each Resolver added to this collection in turn to the passed request
     *
     * @param array $request
     *
     * @return array
     */
    public function resolve(array $request)
    {
        foreach ($this->resolvers as $resolver) {
            $request = $resolver->apply($request);
        }

        return $request;
    }
}
