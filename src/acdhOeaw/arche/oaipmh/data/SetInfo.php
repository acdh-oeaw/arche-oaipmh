<?php

/**
 * The MIT License
 *
 * Copyright 2017 Austrian Centre for Digital Humanities.
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

namespace acdhOeaw\arche\oaipmh\data;

use DOMElement;

/**
 * Simple container for OAI-PMH set data
 * (https://www.openarchives.org/OAI/openarchivesprotocol.html#Set)
 *
 * @author zozlak
 */
class SetInfo {

    /**
     * Set spec - see the OAI-PMH documentation
     * @var string
     */
    public string $spec;

    /**
     * Set name - see the OAI-PMH documentation
     * @var string
     */
    public string $name;

    /**
     * Set metadata to be put inside a <setDescription>
     * @var ?DOMElement
     */
    public DOMElement | null $description;

    /**
     * Creates a set descriptor object by copying provided values.
     * @param string $spec setSpec value
     * @param string $name setName value
     * @param DOMElement|null $description XML containing setDescription
     */
    public function __construct(string $spec, string $name,
                                ?DOMElement $description = null) {
        $this->spec        = $spec;
        $this->name        = $name;
        $this->description = $description;
    }
}
