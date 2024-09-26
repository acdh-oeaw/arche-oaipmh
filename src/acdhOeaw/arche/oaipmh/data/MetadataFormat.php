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
class MetadataFormat extends \stdClass {

    /**
     * OAI-PMH metadataPrefix
     * 
     * Required by: core
     * 
     * @see https://www.openarchives.org/OAI/openarchivesprotocol.html#ListMetadataFormats
     */
    public string $metadataPrefix;

    /**
     * OAI-PMH metadata schema
     * 
     * Required by: core, CmdiMetadata
     * 
     * @see https://www.openarchives.org/OAI/openarchivesprotocol.html#ListMetadataFormats
     */
    public string $schema;

    /**
     * OAI-PMH metadataNamespace
     * 
     * Required by: core
     * 
     * @see https://www.openarchives.org/OAI/openarchivesprotocol.html#ListMetadataFormats
     */
    public string $metadataNamespace;

    /**
     * Class implementing a given metadata format
     * 
     * Required by: core
     */
    public string $class;

    /**
     * Set by core
     */
    public RepositoryInfo $info;

    /**
     * Used by: ResMetadata, CmdiMetadata
     */
    public string $metaResProp;

    /**
     * Used by: ResMetadata, CmdiMetadata
     * 
     * @var array<string, mixed>
     */
    public array $requestOptions = [];

    /**
     * Used by: CmdiMetadata
     */
    public string $cmdiSchemaProp;

    /**
     * Used by: TemplateMetadata
     */
    public string $templatePath;

    /**
     * Used by: TemplateMetadata
     */
    public bool $keepComments;

    /**
     * Used by: TemplateMetadata
     */
    public bool $xmlErrors;

    /**
     * Used by: TemplateMetadata
     * 
     * @var array<string, string>
     */
    public array $rdfNamespaces = [];

    /**
     * Used by: TemplateMetadata
     * 
     * @var array<string, string>
     */
    public array $valueMaps = [];

    /**
     * Creates a metadata format descriptor
     * @param object $fields values to set in the descriptor
     */
    public function __construct(object $fields = []) {
        foreach ((array) $fields as $k => $v) {
            $this->$k = is_array($this->$k ?? null) ? (array) $v : $v;
        }
    }
}
