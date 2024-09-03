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

use DateTimeImmutable;
use DOMElement;
use Exception;
use rdfInterface\TermInterface;
use rdfInterface\LiteralInterface;
use acdhOeaw\arche\oaipmh\OaiException;

/**
 * Description of Value
 *
 * @author zozlak
 */
class Value implements \Countable {

    const AGG_NONE         = 'none';
    const AGG_MIN          = 'min';
    const AGG_MAX          = 'max';
    const AGG              = '`^(?:' . self::AGG_NONE . '|(?:' . self::AGG_MIN . '|' . self::AGG_MAX . ')(?:,.*)?)`';
    const AGG_DEFAULT      = self::AGG_NONE;
    const REQ_REQUIRED     = 'required';
    const REQ_OPTIONAL     = 'optional';
    const REQ              = [self::REQ_REQUIRED, self::REQ_OPTIONAL];
    const REQ_DEFAULT      = self::REQ_REQUIRED;
    const ACTION_OVERWRITE = 'overwrite';
    const ACTION_APPEND    = 'append';
    const ACTION           = [self::ACTION_APPEND, self::ACTION_OVERWRITE];
    const ACTION_DEFAULT   = self::ACTION_APPEND;
    const AS_XML           = 'xml';
    const AS_TEXT          = 'text';
    const AS_ATTRIBUTE     = '@.*';
    const AS               = '`^(' . self::AS_XML . '|' . self::AS_TEXT . '|' . self::AS_ATTRIBUTE . ')$`';
    const AS_DEFAULT       = self::AS_TEXT;
    const LANG_SKIP        = 'skip';
    const LANG_IF_EMPTY    = 'if empty';
    const LANG_OVERWRITE   = 'overwrite';
    const LANG             = [self::LANG_SKIP, self::LANG_IF_EMPTY, self::LANG_OVERWRITE];
    const LANG_DEFAULT     = self::LANG_SKIP;
    const FORMAT           = '`^[DUbcdeEfFgGhHosuxX]:.*$`';
    const MAP              = '`^(?:[-_0-9A-Za-z]+|/.*)$`';

    static public function fromPath(string $path): self {
        $x       = new self();
        $x->path = $path;
        return $x;
    }

    static public function fromDomElement(DOMElement $el, string $suffix = ''): self {
        $x       = new self();
        $x->path = $el->getAttribute('val' . $suffix);
        $el->removeAttribute('val' . $suffix);
        if ($el->hasAttribute('match' . $suffix)) {
            $x->match = '`' . $el->getAttribute('match' . $suffix) . '`umsD';
            $el->removeAttribute('match' . $suffix);
        }
        if ($el->hasAttribute('notMatch' . $suffix)) {
            $x->notMatch = '`' . $el->getAttribute('notMatch' . $suffix) . '`umsD';
            $el->removeAttribute('notMatch' . $suffix);
        }
        $x->replace = $el->getAttribute('replace' . $suffix);
        $el->removeAttribute('replace' . $suffix);
        if ($el->hasAttribute('format' . $suffix)) {
            $x->format = $el->getAttribute('format' . $suffix);
            if (!preg_match(self::FORMAT, $x->format)) {
                throw new OaiException("Unsupported format$suffix attribute value: $x->format");
            }
            $el->removeAttribute('format' . $suffix);
        }
        if ($el->hasAttribute('map' . $suffix)) {
            $x->map = $el->getAttribute('map' . $suffix);
            if (!preg_match(self::MAP, $x->map)) {
                throw new OaiException("Unsupported map$suffix attribute value: $x->map");
            }

            $el->removeAttribute('map' . $suffix);
        }
        if ($el->hasAttribute('aggregate' . $suffix)) {
            $x->aggregate = $el->getAttribute('aggregate' . $suffix);
            if (!preg_match(self::AGG, $x->aggregate)) {
                throw new OaiException("Unsupported aggregate$suffix attribute value: $x->aggregate");
            }
            $el->removeAttribute('aggregate' . $suffix);
        }
        if ($el->hasAttribute('required' . $suffix)) {
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
        if ($el->hasAttribute('as' . $suffix)) {
            $x->as = $el->getAttribute('as' . $suffix);
            if (!preg_match(self::AS, $x->as)) {
                throw new OaiException("Unsupported as$suffix attribute value: $x->as");
            }
            $el->removeAttribute('as' . $suffix);
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
    public string $notMatch;
    public string $replace;
    public string $format;
    public string $map;
    public string $aggregate = self::AGG_DEFAULT;
    public string $required  = self::REQ_DEFAULT;
    public string $action    = self::ACTION_DEFAULT;
    public string $as        = self::AS_DEFAULT;
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
    public function setValues(array $values, ValueMapper $mapper): void {
        $valueLangs = array_map(fn($x) => $x instanceof LiteralInterface ? $x->getLang() : '', $values);

        $values = array_map(fn($x) => (string) $x, $values);
        if (!empty($this->notMatch)) {
            $values = array_filter($values, fn($x) => !preg_match($this->notMatch, $x));
        }
        if (!empty($this->match)) {
            $values = array_filter($values, fn($x) => preg_match($this->match, $x));
            if (!empty($this->replace)) {
                $values = preg_replace($this->match, $this->replace, $values);
            }
        }
        if (!empty($this->format)) {
            if (str_starts_with($this->format, 'D')) {
                $func = function (string $x) {
                    try {
                        $x = new DateTimeImmutable($x);
                        return $x->format(substr($this->format, 2));
                    } catch (Exception $ex) {
                        return null;
                    }
                };
            } elseif (str_starts_with($this->format, 'U')) {
                $func = fn(string $x) => rawurlencode($x);
            } else {
                $func = fn(string $x) => sprintf('%' . substr($this->format, 2) . substr($this->format, 0, 1), $x);
            }
            $values = array_map($func, $values);
            $values = array_filter($values, fn($x) => $x !== null);
        }
        if (!empty($this->map)) {
            if (str_starts_with($this->map, '/')) {
                $property   = substr($this->map, 1);
                $values     = array_merge(...array_map(fn($x) => $mapper->getMapping($x, $property), $values));
                $valueLangs = array_map(fn($x) => $x instanceof LiteralInterface ? $x->getLang() : '', $values);
                $values     = array_map(fn($x) => (string) $x, $values);
            } else {
                $map    = $this->map;
                $values = array_map(fn($x) => $mapper->getStaticMapping($map, $x), $values);
                $values = array_filter($values, fn($x) => $x !== null);
            }
        }
        if ($this->aggregate !== self::AGG_NONE && count($values) > 0) {
            list($agg, $prefLang) = explode(',', $this->aggregate . ',');
            if (!empty($prefLang)) {
                $matching = array_filter($values, fn($idx) => $valueLangs[$idx] === $prefLang, ARRAY_FILTER_USE_KEY);
                $values   = count($matching) > 0 ? $matching : $values;
            }
            if (count($values) > 1) {
                $func   = match ($agg) {
                    self::AGG_MIN => fn(array $x) => min(...$x),
                    self::AGG_MAX => fn(array $x) => max(...$x),
                };
                $agg    = $func($values);
                $values = array_filter($values, fn($x) => $x === $agg);
            }
            reset($values);
            $valueLangs = [$valueLangs[key($values)]];
        }
        $this->values     = array_values($values);
        $this->valueLangs = $valueLangs;
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
    public function insert(DOMElement $el, int $index): void {
        if ($this->count() === 0) {
            return;
        }

        // value
        $value = $this->values[$index];
        if (str_starts_with($this->as, '@')) {
            $attr = substr($this->as, 1);
            if ($this->action === self::ACTION_APPEND) {
                $value = $el->getAttribute($attr) . $value;
            }
            $el->setAttribute($attr, $value);
        } else {
            if ($this->action === self::ACTION_OVERWRITE) {
                $el->textContent = '';
            }
            if ($this->as === self::AS_TEXT) {
                $el->append($value);
            } else {
                $tmp = $el->ownerDocument->createDocumentFragment();
                $tmp->appendXML($value);
                $el->append($tmp);
            }
        }

        // lang
        if ($this->lang === self::LANG_OVERWRITE || $this->lang === self::LANG_IF_EMPTY && $el->getAttribute('xml:lang') === '' && !empty($this->valueLangs[$index])) {
            $el->setAttribute('xml:lang', (string) $this->valueLangs[$index]);
        }
    }
}
