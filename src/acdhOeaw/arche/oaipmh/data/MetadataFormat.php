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

/**
 * Container for OAI-PMH metada format data (both properties used by the OAI-PMH
 * protocol and by this implementation).
 *
 * @author zozlak
 */
class MetadataFormat {

    /**
     * OAI-PMH metadataPrefix
     * @var string
     * @see https://www.openarchives.org/OAI/openarchivesprotocol.html#ListMetadataFormats
     */
    public $metadataPrefix;

    /**
     * OAI-PMH metadata schema
     * @var string
     * @see https://www.openarchives.org/OAI/openarchivesprotocol.html#ListMetadataFormats
     */
    public $schema;

    /**
     * OAI-PMH metadataNamespace
     * @var string
     * @see https://www.openarchives.org/OAI/openarchivesprotocol.html#ListMetadataFormats
     */
    public $metadataNamespace;

    /**
     * 
     * @var ?string
     */
    public $class;

    /**
     * 
     * @var array<string, string>
     */
    public $propNmsp = [];

    /**
     * 
     * @var ?string
     */
    public $schemaProp;

    /**
     * 
     * @var ?string
     */
    public $uriProp;

    /**
     * 
     * @var ?string
     */
    public $metaResProp;

    /**
     * 
     * @var ?string
     */
    public $cmdiSchemaProp;

    /**
     * 
     * @var ?string
     */
    public $titleProp;

    /**
     * 
     * @var ?string
     */
    public $eqProp;

    /**
     * 
     * @var ?string
     */
    public $mode;

    /**
     * 
     * @var array<string, string>
     */
    public $requestOptions = [];

    /**
     * 
     * @var ?string
     */
    public $templateDir;

    /**
     * 
     * @var RepositoryInfo
     */
    public $info;

    /**
     * 
     * @var array<string, string>
     */
    public $idNmsp = [];

    /**
     * 
     * @var ?string
     */
    public $idProp;
    
    /**
     * 
     * @var ?string
     */
    public $acdhNmsp;

    /**
     * 
     * @var ?string
     */
    public $resolverNmsp;

    /**
     * 
     * @var ?string
     */
    public $schemaDefault;

    /**
     * 
     * @var ?string
     */
    public $defaultLang;

    /**
     * 
     * @var ?array<string, string>
     */
    public $valueMaps;
    
    /**
     * Creates a metadata format descriptor
     * @param object $fields values to set in the descriptor
     */
    public function __construct(object $fields = null) {
        foreach ($fields as $k => $v) {
            $this->$k = $v;
        }
    }
}
