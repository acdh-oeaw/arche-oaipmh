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

use DOMElement;
use DOMText;
use DOMDocumentFragment;
use SplObjectStorage;
use rdfInterface\TermInterface;
use rdfInterface\LiteralInterface;
use acdhOeaw\arche\oaipmh\OaiException;

/**
 * Description of Value
 *
 * @author zozlak
 */
class Value implements \Countable {

    const AGG_NONE            = 'none';
    const AGG_MIN             = 'min';
    const AGG_MAX             = 'max';
    const AGG                 = [self::AGG_NONE, self::AGG_MIN, self::AGG_MAX];
    const AGG_DEFAULT         = self::AGG_NONE;
    const REQ_REQUIRED        = 'required';
    const REQ_OPTIONAL        = 'optional';
    const REQ                 = [self::REQ_REQUIRED, self::REQ_OPTIONAL];
    const REQ_DEFAULT         = self::REQ_REQUIRED;
    const ACTION_OVERWRITE    = 'overwrite';
    const ACTION_APPEND       = 'append';
    const ACTION              = [self::ACTION_APPEND, self::ACTION_OVERWRITE];
    const ACTION_DEFAULT      = self::ACTION_APPEND;
    const TARGET_XML_CONTENT  = 'xml content';
    const TARGET_TEXT_CONTENT = 'text content';
    const TARGET_XML_AFTER    = 'xml after';
    const TARGET_TEXT_AFTER   = 'text after';
    const TARGET_ATTRIBUTE    = '@.*';
    const TARGET              = '`^(' . self::TARGET_XML_CONTENT . '|' . self::TARGET_TEXT_CONTENT . '|' . self::TARGET_XML_AFTER . '|' . self::TARGET_TEXT_AFTER . '|' . self::TARGET_ATTRIBUTE . ')$';
    const TARGET_DEFAULT      = self::TARGET_TEXT_CONTENT;
    const LANG_SKIP           = 'skip';
    const LANG_IF_EMPTY       = 'if empty';
    const LANG_OVERWRITE      = 'overwrite';
    const LANG                = [self::LANG_SKIP, self::LANG_IF_EMPTY, self::LANG_OVERWRITE];
    const LANG_DEFAULT        = self::LANG_SKIP;

    static public function fromDomElement(DOMElement $el, string $suffix = ''): self {
        $x          = new self();
        $x->path    = $el->getAttribute('val' . $suffix);
        $el->removeAttribute('val' . $suffix);
        $x->match   = $el->getAttribute('match' . $suffix);
        $el->removeAttribute('match' . $suffix);
        $x->replace = $el->getAttribute('replace' . $suffix);
        $el->removeAttribute('replace' . $suffix);
        $x->format  = $el->getAttribute('format' . $suffix);
        $el->removeAttribute('format' . $suffix);
        $x->map     = $el->getAttribute('map' . $suffix);
        $el->removeAttribute('map' . $suffix);
        if ($el->hasAttribute('aggregate' . $suffix)) {
            $x->aggregate = $el->getAttribute('aggregate' . $suffix);
            if (!in_array($x->aggregate, self::AGG)) {
                throw new OaiException("Unsupported aggregate$suffix attribute value: $x->aggregate");
            }
            $el->removeAttribute('aggregate' . $suffix);
        }
        if ($el->hasAttribute('require' . $suffix)) {
            $x->required = $el->getAttribute('required' . $suffix);
            if (!in_array($x->required, self::REQ)) {
                throw new OaiException("Unsupported required$suffix attribute value: $x->required");
            }
            $el->removeAttribute('required' . $suffix);
        }
        if ($el->hasAttribute('action' . $suffix)) {
            $x->action = $el->getAttribute('action' . $suffix);
            if (!in_array($x->action, self::ACTION)) {
                throw new OaiException("Unsupported action$suffix attribute value: $x->action");
            }
            $el->removeAttribute('action' . $suffix);
        }
        if ($el->hasAttribute('target' . $suffix)) {
            $x->target = $el->getAttribute('target' . $suffix);
            if (!preg_match(self::TARGET, $x->target)) {
                throw new OaiException("Unsupported target$suffix attribute value: $x->target");
            }
            $el->removeAttribute('target' . $suffix);
        }
        if ($el->hasAttribute('lang' . $suffix)) {
            $x->lang = $el->getAttribute('lang' . $suffix);
            if (!in_array($x->lang, self::LANG)) {
                throw new OaiException("Unsupported lang$suffix attribute value: $x->lang");
            }
            $el->removeAttribute('lang' . $suffix);
        }
        return $x;
    }

    public string $path;
    public string $match;
    public string $replace;
    public string $format;
    public string $map;
    public string $aggregate = self::AGG_DEFAULT;
    public string $required  = self::REQ_DEFAULT;
    public string $action    = self::ACTION_DEFAULT;
    public string $target    = self::TARGET_DEFAULT;
    public string $lang      = self::LANG_DEFAULT;

    /**
     * 
     * @var array<string>
     */
    public array $values;

    /**
     * 
     * @var array<string>
     */
    public array $valueLangs;

    /**
     * 
     * @param array<TermInterface> $values
     * @return void
     */
    public function setValues(array $values): void {
        $this->values     = array_map(fn($x) => (string) $x, $values);
        $this->valueLangs = array_map(fn($x) => $x instanceof LiteralInterface ? $x->getLang() : '', $values);
    }

    public function count(): int {
        $count = count($this->values);
        return $this->aggregate === self::AGG_NONE ? $count : min($count, 1);
    }

    public function isRequired(): bool {
        return $this->required === self::REQ_REQUIRED;
    }

    /**
     * 
     * @param DOMElement $el
     * @param SplObjectStorage<DOMNode, mixed> $content
     * @param SplObjectStorage<DOMNode, mixed> $after
     * @param int $index
     * @return void
     */
    public function insert(DOMElement $el, SplObjectStorage $content,
                           SplObjectStorage $after, int $index): void {
        if ($this->count() === 0) {
            return;
        }

        // value
        $value  = $this->values[$index];
        $target = match ($this->target) {
            self::TARGET_TEXT_AFTER, self::TARGET_XML_AFTER => $after,
            self::TARGET_TEXT_CONTENT, self::TARGET_XML_CONTENT => $content,
            default => null,
        };
        if ($target !== null) {
            if ($this->action === self::ACTION_OVERWRITE) {
                $target->removeAll();
            }
            if ($this->target === self::TARGET_TEXT_CONTENT || $this->target === self::TARGET_TEXT_AFTER) {
                $target->attach($el->ownerDocument->createTextNode($value));
            } else {
                $tmp = $el->ownerDocument->createDocumentFragment();
                $tmp->appendXML($value);
                $target->attach($tmp);
            }
        } else {
            $attr = substr($this->target, 1);
            if ($this->action === self::ACTION_APPEND) {
                $value = $el->getAttribute($attr) . $value;
            }
            $el->setAttribute($attr, $value);
        }

        // lang
        if ($this->lang === self::LANG_IF_EMPTY && $el->getAttribute('xml:lang') === '' || $this->lang === self::LANG_OVERWRITE) {
            $el->setAttribute('xml:lang', $this->valueLangs[$index]);
        }
    }
}
