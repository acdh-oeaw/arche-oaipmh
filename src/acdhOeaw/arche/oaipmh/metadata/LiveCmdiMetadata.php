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

namespace acdhOeaw\arche\oaipmh\metadata;

use DOMDocument;
use DOMElement;
use RuntimeException;
use stdClass;
use EasyRdf\Literal;
use EasyRdf\Resource;
use EasyRdf\Graph;
use zozlak\queryPart\QueryPart;
use acdhOeaw\arche\lib\RepoResourceDb;
use acdhOeaw\arche\lib\RepoResourceInterface;
use acdhOeaw\arche\lib\SearchConfig;
use acdhOeaw\arche\oaipmh\data\MetadataFormat;

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
 * - `idNmsp[prefix]` - an array of id URIs namespaces used in the template
 * - `schemaDefault` provides a default CMDI profile (e.g. `clarin.eu:cr1:p_1290431694580.xml`)
 *   to be used when a resource's metadata don't contain the `schemaProp` or none of its values
 *   correspond to an existing CMDI template.
 *   If `schemaDefault` isn't provided, resources which don't contain the `schemaProp`
 *   in their metadata are automatically excluded from the OAI-PMH search.
 * - `schemaEnforce` if provided, only resources with a given value of the `schemaProp`
 *   are processed.
 * - `iiifBaseUrl` used for `val="IIIFURL` (see below)
 * 
 * XML tags in the template can be annotated with following attributes:
 * - `val="valuePath"` specifies how to get the value. Possible `valuePath` variants are:
 *     - `/propUri` - get a value from a given metadata property value
 *     - `/propUri[key]` - parse given metadata property value as YAML and take the value 
 *       at the key `key`
 *     - `@propUri1/propUri2` - get another resource URL from the `propUri1` metadata
 *       property value, then use the `propUri2` metadata property value of this resource.
 *       If inverse of `propoUri1` is needed, prepend it with a dash: `@^propUri1/propUri2`.
 *     - `@propUri` in a tag having the `ComponentId` attribute - inject the CMDI component
 *       identified by the `ComponentId` attribute taking the resource `propUri` metadata
 *       property points to as its base resource.
 *       If inverse of `propoUri1` is needed, prepend it with a dash: `@^propUri1`.
 *     - `NOW` - current time
 *     - `URL`, `URI` - resource's repository URL
 *     - `METAURL` - resource's metadata repository URL
 *     - `ID`, `ID@NMSP`, `ID&NMSP` - value of resource's `idProp` metadata property.
 *       `ID@NMSP` and `ID&NMSP` allow to indicate the value namespace. They differ
 *       in regard to the situation when a value in a given namespace doesn't exist.
 *       In such a case `ID@NMSP` returns just any id and `ID&NMSP` returns empty value.
 *       `NMSP` should be one of the keys provided in the `idNmsp[prefix]` metadata
 *       format configuration property (see above).
 *       Remark - when using the `ID&NMSP` syntax remember about proper XML entity
 *       escaping - ``ID&amp;NMSP`.
 *     - `OAIID` - resources's OAI-PMH identifier
 *     - `OAIURL` - URL of the OAI-PMH `GetRecord` request returning a given resource
 *       metadata in the currently requested metadata format
 *     - `IIIFURL` - resource's IIIF URL which is a concatenation of the metadata format's
 *       `iiifBaseUrl` parameter value and the path part of the repository resource ID in 
 *       the `id` namespace.
 *       This is a special corner case for ARCHE to Kulturpool synchronization.
 *       It requires `idNmsp[id]` ID namespace to be defined in the metadata format config
 *       (see above).
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
 * - `replaceXMLTag="true"` if present, value specified with the `val` attribute substitus the
 *   XML tag itself instead of being injected as its value.
 * - `asAttribute="targetAttribute"` if present, value specified with the `val` attribute is
 *   stored as a given attribute's value. Takes precedense over `replaceXMLTag` and forces
 *   `asXML="false"`.
 * - `dateFormat="FORMAT"` (default native precision read from the resource metadata value)
 *   can be `Date` or `DateTime` which will automatically adjust date precision.  Watch out
 *   as when present it will also naivly process any string values (cutting them or appending
 *   with a default time).
 * - `format="FORMAT"` extend the URL returned according to the `val` attribute with
 *   `?format=FORMAT` (when `val="ID"`) or `@format=FORMAT` (when `val="OAIID"`).
 *   This allows to provide URLs redirecting to particular dissemination services.
 *   It's worth noting that using `val="OAIID"` is faster.
 *   The `asXML` attribute takes a precedense.
 *   Doesn't work for special `val` attribute values of `NOW`, `URL` and `OAIURI`.
 * - `valueMapProp="RDFpropertyURL"` causes value denoted by the `val` attribute to be
 *   mapped to another values using a given RDF property. The `val` attribute value must be 
 *   an URL (e.g. a SKOS concept URL) which returns an RDF graph. All `valueMapProp` property
 *   values from the fetched graph are taken as a template values.
 *   of indicated property are returned.
 * - `valueMapKeepSrc="false"` if present, removes the original value fetched according to the
 *   `val` attribute and returns only values fetched according to the `valueMapProp` attribute.
 *   Taken into account only if `valueMapProp` provided and not empty.
 * - `ComponentId` specifies a component (template) to substitue a given tag (see the `val`
 *   attribute description). The component name should match the template file name without the
 *   .xml extension. When the `ComponentId` is used the actual tag in the template is 
 *   not important because it's anyway replaced by the component's root tag.
 * - `id` if has value of '#', it is filled in with a globally unique sequence
 * 
 * @author zozlak
 */
class LiveCmdiMetadata implements MetadataInterface {

    const FAKE_ROOT_TAG = 'fakeRoot';

    /**
     * Value mapping cache
     * @var ValueMapper
     */
    static private $mapper;

    /**
     * Sequence for id generation
     * @var int
     */
    static private $idSeq = 1;

    /**
     * Repository resource object
     * @var RepoResourceDb
     */
    private $res;

    /**
     * Metadata format descriptor
     * @var MetadataFormat
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
     * @param RepoResourceDb $resource a repository 
     *   resource object
     * @param object $searchResultRow SPARQL search query result row 
     * @param MetadataFormat $format metadata format descriptor
     *   describing this resource
     */
    public function __construct(RepoResourceDb $resource,
                                object $searchResultRow, MetadataFormat $format) {
        $this->res    = $resource;
        $this->format = $format;

        $formats = $this->res->getGraph()->all($this->format->schemaProp);
        foreach ($formats as $i) {
            $i    = preg_replace('|[^-A-Za-z0-9_]|', '_', (string) $i);
            $path = $this->format->templateDir . '/' . $i . '.xml';
            if (file_exists($path)) {
                $this->template = $path;
                break;
            }
        }
        if ($this->template === null && !empty($this->format->schemaDefault)) {
            $default = preg_replace('|[^-A-Za-z0-9_]|', '_', $this->format->schemaDefault);
            $this->template = $this->format->templateDir . '/' . $default . '.xml';
        }
        if (empty($this->template) || !file_exists($this->template)) {
            throw new RuntimeException('No CMDI template matched');
        }

        if (self::$mapper === null) {
            self::$mapper = new ValueMapper();
        }
    }

    /**
     * Creates resource's XML metadata
     *
     * If the template's root element has an `val` attribute a fake
     * root element is introduced to the template to assure it will be a valid
     * XML after the substitution (XML documents have to have a single root
     * element).
     *
     * @return DOMElement 
     */
    public function getXml(): DOMElement {
        $doc                     = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->load($this->template);

        // a special case when a single root element might be missing
        $el = $doc->documentElement;
        if ($el->getAttribute('val') !== '') {
            $oldRoot = $doc->removeChild($el);
            $newRoot = $doc->createElement(self::FAKE_ROOT_TAG);
            $doc->appendChild($newRoot);
            $newRoot->appendChild($oldRoot);
        }
        $this->processElement($doc->documentElement);
        return $doc->documentElement;
    }

    /**
     * Applies metadata format restrictions.
     * 
     * @param MetadataFormat $format
     * @return QueryPart
     */
    static public function extendSearchFilterQuery(MetadataFormat $format): QueryPart {
        if (!empty($format->schemaEnforce)) {
            // Handle only resources having `schemaProp` metadata property value equal to the `schemaEnforce` value.
            return new QueryPart(
                "SELECT id FROM metadata WHERE property = ? AND substring(value, 1, 1000) = ?",
                [$format->schemaProp, $format->schemaEnforce]
            );
        }
        return new QueryPart();
    }

    static public function extendSearchDataQuery(MetadataFormat $format): QueryPart {
        return new QueryPart();
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
        if ($el->getAttribute('id') === '#') {
            $el->setAttribute('id', 'id' . self::$idSeq);
            self::$idSeq++;
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
        $val    = $el->getAttribute('val');
        $asAttr = $el->getAttribute('asAttribute');

        $remove = true;
        if ($val === 'NOW') {
            $this->insertContent($el, date('Y-m-d'), $asAttr);
            $remove = false;
        } else if ($val === 'ID' || substr($val, 0, 3) === 'ID&' || substr($val, 0, 3) === 'ID@') {
            $format = $el->getAttribute('format');
            $format = !empty($format) ? '?format=' . urlencode($format) : '';
            $value  = $this->getResourceId(substr($val, 2, 1), substr($val, 3), $format);
            $this->insertContent($el, $value, $asAttr);
            $remove = false;
        } else if ($val === 'URI' || $val === 'URL') {
            $this->insertContent($el, $this->res->getUri(), $asAttr);
            $remove = false;
        } else if ($val === 'METAURL') {
            $this->insertContent($el, $this->res->getUri() . '/metadata', $asAttr);
            $remove = false;
        } else if ($val === 'OAIID') {
            $id     = (string) $this->res->getGraph()->get($this->format->uriProp);
            $format = $el->getAttribute('format');
            $format = !empty($format) ? '@format=' . urlencode($format) : '';
            $this->insertContent($el, $id . $format, $asAttr);
            $remove = false;
        } else if ($val === 'OAIURL') {
            $id     = rawurlencode((string) $this->res->getGraph()->get($this->format->uriProp));
            $prefix = rawurlencode($this->format->metadataPrefix);
            $value  = $this->format->info->baseURL . '?verb=GetRecord&metadataPrefix=' . $prefix . '&identifier=' . $id;
            $this->insertContent($el, $value, $asAttr);
            $remove = false;
        } else if ($val === 'IIIFURL') {
            $tmp    = $this->getResourceId('&', 'id', '');
            $tmp    = parse_url($tmp);
            $tmp    = !empty($tmp['path']) ? $this->format->iiifBaseUrl . $tmp['path'] : '';
            $this->insertContent($el, $tmp, $asAttr);
            $remove = false;
        } else if ($val !== '') {
            list('prop' => $prop, 'recursive' => $recursive, 'subprop' => $subprop, 'extUriProp' => $extUriProp, 'inverse' => $inverse) = $this->parseVal($val);
            if ($recursive || $inverse) {
                $meta = $this->getResourcesByPath($prop, $recursive, $inverse);
            } else {
                $meta = $this->res->getGraph();
            }
            $component = $el->getAttribute('ComponentId');
            if (!empty($component) && empty($subprop)) {
                $this->insertCmdiComponents($el, $meta, $component, $prop);
            } else {
                $this->insertMetaValues($el, $meta, $prop, $subprop, $extUriProp);
            }
        }

        $this->removeTemplateAttributes($el);
        return $remove;
    }

    /**
     * Parses the `val` attribute into components and returns them as 
     * an array.
     * 
     * The components are:
     * - `prop` the metadata property to be read
     * - `recursive` should the property be follow recursively?
     * - `subprop` the YAML object key (null if the property value should be
     *   taken as it is)
     * - `extUriProp` if `prop` value points to a resource, metadata property
     *   which should be read from the target resource's metadata
     * - `inverse` boolean value indicating if `extUriProp` points to the
     *   external resource (`false`) or if the external resource is pointing
     *   to the current one (`true`)
     * 
     * @param string $val
     * @return array<string, mixed>
     */
    private function parseVal(string $val): array {
        $recursive  = false;
        $inverse    = false;
        $extUriProp = null;
        $prop       = substr($val, 1);
        if (substr($val, 0, 1) === '@') {
            $tmp  = explode('/', $prop);
            $prop = $tmp[0];
            if (substr($prop, -1) === '*') {
                $recursive = true;
                $prop      = substr($prop, 0, -1);
            }
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
            'recursive'  => $recursive,
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
        $oldMeta = $this->res->getGraph();

        $format                = clone($this->format);
        $format->schemaDefault = null;

        $count = $el->getAttribute('count');
        if (empty($count)) {
            $count = '1';
        }

        $resources = [];
        foreach ($meta->all($prop) as $i) {
            if ($i instanceof Literal) {
                $resources[] = $this->res;
            } elseif (count($i->propertyUris()) === 0) {
                $resources[] = $this->res->getRepo()->getResourceById($i);
            } else {
                $resTmp      = new RepoResourceDb($i->getUri(), $this->res->getRepo());
                $resTmp->setGraph($i);
                $resources[] = $resTmp;
            }
            if ($count === '1') {
                break;
            }
        }
        if (in_array($count, ['1', '+']) && count($resources) === 0) {
            $graph       = new Graph();
            $meta        = $graph->addLiteral('https://dummy.res', $this->format->schemaProp, $component);
            $this->res->setGraph($graph->resource('https://dummy.res'));
            $resources[] = $this->res;
        }

        try {
            foreach ($resources as $res) {
                $meta         = $res->getGraph();
                $meta->delete($this->format->schemaProp);
                $meta->addLiteral($this->format->schemaProp, $component);
                $res->setGraph($meta);
                $componentObj = new LiveCmdiMetadata($res, new stdClass(), $format);
                $componentXml = $componentObj->getXml();
                if ($componentXml->nodeName === self::FAKE_ROOT_TAG) {
                    foreach ($componentXml->childNodes as $n) {
                        $nn = $el->ownerDocument->importNode($n, true);
                        $el->parentNode->appendChild($nn);
                    }
                } else {
                    $componentXml = $el->ownerDocument->importNode($componentXml, true);
                    $el->parentNode->appendChild($componentXml);
                }
            }
        } catch (RuntimeException $ex) {
            
        }

        $this->res->setGraph($oldMeta);
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
        $lang        = ($el->getAttribute('lang') ?? '' ) === 'true';
        $asXml       = ($el->getAttribute('asXML') ?? '' ) === 'true';
        $count       = $el->getAttribute('count');
        $dateFormat  = $el->getAttribute('dateFormat');
        $format      = $el->getAttribute('format');
        $valueMap    = $el->getAttribute('valueMapProp');
        $keepSrc     = $el->getAttribute('valueMapKeepSrc');
        $replaceTag  = $el->getAttribute('replaceXMLTag');
        $asAttribute = $el->getAttribute('asAttribute');
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
                if (count($i->propertyUris()) === 0) {
                    $metaTmp = $this->res->getRepo()->getResourceById($i)->getGraph();
                } else {
                    $metaTmp = $i;
                }
                foreach ($metaTmp->all($extUriProp) as $j) {
                    $this->collectMetaValue($values, $j, null, $dateFormat);
                }
            } else {
                $this->collectMetaValue($values, $i, $subprop, $dateFormat);
            }
        }
        if ($valueMap) {
            $mapped = [];
            foreach ($values as &$i) {
                foreach ($i as $j) {
                    $mapped = array_merge($mapped, self::$mapper->getMapping($j, $valueMap));
                }
                if (!$keepSrc) {
                    $i = [];
                }
            }
            unset($i);
            foreach ($mapped as $i) {
                $this->collectMetaValue($values, $i, null, $dateFormat);
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
                $ch = null;
                if ($asXml) {
                    $df = $el->ownerDocument->createDocumentFragment();
                    $df->appendXML($value);
                    if ($replaceTag) {
                        $ch = $df;
                    }
                } else {
                    $value = $value . (!empty($value) ? $format : '');
                    if (!empty($asAttribute)) {
                        /** @var DOMElement $ch */
                        $ch = $el->cloneNode(true);
                        $this->removeTemplateAttributes($ch);
                        $this->insertAttribute($ch, $asAttribute, $value);
                    } elseif ($replaceTag) {
                        $ch = $el->ownerDocument->createTextNode($value);
                    } else {
                        /** @var DOMElement $ch */
                        $ch              = $el->cloneNode(true);
                        $this->removeTemplateAttributes($ch);
                        $ch->textContent = $value;
                    }
                }
                if ($lang && $language !== '' && $ch instanceof DOMElement) {
                    $ch->setAttribute('xml:lang', $language);
                }
                $parent->insertBefore($ch, $el);
            }
        }
    }

    private function removeTemplateAttributes(DOMElement $ch): void {
        $ch->removeAttribute('val');
        $ch->removeAttribute('count');
        $ch->removeAttribute('lang');
        $ch->removeAttribute('getLabel');
        $ch->removeAttribute('asXML');
        $ch->removeAttribute('dateFormat');
        $ch->removeAttribute('format');
        $ch->removeAttribute('valueMapProp');
        $ch->removeAttribute('valueMapKeepSrc');
        $ch->removeAttribute('replaceXMLTag');
        $ch->removeAttribute('asAttribute');
    }

    /**
     * Extracts metadata value from a given EasyRdf node
     * @param array<string> $values
     * @param Literal|Resource $metaVal
     * @param ?string $subprop
     * @param ?string $dateFormat
     */
    private function collectMetaValue(array &$values,
                                      Literal | Resource $metaVal,
                                      ?string $subprop, ?string $dateFormat) {
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
        if ($nmsp !== '' && isset($this->format->propNmsp->$nmsp)) {
            $prop = str_replace($nmsp . ':', $this->format->propNmsp->$nmsp, $prop);
        }
        return $prop;
    }

    /**
     * Prepares fake resource metadata allowing to resolve inverse and/or 
     * recursively targetted resources.
     * @param string $prop
     * @param bool $recursive
     * @param bool $inverse
     * @return Resource
     */
    private function getResourcesByPath(string $prop, bool $recursive,
                                        bool $inverse): Resource {
        $repo = $this->res->getRepo();
        $id   = substr($this->res->getUri(), strlen($repo->getBaseUrl()));

        switch (($recursive ? 'r' : '') . ($inverse ? 'i' : '')) {
            case 'ri':
                $query = "
                    WITH t AS (SELECT * FROM get_relatives(?, ?, 999999, 0))
                    SELECT id FROM t WHERE n > 0 AND n = (SELECT max(n) FROM t)
                 ";
                $param = [$id, $prop];
                break;
            case 'r':
                $query = "
                    WITH t AS (SELECT * FROM get_relatives(?, ?, 0, -999999))
                    SELECT id FROM t WHERE n < 0 AND n = (SELECT min(n) FROM t)
                 ";
                $param = [$id, $prop];
                break;
            case 'i':
                $query = "SELECT id FROM relations WHERE property = ? AND target_id = ?";
                $param = [$prop, $id];
                break;
            default:
                throw new RuntimeException('It does not make sense for both $recursive and $inverse to be false');
        }

        $config               = new SearchConfig();
        $config->class        = get_class($this->res);
        $config->metadataMode = RepoResourceInterface::META_RESOURCE;
        $graph                = $repo->getGraphBySqlQuery($query, $param, $config);
        $resource             = $graph->resource($this->res->getUri());
        foreach ($graph->resourcesMatching($repo->getSchema()->searchMatch) as $i) {
            $resource->addResource($prop, $i);
        }
        return $resource;
    }

    private function getResourceId(string $method, string $namespace,
                                   string $format): string {
        $ids   = $this->res->getIds();
        $match = null;
        if (!empty($method)) {
            if (!isset($this->format->idNmsp->$namespace)) {
                throw new OaiException("namespace '$namespace' is not defined in the metadata format config");
            }
            $namespace = $this->format->idNmsp->$namespace;
            foreach ($ids as $i) {
                if (str_starts_with($i, $namespace)) {
                    $otherNmsp = false;
                    foreach ((array) $this->format->idNmsp as $j) {
                        if ($nmsp !== $j && str_starts_with($i, $j)) {
                            $otherNmsp = true;
                            break;
                        }
                    }
                    if (!$otherNmsp) {
                        $match = $i;
                        break;
                    } else {
                        $match = $i;
                    }
                }
            }
        }
        if ($match === null && $method !== '&') {
            $match = $ids[0] ?? $this->res->getUri();
        }
        if (!empty($match)) {
            $match .= $format;
        }
        return (string) $match;
    }

    private function insertAttribute(DOMElement $el, string $attribute,
                                     string $value): void {
        $p = strpos($attribute, ':');
        if ($p > 0) {
            $prefix = substr($attribute, 0, $p);
            $nmsp   = $el->lookupNamespaceUri($prefix);
        }
        if (!empty($prefix)) {
            $el->setAttributeNS($nmsp, $attribute, $value);
        } else {
            $el->setAttribute($attribute, $value);
        }
    }

    private function insertContent(DOMElement $el, string $value,
                                   ?string $attribute): void {
        if (!empty($attribute)) {
            $this->insertAttribute($el, $attribute, $value);
        } else {
            $el->textContent = $value;
        }
    }
}
