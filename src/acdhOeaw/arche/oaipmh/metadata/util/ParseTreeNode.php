<?php

/*
 * The MIT License
 *
 * Copyright 2024 zozlak.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace acdhOeaw\arche\oaipmh\metadata\util;

use acdhOeaw\arche\oaipmh\OaiException;

/**
 * Class used to model logical expressions parse tree.
 * Used to evaluate expressions by the TemplateMetadata class.
 *
 * @author zozlak
 */
class ParseTreeNode {

    const OP_OR                    = 'OR';
    const OP_AND                   = 'AND';
    const OP_NOT                   = 'NOT';
    const OP_PARENTHESIS           = '(';
    const SINGLE_OPERAND_OPERATORS = [self::OP_NOT];
    const PRECEDENCE               = [
        self::OP_PARENTHESIS => 0,
        self::OP_NOT         => 1,
        self::OP_OR          => 2,
        self::OP_AND         => 4,
    ];

    static public function fromValue(bool $value, string $debug = ''): self {
        $x        = new ParseTreeNode();
        $x->value = $value;
        $x->debug = $debug;
        return $x;
    }

    static public function fromOperator(string $operator, self | null $first): self {
        $x           = new ParseTreeNode();
        $x->operator = $operator;
        if ($first !== null) {
            $priority   = self::PRECEDENCE[$operator];
            $prevParent = $first;
            $parent     = $first->parent ?? null;
            while ($parent && $priority < self::PRECEDENCE[$parent->operator]) {
                $prevParent = $parent;
                $parent     = $parent->parent ?? null;
            }
            $prevParent->parent = $x;
            $x->first           = $prevParent;
            if ($parent != null) {
                $x->parent = $parent;
                if ($parent->first === $prevParent) {
                    $parent->first = $x;
                } else {
                    $parent->second = $x;
                }
            }
        }
        return $x;
    }

    public bool $value;
    public string $operator;
    public self $first;
    public self $second;
    public self $parent;
    public string $debug;

    public function push(self $operand): self {
        $operand->parent = $this;
        if (!isset($this->first)) {
            $this->first = $operand;
            return $this->operator === self::OP_PARENTHESIS ? $operand : $this;
        } elseif (!isset($this->second) && !in_array($this->operator, self::SINGLE_OPERAND_OPERATORS)) {
            $this->second = $operand;
            return $operand;
        }
        throw new OaiException("Too many operands");
    }

    public function evaluate(): bool {
        if (isset($this->value)) {
            return $this->value;
        }
        return match ($this->operator) {
            'AND' => $this->first->evaluate() && $this->second->evaluate(),
            'OR' => $this->first->evaluate() || $this->second->evaluate(),
            'NOT' => !$this->first->evaluate(),
            '(' => $this->first->evaluate(),
        };
    }

    public function matchParenthesis(): self {
        $parent = $this->parent;
        while ($parent->operator !== self::OP_PARENTHESIS) {
            $parent = $parent->parent;
        }
        return $parent;
    }
}
