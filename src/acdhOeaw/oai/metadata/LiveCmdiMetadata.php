<?php

/**
 * The MIT License
 *
 * Copyright 2018 Austrian Centre for Digital Humanities.
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

namespace acdhOeaw\oai\metadata;

use DOMDocument;
use DOMElement;
use RuntimeException;
use stdClass;
use EasyRdf\Literal;
use EasyRdf\Resource;
use EasyRdf\Graph;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
use acdhOeaw\fedora\metadataQuery\SimpleQuery;
use acdhOeaw\oai\data\MetadataFormat;

/**
 * Creates &lt;metadata&gt; element by filling in an XML template with values
 * read from the repository resource's metadata.
 * 
 * Required metadata format definitition properties:
 * - `uriProp` - metadata property storing resource's OAI-PMH id
 * - `idProp` - metadata property identifying a repository resource
 * - `labelProp` - metadata property storing repository resource label
 * - `schemaProp` - metadata property storing resource's CMDI profile URI
 * - `templateDir` - path to a directory storing XML templates;
 *    each template should have exactly same name as the CMDI profile id, e.g. `clarin.eu:cr1:p_1290431694580.xml`
 * - `defaultLang` - default language to be used when the template doesn't explicitly specify one
 * 
 * Optional metadata format definition properties:
 * - `propNmsp[prefix]` - an array of property URIs namespaces used in the template
 * - `schemaDefault` provides a default CMDI profile (e.g. `clarin.eu:cr1:p_1290431694580.xml`)
 *   to be used when a resource's metadata don't contain the `schemaProp` or none of its values
 *   correspond to an existing CMDI template.
 *   If `schemaDefault` isn't provided, resources which don't contain the `schemaProp`
 *   in their metadata are automatically excluded from the OAI-PMH search.
 * - `schemaEnforce` if provided, only resources with a given value of the `schemaProp`
 *   are processed.
 * 
 * XML tags in the template can be annotated with following attributes:
 * - `val="valuePath"` specifies how to get the value. Possible `valuePath` variants are:
 *     - `/propUri` - get a value from a given metadata property value
 *     - `/propUri[key]` - parse given metadata property value as YAML and take the value 
 *       at the key `key`
 *     - `@propUri1/propUri2` - get another resource URI from the `propUri1` metadata
 *       property value, then use the `propUri2` metadata property value of this resource
 *     - `NOW` - get the current time
 *     - `URI` - get the resource's repository URI
 *     - `OAIURI` - get the resource's OAI-PMH ID
 * - `count="N"` (default `1`)
 *     - when "*" and metadata contain no property specified by the `val` attribute
 *       the tag is removed from the template;
 *     - when "*" or "+" and metadata contain many properties specified by the `val` attribute
 *       the tag is repeated for each metadata property value
 *     - when "1" or "+" and metadata contain no property specified by the `val` attribute
 *       the tag is left empty in the template;
 *     - when "1" and metadata contain many properties specified by the `val` attribute
 *       first metadata property value is used
 * - `lang="true"` if present and a metadata property value contains information about
 *   the language, the `xml:lang` attribute is added to the template tag
 * - `asXML="true"` if present, value specified with the `val` attribute is parsed and added
 *   as XML
 * 
 * @author zozlak
 */
class LiveCmdiMetadata implements MetadataInterface {

    /**
     * Repository resource object
     * @var \acdhOeaw\fedora\FedoraResource
     */
    private $res;

    /**
     * Metadata format descriptor
     * @var \acdhOeaw\oai\data\MetadataFormat
     */
    private $format;

    /**
     * Path to the XML template file
     * @var string
     */
    private $template;

    /**
     * Creates a metadata object for a given repository resource.
     * 
     * @param FedoraResource $resource repository resource object
     * @param stdClass $sparqlResultRow SPARQL search query result row 
     * @param MetadataFormat $format metadata format descriptor
     *   describing this resource
     */
    public function __construct(FedoraResource $resource,
                                stdClass $sparqlResultRow,
                                MetadataFormat $format) {
        $this->res    = $resource;
        $this->format = $format;

        $formats = $this->res->getMetadata()->all($this->format->schemaProp);
        foreach ($formats as $i) {
            $i    = preg_replace('|^.*(clarin.eu:[^/]+).*$|', '\\1', (string) $i);
            $path = $this->format->templateDir . '/' . $i . '.xml';
            if (file_exists($path)) {
                $this->template = $path;
                break;
            }
        }
        if ($this->template === null && !empty($this->format->schemaDefault)) {
            $this->template = $this->format->templateDir . '/' . $this->format->schemaDefault . '.xml';
        }
        if (empty($this->template)) {
            throw new RuntimeException('No CMDI template matched');
        }
    }

    /**
     * Creates resource's XML metadata
     * 
     * @return DOMElement 
     */
    public function getXml(): DOMElement {
        $doc                     = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->load($this->template);
        $this->processElement($doc->documentElement);
        return $doc->documentElement;
    }

    /**
     * Applies metadata format restrictions.
     * 
     * @param MetadataFormat $format
     * @param string $resVar
     * @return string
     */
    public static function extendSearchQuery(MetadataFormat $format,
                                             string $resVar): string {
        $query = '';
        if (!empty($format->schemaEnforce)) {
            $param = [$format->schemaProp, '^' . $format->schemaEnforce . '$'];
            $query = new SimpleQuery('{' . $resVar . ' ?@ ?schemaUri . FILTER regex(str(?schemaUri), ?#)}', $param);
            $query = $query->getQuery();
        } else if (empty($format->schemaDefault)) {
            $query = new SimpleQuery($resVar . ' ?@ ?schemaUri .', [$format->schemaProp]);
            $query = $query->getQuery();
        }
        return $query;
    }

    /**
     * Recursively processes all XML elements
     * @param DOMElement $el DOM element to be processed
     */
    private function processElement(DOMElement $el): bool {
        $toRemove = [];
        foreach ($el->childNodes as $ch) {
            if ($ch instanceof DOMElement) {
                $remove = $this->processElement($ch);
                if ($remove) {
                    $toRemove[] = $ch;
                }
            }
        }
        foreach ($toRemove as $i) {
            $el->removeChild($i);
        }

        $remove = false;
        if ($el->hasAttribute('val')) {
            $remove = $this->insertValue($el);
        }
        return $remove;
    }

    /**
     * Injects metadata values into a given DOM element of the CMDI template.
     * @param DOMElement $el DOM element to be processes
     * @return bool should `$el` DOMElement be removed from the document
     */
    private function insertValue(DOMElement $el): bool {
        $val = $el->getAttribute('val');

        $remove = true;
        if ($val === 'NOW') {
            $el->textContent = date('Y-m-d');
            $remove          = false;
        } else if ($val === 'URI') {
            $el->textContent = $this->res->getMetadata()->getResource($this->format->uriProp)->getUri();
            $remove          = false;
        } else if ($val === 'OAIURI') {
            $id              = urlencode($this->res->getMetadata()->getResource($this->format->uriProp)->getUri());
            $prefix          = urlencode($this->format->metadataPrefix);
            $el->textContent = $this->format->info->baseURL . '?verb=GetRecord&metadataPrefix=' . $prefix . '&identifier=' . $id;
            $remove          = false;
        } else {
            $this->insertMetaValues($el, $val);
        }

        $el->removeAttribute('val');
        return $remove;
    }

    /**
     * Fetches values from repository resource's metadata and creates corresponding
     * CMDI parts.
     * @param DOMElement $el
     * @param string $val DOMElement's `val` attribute value
     */
    private function insertMetaValues(DOMElement $el, string $val) {
        $meta = $this->res->getMetadata();

        $extUriProp = null;
        $prop       = substr($val, 1);
        if (substr($val, 0, 1) === '@') {
            $i          = strpos($val, '/');
            $prop       = substr($val, 1, $i - 1);
            $extUriProp = $this->replacePropNmsp(substr($val, $i + 1));
        }
        $prop    = $this->replacePropNmsp($prop);
        $i       = strpos($prop, '[');
        $subprop = null;
        if ($i !== false) {
            $subprop = substr($prop, $i + 1, -1);
            $prop    = substr($prop, 0, $i);
        }

        $lang  = ($el->getAttribute('lang') ?? '' ) === 'true';
        $asXml = ($el->getAttribute('asXML') ?? '' ) === 'true';
        $count = $el->getAttribute('count');
        if (empty($count)) {
            $count = '1';
        }
        $values = [];
        foreach ($meta->all($prop) as $i) {
            if ($extUriProp !== null && $i instanceof Resource) {
                $metaTmp = $this->res->getFedora()->getResourceById($i)->getMetadata();
                foreach ($metaTmp->all($extUriProp) as $j) {
                    $this->collectMetaValue($values, $j, null);
                }
            } else {
                $this->collectMetaValue($values, $i, $subprop);
            }
        }

        if (count($values) === 0 && in_array($count, ['1', '+'])) {
            $values[''] = [''];
        }
        if ($count === '1') {
            if (isset($values[$this->format->defaultLang])) {
                $values = [$this->format->defaultLang => [$values[$this->format->defaultLang][0]]];
            } else if (isset($values[''])) {
                $values = ['' => [$values[''][0]]];
            } else {
                $values = ['' => [$values[array_keys($values)[0]][0]]];
            }
        }

        $parent = $el->parentNode;
        foreach ($values as $language => $tmp) {
            foreach ($tmp as $value) {
                $ch = $el->cloneNode(true);
                $ch->removeAttribute('val');
                $ch->removeAttribute('count');
                $ch->removeAttribute('lang');
                $ch->removeAttribute('getLabel');
                if ($asXml) {
                    $df = $ch->ownerDocument->createDocumentFragment();
                    $df->appendXML($value);
                    $ch->appendChild($df);
                } else {
                    $ch->textContent = $value;
                }
                if ($lang && $language !== '') {
                    $ch->setAttribute('xml:lang', $language);
                }
                $parent->appendChild($ch);
            }
        }
    }

    /**
     * Extracts metadata value from a given EasyRdf node
     * @param array $values
     * @param Literal $metaVal
     * @param type $subprop
     */
    private function collectMetaValue(array &$values, $metaVal, $subprop) {
        $language = '';
        $value    = (string) $metaVal;
        if ($metaVal instanceof Literal) {
            $language = $metaVal->getLang();
        }
        if (!isset($values[$language])) {
            $values[$language] = [];
        }
        if ($subprop !== null) {
            $value = yaml_parse($value)[$subprop];
        }
        $values[$language][] = $value;
    }

    /**
     * 
     * @param string $prop
     * @return string
     */
    private function replacePropNmsp(string $prop): string {
        $nmsp = substr($prop, 0, strpos($prop, ':'));
        if ($nmsp !== '' && isset($this->format->propNmsp[$nmsp])) {
            $prop = str_replace($nmsp . ':', $this->format->propNmsp[$nmsp], $prop);
        }
        return $prop;
    }

}
