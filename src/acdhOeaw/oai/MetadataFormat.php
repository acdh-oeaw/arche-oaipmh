<?php

/*
 * The MIT License
 *
 * Copyright 2017 zozlak.
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

namespace acdhOeaw\oai;

/**
 * Container for OAI-PMH metada format data
 *
 * @author zozlak
 */
class MetadataFormat {

    /**
     *
     * @var string
     */
    public $metadataPrefix;

    /**
     *
     * @var string
     */
    public $schema;

    /**
     *
     * @var string
     */
    public $metadataNamespace;

    /**
     *
     * @var string
     */
    public $rdfProperty;

    /**
     *
     * @var string
     */
    public $class;

    /**
     * 
     * @param array $fields
     */
    public function __construct(array $fields = null) {
        if (is_array($fields)) {
            $this->metadataPrefix    = isset($fields['metadataPrefix']) ? $fields['metadataPrefix'] : null;
            $this->schema            = isset($fields['schema']) ? $fields['schema'] : null;
            $this->metadataNamespace = isset($fields['metadataNamespace']) ? $fields['metadataNamespace'] : null;
            $this->rdfProperty       = isset($fields['rdfProperty']) ? $fields['rdfProperty'] : null;
            $this->class             = isset($fields['class']) ? $fields['class'] : null;
        }
    }

}
