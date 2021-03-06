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
 * @author Spencer Mortensen <smortensen@datto.com>
 * @author Anthony Liu <aliu@datto.com>
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 * @copyright 2016 Datto, Inc.
 */

namespace Datto\Cinnabari\Compiler;

use Datto\Cinnabari\Exception\CompilerException;
use Datto\Cinnabari\Legacy\Parser;
use Datto\Cinnabari\Legacy\Translator;
use Datto\Cinnabari\Legacy\TypeInferer;
use Datto\Cinnabari\Mysql\Expression;
use Datto\Cinnabari\Mysql\Functions\Concatenate;
use Datto\Cinnabari\Mysql\Functions\CharacterLength;
use Datto\Cinnabari\Mysql\Functions\Lower;
use Datto\Cinnabari\Mysql\Functions\Substring;
use Datto\Cinnabari\Mysql\Functions\Upper;
use Datto\Cinnabari\Mysql\Operators\AndOperator;
use Datto\Cinnabari\Mysql\Operators\Divides;
use Datto\Cinnabari\Mysql\Operators\Equal;
use Datto\Cinnabari\Mysql\Operators\Greater;
use Datto\Cinnabari\Mysql\Operators\GreaterEqual;
use Datto\Cinnabari\Mysql\Operators\Less;
use Datto\Cinnabari\Mysql\Operators\LessEqual;
use Datto\Cinnabari\Mysql\Operators\Minus;
use Datto\Cinnabari\Mysql\Operators\Not;
use Datto\Cinnabari\Mysql\Operators\OrOperator;
use Datto\Cinnabari\Mysql\Operators\Plus;
use Datto\Cinnabari\Mysql\Operators\RegexpBinary;
use Datto\Cinnabari\Mysql\Operators\Times;
use Datto\Cinnabari\Mysql\Parameter;
use Datto\Cinnabari\Mysql\Statements\AbstractStatement;
use Datto\Cinnabari\Php\Input;
use Datto\Cinnabari\Php\Output;

/*
When joining from an origin table to a destination table:
 * Assume there is exactly one matching row in the destination table
 * If there is NO foreign key:
      Add the possibility of no matching rows in the destination table
 * If there is either:
     (a) NO uniqueness constraint on the destination table, or
     (b) BOTH the origin and destination columns are nullable:
 * Then add the possibility of many matching rows
*/

/**
 * Class AbstractCompiler
 * @package Datto\Cinnabari
 */
abstract class AbstractCompiler
{
    /** @var array */
    protected $schema;

    /** @var array */
    protected $signatures;

    /** @var array */
    protected $request;

    /** @var Input */
    protected $input;

    /** @var int */
    protected $context;
    
    /** @var AbstractStatement */
    protected $mysql;

    /** @var AbstractStatement */
    protected $subquery;

    /** @var int */
    protected $subqueryContext;

    /** @var array */
    protected $contextJoin;

    /** @var array */
    protected $rollbackPoint;

    const IS_REQUIRED = false;
    const IS_OPTIONAL = true;

    /**
     * AbstractCompiler constructor.
     *
     * @param array $schema
     * @param array $signatures
     */
    public function __construct($schema, $signatures)
    {
        $this->schema = $schema;
        $this->signatures = $signatures;
    }

    public function parentReset($request, $mysql)
    {
        $this->request = $request;
        $this->input = new Input();
        $this->context = null;
        $this->mysql = $mysql;
        $this->subquery = null;
        $this->subqueryContext = null;
        $this->contextJoin = null;
        $this->rollbackPoint = array();        
    }

    /**
     * @param array $token
     * @param Expression|null $output
     * @param int $type
     */
    abstract protected function getProperty($token, &$output, &$type);

    protected static function getTopLevelFunction($request)
    {
        if (!isset($request) || (count($request) === 0)) {
            throw CompilerException::unknownRequestType($request);
        }

        $firstToken = reset($request);

        if (count($firstToken) < 3) {
            throw CompilerException::unknownRequestType($request);
        }

        list($tokenType, $functionName, ) = $firstToken;

        if ($tokenType !== Parser::TYPE_FUNCTION) {
            throw CompilerException::unknownRequestType($request);
        }

        return $functionName;
    }

    protected static function getTypes($signatures, $translatedRequest)
    {
        $typeInferer = new TypeInferer($signatures);

        self::extractExpression($translatedRequest, $expressions);

        return $typeInferer->infer($expressions);
    }

    private static function extractExpression($requestArray, &$expressions)
    {
        if (!isset($expressions)) {
            $expressions = array();
        }

        $localExpressions = array();

        foreach ($requestArray as $request) {
            list($tokenType, $token) = each($request);

            switch ($tokenType) {
                case Translator::TYPE_FUNCTION:
                    $arguments = array();
                    foreach ($token['arguments'] as $argument) {
                        $argumentExpressions = self::extractExpression($argument, $expressions);
                        if (count($argumentExpressions) > 0) {
                            $expression = self::extractExpression($argument, $expressions);
                            $arguments[] = end($expression);
                        }
                    }
                    if (count($arguments) > 0) {
                        $expressions[] = array(
                            'name' => $token['function'],
                            'type' => 'function',
                            'arguments' => $arguments
                        );
                    }
                    break;

                case Translator::TYPE_PARAMETER:
                    $localExpressions[] = array(
                        'name' => $token,
                        'type' => 'parameter'
                    );
                    break;

                case Translator::TYPE_VALUE:
                    $localExpressions[] = array(
                        'name' => $token['type'],
                        'type' => 'primitive'
                    );
                    break;

                case Translator::TYPE_LIST:
                    foreach ($token as $pair) {
                        $left = self::extractExpression($pair['property'], $expressions);
                        $right = self::extractExpression($pair['value'], $expressions);
                        if ((count($left) > 0) && (count($right) > 0)) {
                            $expressions[] = array(
                                'name' => 'assign',
                                'type' => 'function',
                                'arguments' => array($left[0], $right[0])
                            );
                        }
                    }
                    break;
            }
        }

        return $localExpressions;
    }

    protected function optimize($topLevelFunction, $request)
    {
        $method = self::analyze($topLevelFunction, $request);

        // Rule: remove unnecessary sort functions
        if (
            $method['is']['count'] ||
            $method['is']['aggregator'] ||
            $method['is']['set'] ||
            $method['is']['delete']
        ) {
            if (
                $method['before']['sorts']['slices'] || (
                    $method['sorts'] && !$method['slices']
                )
            ) {
                $request = self::removeFunction('sort', $request, $sort);
                $request = self::removeFunction('rsort', $request, $sort);
                $method['sorts'] = false;
                $method['before']['sorts']['filters'] = false;
                $method['before']['sorts']['slices'] = false;
                $method['before']['filters']['sorts'] = false;
                $method['before']['slices']['sorts'] = false;
            }
        }

        // Rule: slices imply a sort
        if (
            self::scanTable($request, $table, $id, $hasZero) && (
                !$method['before']['slices']['sorts'] || (
                    $method['slices'] && !$method['sorts']
                )
            )
        ) {
            // TODO: get the type of the table's id; don't assume int
            $type = Output::TYPE_INTEGER;
            $valueToken = array(
                Translator::TYPE_VALUE => array(
                    'table' => $table,
                    'expression' => $id,
                    'type' => $type,
                    'hasZero' => $hasZero
                )
            );
            $sortFunction = array(
                Translator::TYPE_FUNCTION => array(
                    'function' => 'sort',
                    'arguments' => array(array($valueToken))
                )
            );
            $request = self::insertFunctionBefore($sortFunction, 'slice', $request);
        }

        // Rule: slices in countsaggregators require subqueries
        if ($method['is']['count'] || $method['is']['aggregator']) {
            if ($method['slices']) {
                $forkFunction = array(
                    Translator::TYPE_FUNCTION => array(
                        'function' => 'fork',
                        'arguments' => array()
                    )
                );

                $request = self::insertFunctionAfter($forkFunction, 'slice', $request);
            }
        }

        // Rule: when filters and sorts are adjacent, force the filter to appear before the sort
        if (
            $method['before']['filters']['sorts'] && (
                !$method['slices'] || (
                    // the slice cannot be between the filter and the sort
                    $method['before']['filters']['slices'] === $method['before']['sorts']['slices']
                )
            )
        ) {
            $request = self::removeFunction('sort', $request, $removedFunction);
            $request = self::removeFunction('rsort', $request, $removedFunction);
            $request = self::insertFunctionAfter($removedFunction, 'filter', $request);
            $method['before']['filters']['sorts'] = false;
            $method['before']['sorts']['filters'] = true;
        }

        return $request;
    }

    private static function removeFunction($functionName, $request, &$removedFunction)
    {
        return array_filter(
            $request,
            function ($wrappedToken) use ($functionName, &$removedFunction) {
                list($tokenType, $token) = each($wrappedToken);

                $include = ($tokenType !== Translator::TYPE_FUNCTION) ||
                    $token['function'] !== $functionName;

                if (!$include) {
                    $removedFunction = $wrappedToken;
                }

                return $include;
            }
        );
    }

    private static function insertFunctionBefore($function, $target, $request)
    {
        return self::insertFunctionRelativeTo(true, $function, $target, $request);
    }

    private static function insertFunctionAfter($function, $target, $request)
    {
        return self::insertFunctionRelativeTo(false, $function, $target, $request);
    }

    private static function insertFunctionRelativeTo($insertBefore, $function, $target, $request)
    {
        return array_reduce(
            $request,
            function ($carry, $wrappedToken) use ($insertBefore, $function, $target) {
                list($type, $token) = each($wrappedToken);
                $tokensToAdd = array($wrappedToken);
                if ($type === Translator::TYPE_FUNCTION && $token['function'] === $target) {
                    if ($insertBefore) {
                        array_unshift($tokensToAdd, $function);
                    } else {
                        $tokensToAdd[] =  $function;
                    }
                }
                return array_merge($carry, $tokensToAdd);
            },
            array()
        );
    }

    protected function analyze($topLevelFunction, $translatedRequest)
    {
        // is a get, delete, set, insert, count, aggregator
        $method = array();
        $method['is'] = array();
        $method['is']['get'] = false;
        $method['is']['delete'] = false;
        $method['is']['set'] = false;
        $method['is']['insert'] = false;
        $method['is']['count'] = false;
        $method['is']['aggregator'] = false;

        if (array_key_exists($topLevelFunction, $method['is'])) {
            $method['is'][$topLevelFunction] = true;
        } else {
            $method['is']['aggregator'] = true;
        }

        // order of the list functions
        $functions = array();
        foreach ($translatedRequest as $wrappedToken) {
            list($tokenType, $token) = each($wrappedToken);
            if ($tokenType === Translator::TYPE_FUNCTION) {
                $functions[] = $token['function'];
            }
        }

        $method['before'] = array(
            'filters' => array('sorts' => false, 'slices' => false),
            'sorts' => array('filters' => false, 'slices' => false),
            'slices' => array('filters' => false, 'sorts' => false)
        );
        $filterIndex = array_search('filter', $functions, true);
        $sortIndex = array_search('sort', $functions, true);
        if ($sortIndex === false) {
            $sortIndex = array_search('rsort', $functions, true);
        }
        $sliceIndex = array_search('slice', $functions, true);
        $method['filters'] = $filterIndex !== false;
        $method['sorts'] = $sortIndex !== false;
        $method['slices'] = $sliceIndex !== false;
        if ($method['filters'] && $method['sorts']) {
            $method['before']['filters']['sorts'] = $filterIndex > $sortIndex;
            $method['before']['sorts']['filters'] = $sortIndex > $filterIndex;
        }
        if ($method['filters'] && $method['slices']) {
            $method['before']['filters']['slices'] = $filterIndex > $sliceIndex;
            $method['before']['slices']['filters'] = $sliceIndex > $filterIndex;
        }
        if ($method['sorts'] && $method['slices']) {
            $method['before']['sorts']['slices'] = $sortIndex > $sliceIndex;
            $method['before']['slices']['sorts'] = $sliceIndex > $sortIndex;
        }

        return $method;
    }

    protected function getOptionalFilterFunction()
    {
        if (!self::scanFunction(reset($this->request), $name, $arguments)) {
            return false;
        }

        if ($name !== 'filter') {
            return false;
        }

        if (!isset($arguments) || (count($arguments) === 0)) {
            throw CompilerException::noFilterArguments($this->request);
        }

        if (!$this->getExpression($arguments[0], self::IS_REQUIRED, $where, $type)) {
            throw CompilerException::badFilterExpression(
                $this->context,
                $arguments[0]
            );
        }

        $this->mysql->setWhere($where);

        array_shift($this->request);

        return true;
    }

    protected function handleJoin($token)
    {
        if ($token['isContextual']) {
            $this->contextJoin = $token;
        }

        if (isset($this->subquery)) {
            $this->subqueryContext = $this->subquery->addJoin(
                $this->subqueryContext,
                $token['tableB'],
                $token['expression']
            );
        } else {
            $this->context = $this->mysql->addJoin(
                $this->context,
                $token['tableB'],
                $token['expression']
            );
        }
    }

    protected function followJoins($arrayToken)
    {
        while ($this->scanJoin(reset($arrayToken), $joinToken)) {
            $this->handleJoin($joinToken);
            array_shift($arrayToken);
        }

        return $arrayToken;
    }

    protected function getExpression($arrayToken, $hasZero, &$expression, &$type)
    {
        $firstElement = reset($arrayToken);
        list($tokenType, $token) = each($firstElement);

        $context = $this->context;
        $result = false;

        switch ($tokenType) {
            case Translator::TYPE_JOIN:
                $this->setRollbackPoint();
                $this->handleJoin($token);
                array_shift($arrayToken);
                $result = $this->conditionallyRollback(
                    $this->getExpression($arrayToken, $hasZero, $expression, $type)
                );
                break;

            case Translator::TYPE_PARAMETER:
                $result = $this->getParameter($token, $hasZero, $expression);
                break;

            case Translator::TYPE_VALUE:
                $result = $this->getProperty($token, $expression, $type);
                break;

            case Translator::TYPE_FUNCTION:
                $name = $token['function'];
                $arguments = $token['arguments'];
                $result = $this->getFunction($name, $arguments, $hasZero, $expression, $type);
                break;

            default:
                // TODO
        }

        $this->context = $context;
        return $result;
    }

    protected function getFunction($name, $arguments, $hasZero, &$output, &$type)
    {
        $countArguments = count($arguments);

        if ($countArguments === 1) {
            $argument = current($arguments);
            return $this->getUnaryFunction($name, $argument, $hasZero, $output, $type);
        }

        if ($countArguments === 2) {
            list($argumentA, $argumentB) = $arguments;
            return $this->getBinaryFunction($name, $argumentA, $argumentB, $hasZero, $output, $type);
        }

        if ($countArguments === 3) {
            list($argumentA, $argumentB, $argumentC) = $arguments;
            return $this->getTernaryFunction($name, $argumentA, $argumentB, $argumentC, $hasZero, $output, $type);
        }

        return false;
    }

    protected function getUnaryFunction($name, $argument, $hasZero, &$expression, &$type)
    {
        if ($name === 'length') {
            return $this->getLengthFunction($argument, $hasZero, $expression, $type);
        }

        if (!$this->getExpression($argument, $hasZero, $childExpression, $argumentType)) {
            return false;
        }

        $type = $this->getReturnTypeFromFunctionName($name, $argumentType, false, false);

        switch ($name) {
            case 'uppercase':
                $expression = new Upper($childExpression);
                return true;

            case 'lowercase':
                $expression = new Lower($childExpression);
                return true;

            case 'not':
                $expression = new Not($childExpression);
                return true;

            default:
                $type = null;
                return false;
        }
    }

    protected function getLengthFunction($argument, $hasZero, &$expression, &$type)
    {
        if (!$this->getExpression($argument, self::IS_REQUIRED, $childExpression, $argumentType)) {
            return false;
        }

        $type = Output::TYPE_INTEGER;
        $expression = new CharacterLength($childExpression);
        return true;
    }

    protected function getBinaryFunction($name, $argumentA, $argumentB, $hasZero, &$expression, &$type)
    {
        if (
            !$this->getExpression($argumentA, $hasZero, $expressionA, $argumentTypeOne) ||
            !$this->getExpression($argumentB, $hasZero, $expressionB, $argumentTypeTwo)
        ) {
            return false;
        }

        $type = $this->getReturnTypeFromFunctionName($name, $argumentTypeOne, $argumentTypeTwo, false);

        switch ($name) {
            case 'plus':
                if ($argumentTypeOne === Output::TYPE_STRING) {
                    $expression = new Concatenate($expressionA, $expressionB);
                } else {
                    $expression = new Plus($expressionA, $expressionB);
                }
                return true;

            case 'minus':
                $expression = new Minus($expressionA, $expressionB);
                return true;

            case 'times':
                $expression = new Times($expressionA, $expressionB);
                return true;

            case 'divides':
                $expression = new Divides($expressionA, $expressionB);
                return true;

            case 'equal':
                $expression = new Equal($expressionA, $expressionB);
                return true;

            case 'and':
                $expression = new AndOperator($expressionA, $expressionB);
                return true;

            case 'or':
                $expression = new OrOperator($expressionA, $expressionB);
                return true;

            case 'notEqual':
                $equalExpression = new Equal($expressionA, $expressionB);
                $expression = new Not($equalExpression);
                return true;

            case 'less':
                $expression = new Less($expressionA, $expressionB);
                return true;

            case 'lessEqual':
                $expression = new LessEqual($expressionA, $expressionB);
                return true;

            case 'greater':
                $expression = new Greater($expressionA, $expressionB);
                return true;

            case 'greaterEqual':
                $expression = new GreaterEqual($expressionA, $expressionB);
                return true;

            case 'match':
                $expression = new RegexpBinary($expressionA, $expressionB);
                return true;

            default:
                $type = null;
                return false;
        }
    }

    protected function getTernaryFunction($name, $argumentA, $argumentB, $argumentC, $hasZero, &$expression, &$type)
    {
        if ($name === 'substring') {
            return $this->getSubstringFunction($argumentA, $argumentB, $argumentC, $hasZero, $expression, $type);
        }

        if (
            !$this->getExpression($argumentA, $hasZero, $expressionA, $argumentTypeOne) ||
            !$this->getExpression($argumentB, $hasZero, $expressionB, $argumentTypeTwo) ||
            !$this->getExpression($argumentC, $hasZero, $expressionC, $argumentTypeThree)
        ) {
            return false;
        }

        $type = $this->getReturnTypeFromFunctionName($name, $argumentTypeOne, $argumentTypeTwo, $argumentTypeThree);

        switch ($name) {
            default:
                $type = null;
                return false;
        }
    }

    protected function getSubstringFunction($stringExpression, $beginParameter, $endParameter, $hasZero, &$expression, &$type)
    {
        if (!$this->getExpression($stringExpression, self::IS_REQUIRED, $stringMysql, $typeA)) {
            return false;
        }

        if (
            !$this->scanParameter($beginParameter, $beginName) ||
            !$this->scanParameter($endParameter, $endName)
        ) {
            return false;
        }

        $beginId = $this->input->useSubstringBeginArgument($beginName, self::IS_REQUIRED);
        $endId = $this->input->useSubstringEndArgument($beginName, $endName, self::IS_REQUIRED);

        $beginMysql = new Parameter($beginId);
        $endMysql = new Parameter($endId);

        $expression = new Substring($stringMysql, $beginMysql, $endMysql);
        $type = Output::TYPE_STRING;

        return true;
    }

    private function getReturnTypeFromFunctionName($name, $typeOne, $typeTwo, $typeThree)
    {
        if (array_key_exists($name, $this->signatures)) {
            $signatures = $this->signatures[$name];
            
            foreach ($signatures as $signature) {
                if (self::signatureMatchesArguments($signature, $typeOne,
                    $typeTwo, $typeThree)
                ) {
                    return $signature['return'];
                }
            }

            return $signatures[0]['return'];
        } else {
            return false;
        }
    }
    
    protected static function signatureMatchesArguments($signature, $typeOne, $typeTwo, $typeThree)
    {
        if ($signature['arguments'][0] !== $typeOne) {
            return false;
        }

        // TODO: assumes functions take at most 3 arguments for simplicity
        if (count($signature['arguments']) >= 2) {
            if ($signature['arguments'][1] !== $typeTwo) {
                return false;
            }

            if (count($signature['arguments']) >= 3) {
                return $signature['arguments'][2] === $typeThree;
            }
            
            return true;
        }

        return true;
    }

    protected function getParameter($name, $hasZero, &$output)
    {
        $id = $this->input->useArgument($name, $hasZero);

        if ($id === null) {
            return false;
        }

        $output = new Parameter($id);
        return true;
    }

    protected static function scanTable($input, &$table, &$id, &$hasZero)
    {
        // scan the next token of the supplied arrayToken
        $input = reset($input);

        list($tokenType, $token) = each($input);

        if ($tokenType !== Translator::TYPE_TABLE) {
            return false;
        }

        $table = $token['table'];
        $id = $token['id'];
        $hasZero = $token['hasZero'];

        return true;
    }

    protected static function scanParameter($input, &$name)
    {
        // scan the next token of the supplied arrayToken
        $input = reset($input);

        list($tokenType, $token) = each($input);

        if ($tokenType !== Translator::TYPE_PARAMETER) {
            return false;
        }

        $name = $token;
        return true;
    }

    protected static function scanProperty($input, &$table, &$name, &$type, &$hasZero)
    {
        list($tokenType, $token) = each($input);

        if ($tokenType !== Translator::TYPE_VALUE) {
            return false;
        }

        $table = $token['table'];
        $name = $token['expression'];
        $type = $token['type'];
        $hasZero = $token['hasZero'];
        return true;
    }

    protected static function scanFunction($input, &$name, &$arguments)
    {
        list($tokenType, $token) = each($input);

        if ($tokenType !== Translator::TYPE_FUNCTION) {
            return false;
        }

        $name = $token['function'];
        $arguments = $token['arguments'];
        return true;
    }

    protected static function scanJoin($input, &$object)
    {
        reset($input);

        list($tokenType, $token) = each($input);

        if ($tokenType !== Translator::TYPE_JOIN) {
            return false;
        }

        $object = $token;
        return true;
    }

    protected function conditionallyRollback($success)
    {
        if ($success) {
            $this->clearRollbackPoint();
            return true;
        } else {
            $this->rollback();
            return false;
        }
    }

    protected function setRollbackPoint()
    {
        $this->rollbackPoint[] = array($this->context, $this->contextJoin, $this->input, $this->mysql);
    }

    protected function clearRollbackPoint()
    {
        array_pop($this->rollbackPoint);
    }

    protected function rollback()
    {
        $rollbackState = array_pop($this->rollbackPoint);
        $this->context = $rollbackState[0];
        $this->contextJoin = $rollbackState[1];
        $this->input = $rollbackState[2];
        $this->mysql = $rollbackState[3];
    }
}
