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
 * - `schemaProp` - metadata property storing resource's CMDI profile URL
 * - `templateDir` - path to a directory storing XML templates;
 *    each template should have exactly same name as the CMDI profile id, e.g. `clarin.eu:cr1:p_1290431694580.xml`
 * - `defaultLang` - default language to be used when the template doesn't explicitly specify one
 * 
 * Optional metadata format definition properties:
 * - `propNmsp[prefix]` - an array of property URLs namespaces used in the template
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
 *     - `@propUri1/propUri2` - get another resource URL from the `propUri1` metadata
 *       property value, then use the `propUri2` metadata property value of this resource
 *     - `@propUri` in a tag having the `ComponentId` attribute - inject the CMDI component
 *       identified by the `ComponentId` attribute taking the resource `propUri` metadata
 *       property points to as its base resource
 *     - `NOW` - get the current time
 *     - `URL` - get the resource's repository URL
 *     - `ID` - get the resource's ACDH repo UUID
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
 * - `dateFormat="FORMAT"` (default native precision read from the resource metadata value)
 *   can be `Date` or `DateTime` which will automatically adjust date precision.  Watch out
 *   as when present it will also naivly process any string values (cutting them or appending
 *   with a default time).
 * - `format="FORMAT"` forces an URL value fetched according to the `val` attribute to be
 *   extended with a `?format=FORMAT`. Allows to provide links to particular serialization
 *   of repository objects when the default one (typically the repository GUI) is not the
 *   desired one. The `asXML` attribute takes a precedense.
 *   Doesn't work for special `val` attribute values of `NOW`, `URL` and `OAIURI`.
 * - `valueMapProp="RDFpropertyURL"` causes value denoted by the `val` attribute to be
 *   mapped to another values using a given RDF property. The value must be an URL
 *   (e.g. a SKOS concept URL) which is then resolved to an RDF graph and all the values
 *   of indicated property are returned.
 * - `valueMapKeepSrc="false"` if present, removes the original value fetched according to the
 *   `val` attribute and returns only values fetched according to the `valueMapProp` attribute.
 *   Taken into account only if `valueMapProp` provided and not empty.
 * 
 * @author zozlak
 */
class LiveCmdiMetadata implements MetadataInterface {

    static private $mapper;

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

        if (self::$mapper === null) {
            self::$mapper = new ValueMapper();
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
        $remove = false;
        if ($el->hasAttribute('val')) {
            $remove = $this->insertValue($el);
        }

        $chToRemove = [];
        foreach ($el->childNodes as $ch) {
            if ($ch instanceof DOMElement) {
                $chRemove = $this->processElement($ch);
                if ($chRemove) {
                    $chToRemove[] = $ch;
                }
            }
        }
        foreach ($chToRemove as $i) {
            $el->removeChild($i);
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
        } else if ($val === 'ID') {
            $format = $el->getAttribute('format');
            $format = !empty($format) ? '?format=' . urlencode($format) : '';
            $el->textContent = $this->res->getId() . $format;
            $remove          = false;
        } else if ($val === 'URI' || $val === 'URL') {
            $el->textContent = $this->res->getUri(true);
            $remove          = false;
        } else if ($val === 'OAIURI') {
            $id              = urlencode($this->res->getMetadata()->getResource($this->format->uriProp)->getUri());
            $prefix          = urlencode($this->format->metadataPrefix);
            $el->textContent = $this->format->info->baseURL . '?verb=GetRecord&metadataPrefix=' . $prefix . '&identifier=' . $id;
            $remove          = false;
        } else if ($val !== '') {
            list('prop' => $prop, 'subprop' => $subprop, 'extUriProp' => $extUriProp, 'inverse' => $inverse) = $this->parseVal($val);
            if ($inverse) {
                $meta = $this->getInverseResources($prop);
            } else {
                $meta = $this->res->getMetadata();
            }
            $component = $el->getAttribute('ComponentId');
            if (!empty($component) && empty($subprop)) {
                $this->insertCmdiComponents($el, $meta, $component, $prop);
            } else {
                $this->insertMetaValues($el, $meta, $prop, $subprop, $extUriProp);
            }
        }

        $el->removeAttribute('val');
        $el->removeAttribute('count');
        $el->removeAttribute('lang');
        $el->removeAttribute('format');
        $el->removeAttribute('dateFormat');
        $el->removeAttribute('asXML');
        $el->removeAttribute('valueMapProp');
        $el->removeAttribute('valueMapKeepSrc');
        return $remove;
    }

    /**
     * Parses the `val` attribute into three components and returns them as 
     * an array.
     * 
     * The components are:
     * - `prop` the metadata property to be read
     * - `subprop` the YAML object key (null if the property value should be
     *   taken as it is)
     * - `extUriProp` if `prop` value points to a resource, metadata property
     *   which should be read from the target resource's metadata
     * - `inverse` boolean value indicating if `extUriProp` points to the
     *   external resource (`false`) or if the external resource is pointing
     *   to the current one (`true`)
     * 
     * @param string $val
     * @return array
     */
    private function parseVal(string $val): array {
        $inverse    = false;
        $extUriProp = null;
        $prop       = substr($val, 1);
        if (substr($val, 0, 1) === '@') {
            $tmp  = explode('/', $prop);
            $prop = $tmp[0];
            if (count($tmp) > 1) {
                $extUriProp = $this->replacePropNmsp($tmp[1]);
            }
        }
        if (substr($prop, 0, 1) == '^') {
            $prop    = substr($prop, 1);
            $inverse = true;
        }
        $prop    = $this->replacePropNmsp($prop);
        $i       = strpos($prop, '[');
        $subprop = null;
        if ($i !== false) {
            $subprop = substr($prop, $i + 1, -1);
            $prop    = substr($prop, 0, $i);
        }
        return [
            'prop'       => $prop,
            'subprop'    => $subprop,
            'extUriProp' => $extUriProp,
            'inverse'    => $inverse,
        ];
    }

    /**
     * Inserts a value by injecting an external CMDI template.
     * @param DOMElement $el
     * @param Resource $meta
     * @param string $component
     * @param string $prop
     */
    private function insertCmdiComponents(DOMElement $el, Resource $meta,
                                          string $component, string $prop) {
        $oldMeta = $this->res->getMetadata();

        $format                = clone($this->format);
        $format->schemaDefault = null;

        $count = $el->getAttribute('count');
        if (empty($count)) {
            $count = '1';
        }

        $resources = [];
        foreach ($meta->all($prop) as $i) {
            $resources[] = $this->res->getFedora()->getResourceById($i);
            if ($count === '1') {
                break;
            }
        }
        if (in_array($count, ['1', '+']) && count($resources) === 0) {
            $graph       = new Graph();
            $meta        = $graph->addLiteral('https://dummy.res', $this->format->schemaProp, $component);
            $this->res->setMetadata($graph->resource('https://dummy.res'));
            $resources[] = $this->res;
        }

        try {
            foreach ($resources as $res) {
                $meta         = $res->getMetadata();
                $meta->delete($this->format->schemaProp);
                $meta->addLiteral($this->format->schemaProp, $component);
                $res->setMetadata($meta);
                $componentObj = new LiveCmdiMetadata($res, new stdClass(), $format);
                $componentXml = $componentObj->getXml();
                $componentXml = $el->ownerDocument->importNode($componentXml, true);
                $el->parentNode->appendChild($componentXml);
            }
        } catch (RuntimeException $ex) {
            
        }

        $this->res->setMetadata($oldMeta);
    }

    /**
     * Inserts a value from metadata.
     * @param DOMElement $el
     * @param Resource $meta
     * @param string $prop
     * @param string|null $subprop
     * @param string|null $extUriProp
     */
    private function insertMetaValues(DOMElement $el, Resource $meta,
                                      string $prop, ?string $subprop,
                                      ?string $extUriProp) {
        $lang       = ($el->getAttribute('lang') ?? '' ) === 'true';
        $asXml      = ($el->getAttribute('asXML') ?? '' ) === 'true';
        $count      = $el->getAttribute('count');
        $dateFormat = $el->getAttribute('dateFormat');
        $format     = $el->getAttribute('format');
        $valueMap   = $el->getAttribute('valueMapProp');
        $keepSrc    = $el->getAttribute('valueMapKeepSrc');
        if (empty($count)) {
            $count = '1';
        }
        if (!empty($format)) {
            $format = '?format=' . urlencode($format);
        }
        $valueMap = $this->replacePropNmsp($valueMap);

        $values = [];
        foreach ($meta->all($prop) as $i) {
            if ($extUriProp !== null && $i instanceof Resource) {
                $metaTmp = $this->res->getFedora()->getResourceById($i)->getMetadata();
                foreach ($metaTmp->all($extUriProp) as $j) {
                    $this->collectMetaValue($values, $j, null, $dateFormat);
                }
            } else {
                $this->collectMetaValue($values, $i, $subprop, $dateFormat);
            }
        }

        if ($valueMap) {
            foreach ($values as $lang => &$i) {
                $tmp = [];
                foreach ($i as $j) {
                    $tmp = array_merge($tmp, self::$mapper->getMapping($j, $valueMap));
                }
                if ($keepSrc) {
                    $i = array_merge($i, $tmp);
                } else {
                    $i = $tmp;
                }
            }
            unset($i);
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
                $ch->removeAttribute('asXML');
                $ch->removeAttribute('dateFormat');
                $ch->removeAttribute('format');
                $ch->removeAttribute('valueMapProp');
                $ch->removeAttribute('valueMapKeepSrc');
                if ($asXml) {
                    $df = $ch->ownerDocument->createDocumentFragment();
                    $df->appendXML($value);
                    $ch->appendChild($df);
                } else {
                    $ch->textContent = $value . (!empty($value) ? $format : '');
                }
                if ($lang && $language !== '') {
                    $ch->setAttribute('xml:lang', $language);
                }
                $parent->insertBefore($ch, $el);
            }
        }
    }

    /**
     * Extracts metadata value from a given EasyRdf node
     * @param array $values
     * @param Literal $metaVal
     * @param type $subprop
     */
    private function collectMetaValue(array &$values, $metaVal, $subprop,
                                      $dateFormat) {
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
        switch ($dateFormat) {
            case 'Date':
                $value = substr($value, 0, 10);
                break;
            case 'DateTime':
                $value = $value . substr('0000-01-01T00:00:00Z', strlen($value));
                break;
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

    /**
     * Prepares fake resource metadata allowing to resolve reverse properties
     * resource links.
     * @param string $prop
     * @return \EasyRdf\Resource
     */
    private function getInverseResources(string $prop): Resource {
        $fedora    = $this->res->getFedora();
        $graph     = new Graph();
        $meta      = $graph->resource('.');
        $query     = new SimpleQuery('SELECT ?res WHERE {?res ?@ ?@.}', [$prop, $this->res->getId()]);
        $resources = $fedora->runQuery($query);
        foreach ($resources as $i) {
            $res = $fedora->getResourceByUri((string) $i->res);
            $meta->addResource($prop, $res->getId());
        }
        return $meta;
    }

}
