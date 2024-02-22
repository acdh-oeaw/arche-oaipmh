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

/**
 * Description of Value
 *
 * @author zozlak
 */
class Value {

    static public function fromDomElement(DOMElement $el): self {
        $x            = new self();
        $x->path      = $el->getAttribute('val');
        $el->removeAttribute('val');
        $x->match     = $el->getAttribute('match');
        $el->removeAttribute('match');
        $x->replace   = $el->getAttribute('replace');
        $el->removeAttribute('replace');
        $x->format    = $el->getAttribute('format');
        $el->removeAttribute('format');
        $x->aggregate = $el->getAttribute('aggregate');
        $el->removeAttribute('aggregate');
        $x->map       = $el->getAttribute('map');
        $el->removeAttribute('map');
        $x->remove    = $el->getAttribute('remove');
        $el->removeAttribute('remove');
        return $x;
    }

    public string $path;
    public string $match;
    public string $replace;
    public string $format;
    public string $aggregate;
    public string $map;
    public string $remove;

    public function insert(DOMElement $el, array $values): void {
        if ($this->remove) {
            foreach ($values as $i) {
                $el->before($el->ownerDocument->createTextNode($i));
            }
        } else {
            foreach ($values as $i) {
                $tmp = $el->cloneNode();
                $tmp->appendChild($el->ownerDocument->createTextNode($i));
                $el->before($tmp);
            }
        }
        $el->parentNode->removeChild($el);
    }
}
