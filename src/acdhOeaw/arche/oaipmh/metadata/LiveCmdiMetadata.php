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

use DOMComment;
use DOMDocument;
use DOMElement;
use Exception;
use RuntimeException;
use stdClass;
use EasyRdf\Literal;
use EasyRdf\Resource;
use EasyRdf\Graph;
use zozlak\RdfConstants as RDF;
use zozlak\queryPart\QueryPart;
use acdhOeaw\arche\lib\RepoResourceDb;
use acdhOeaw\arche\lib\RepoResourceInterface;
use acdhOeaw\arche\lib\SearchConfig;
use acdhOeaw\arche\oaipmh\OaiException;
use acdhOeaw\arche\oaipmh\data\MetadataFormat;

/**
 * Creates <metadata> element by filling in an XML template with values
 * read from the repository resource's metadata.
 * 
 * Required metadata format definitition properties:
 * - `uriProp` - metadata property storing resource's OAI-PMH id
 * - `idProp` - metadata property identifying a repository resource
 * - `labelProp` - metadata property storing repository resource label
 * - `schemaProp` - metadata property storing resource's CMDI profile URL
 * - `resolverNmsp` - regular expression uniquely matching the resource's 
 *    identifier namespace allowing the content type negotation. Required for
 *    the `format` template attribute to generate proper URLs.
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
 * - `valueMaps[mapName]` - value maps to be used with the `valueMap` template attribute.
 *   Every map should be an object with source values being property names and target values
 *   being property values.
 * - `timeout` - if the template generation takes longer than the given time (in seconds)
 *   emit bunch of whitespaces to inform the client something's going on and keep the
 *   connectio alive
 * - `cache` - sets up LiveCmdiMetadata internal cache (separate from the global OAI-PMH cache).
 *   Just skip this configuration property to avoid using the internal cache.
 *     - `perResource` (true/false) should a clean cache be used for every OAI-PMH resource?
 *       Takes effect only for the GetRecords OAI-PMH verb. Having a shared cache is likely
 *       to speed up the response generation but can also significantly increase memory usage.
 *       Use with caution and probably combine with `skipClasses`/`includeClasses`.
 *       On the other hand per-resource only cache makes sense only for the complex template
 *       structure where there are chances for resources and/or subtemplates to be used more
 *       than once within a single top-level metadata record.
 *     - `skipClasses` (array of URIs) repository resources of given RDF classes will
 *       be excluded from caching
 *     - `includeClasses` (array of URIs) only repository resources of given RDF classes
 *       will be cached
 *     - `statistics` (true/false) should cache usage statistics be appended to each generated
 *       metadata record?
 *       This feature is useful for tuning the cache settings, especially selecting skipClasses
 *       and includeClasses filters but it shouldn't be used on production as it alters the
 *       output metadata schema.
 * 
 * XML tags in the template can be annotated with following attributes:
 * - `val="valuePath"` specifies how to get the value. Possible `valuePath` variants are:
 *     - `/propUri` - get a value from a given metadata property value
 *     - `/propUri[key]` - parse given metadata property value as YAML and take the value 
 *       at the key `key`
 *     - `@propUri1/propUri2` - get another resource URL from the `propUri1` metadata
 *       property value, then use the `propUri2` metadata property value of this resource.
 *       If inverse of `propoUri1` is needed, prepend it with a dash: `@^propUri1/propUri2`.
 *     - `@propUri` in a tag having the `ComponentId` attribute - inject the template
 *       indicated by the `ComponentId` attribute taking the resource to which the `propUri` 
 *       points to as template's base resource.
 *       If inverse of `propoUri` is needed, prepend it with a dash: `@^propUri`.
 *     - `NOW` - current time
 *     - `URL`, `URI` - resource's repository URL
 *     - `METAURL` - resource's metadata repository URL
 *     - `OAIID` - resources's OAI-PMH identifier
 *     - `OAIURL` - URL of the OAI-PMH `GetRecord` request returning a given resource
 *       metadata in the currently requested metadata format
 * - `dateFormat="FORMAT"` - when present, causes value to be interpreted as a date
 *   and formatted according to a given format. Formatting is applied before any
 *   further processing is done like applying `match`/`replace`/`aggregate`/`count`.
 *   Values which can't be parsed as dates are skipped. 
 *   `FORMAT` description can be found on the
 *    https://www.php.net/manual/en/datetime.format.php#format
 * - `match="regular expression"` - when present, only values matching a given
 *   regular expression are processed. It is applied to the values list returned according
 *   to the `val` (and, if specified, `dateFormat`) attribute and before `aggregate`
 *   attribute is applied.
 * - `replace="regular expression replace"` - works with the `match` attribute. Provides
 *   a way to adjust the matched value. Match placeholders use the backslash syntax
 *   (`\1` matches the first regex capture group, etc.)
 * - `aggregate="min or max"` - when present out of all values passing the `match`/`replace`
 *   step only a single value (minimum or maximum) is taken.
 * - `valueMap="mapName"` - name of the value map (defined in the metadata format config) to
 *   be applied to the value(s) denoted by the `val` attribute.
 *   Value map name can be preceeded with a `*`, `-` (default) or `+`:
 *   - `*` keep both original and mapped values, don't care if an original value doesn't have
 *     a mapping
 *   - `-` use only mapped values, if original value doesn't have a mapping, discard it
 *   - `+` use only mapped values but if original value doesn't have a mapping, keep the original
 *      value instead
 * - `valueMapProp="RDFpropertyURL"` maps the value indicated by the `val` attribute using
 *   an external RDF data.
 *   First, a value indicated by the `val` attribute is treated as an URL.  Its content is 
 *   downloaded and parsed as RDF. Then all values of the `valueMapProp` RDF property in the
 *   parsed RDF graph are taken as actual template values.
 *   This mechanism allows to resolve e.g. external SKOS vocabulary concepts, if only they
 *   are published in a way allowing to download the concept definition as an RDF.
 * - `valueMapKeepSrc="false"` if present, removes the original value fetched according to the
 *   `val` attribute and returns only values fetched according to the `valueMapProp` attribute.
 *   Taken into account only if `valueMapProp` provided and not empty.
 * - `count="N"` (default `1`)
 *     - when "*" and metadata contain no property specified by the `val` attribute
 *       the tag is removed from the template;
 *     - when "*" or "+" and metadata contain many properties specified by the `val` attribute
 *       the tag is repeated for each metadata property value
 *     - when "1" or "+" and metadata contain no property specified by the `val` attribute
 *       the tag is left empty in the template;
 *     - when "1" and metadata contain many properties specified by the `val` attribute
 *       first metadata property value is used
 * - `format="FORMAT"` - for all values being RDF resources or the `OAIID` generates
 *   an URL requesting response to be returned in a given format (e.g. `image/jpeg` or 
 *   `text/turtle`)
 * - `lang="true"` if present and a metadata property value contains information about
 *   the language, the `xml:lang` attribute is added to the template tag
 * - `asXML="true"` if present, value specified with the `val` attribute is parsed and added
 *   as XML
 * - `replaceXMLTag="true"` if present, value specified with the `val` attribute substitus the
 *   XML tag itself instead of being injected as its value.
 * - `asAttribute="targetAttribute"` if present, value specified with the `val` attribute is
 *   stored as a given attribute's value. Takes precedense over `replaceXMLTag` and forces
 *   `asXML="false"`.
 * - `ComponentId` specifies a template to substitue a given tag with. The template is being
 *   processed with a base resource(s) as defined by the `val` attribute.
 *   The attribute value should match the template file name without the .xml extension. 
 *   If a template file `{ComponentIdValue}_{BaseResourceRdfClass}.xml` exists, it's used
 *   instead of the `{ComponentIdValue}.xml` template. This allows for using different templates
 *   for different target resources.
 *   When the `ComponentId` is used the actual tag in the template is not important because it's 
 *   anyway replaced by the component's root tag.
 * - `id` if has value of '#', it is filled in with a globally unique sequence
 * 
 * @author zozlak
 */
class LiveCmdiMetadata implements MetadataInterface {

    const FAKE_ROOT_TAG     = 'fakeRoot';
    const STATS_TAG         = 'debugStats';
    const VALUEMAP_ALL      = '*';
    const VALUEMAP_FALLBACK = '+';
    const VALUEMAP_STRICT   = '-';

    /**
     * Value mapping cache
     */
    static private ?ValueMapper $mapper;

    /**
     * Sequence for id generation
     * @var int
     */
    static private int $idSeq = 1;

    /**
     * 
     * @var array<string, DOMDocument>
     */
    static private array $xmlCache = [];

    /**
     * 
     * @var array<string, RepoResourceDb>
     */
    static private array $rdfCache = [];

    /**
     * 
     * @var array<string, int>
     */
    static private array $cacheHits = ['rdf' => 0, 'xml' => 0];
    static private int $timeout   = 0;

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
    private $depth = -1;

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
        $formats = array_map('strval', $formats);
        $formats = array_combine($formats, array_map('strlen', $formats));
        arsort($formats);
        $formats = array_keys($formats);
        foreach ($formats as $i) {
            $i    = preg_replace('|[^-A-Za-z0-9_]|', '_', $i);
            $path = $this->format->templateDir . '/' . $i . '.xml';
            if (file_exists($path)) {
                $this->template = $path;
                break;
            }
        }
        if ($this->template === null && !empty($this->format->schemaDefault)) {
            $default        = preg_replace('|[^-A-Za-z0-9_]|', '_', $this->format->schemaDefault);
            $this->template = $this->format->templateDir . '/' . $default . '.xml';
        }
        if (empty($this->template) || !file_exists($this->template)) {
            throw new RuntimeException('No CMDI template matched');
        }

        if (!isset(self::$mapper)) {
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
     * @param int $depth subtemplates insertion depth
     * @param bool $cache should cache be used?
     * @return DOMElement 
     */
    public function getXml(int $depth = 0, bool $cache = true): DOMElement {
        $this->depth = $depth;

        // output something if the template generation takes to long to avoid timeouts
        if ($depth === 0) {
            self::$timeout = time();
        } elseif (time() - self::$timeout > ($this->format->timeout ?? PHP_INT_MAX)) {
            echo "                                                                ";
            ob_flush();
            flush();
            self::$timeout = time();
        }

        $cacheId = $this->getXmlCacheId();
        if ($cache && isset(self::$xmlCache[$cacheId])) {
            self::$cacheHits['xml']++;
            return self::$xmlCache[$cacheId]->documentElement;
        }

        $doc                     = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $res                     = $doc->load($this->template);
        $warning                 = libxml_get_last_error();
        if ($res === false || $warning !== false) {
            if ($warning) {
                $warning = " ($warning->message in file $warning->file:$warning->line)";
            }
            throw new Exception("Failed to parse $this->template template$warning");
        }

        // a special case when a single root element might be missing
        $el = $doc->documentElement;
        if ($el->getAttribute('val') !== '') {
            $oldRoot = $doc->removeChild($el);
            $newRoot = $doc->createElement(self::FAKE_ROOT_TAG);
            $doc->appendChild($newRoot);
            $newRoot->appendChild($oldRoot);
        }
        $this->processElement($doc->documentElement);

        if ($depth === 0 && ($this->format->cache->statistics ?? false)) {
            $this->appendCacheStats($doc);
        }
        $this->maintainXmlCache($doc);
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
        $child      = $el->firstChild;
        while ($child !== null) {
            if ($child instanceof DOMElement) {
                $chRemove = $this->processElement($child);
                if ($chRemove) {
                    $chToRemove[] = $child;
                }
            } elseif ($child instanceof DOMComment) {
                $chToRemove[] = $child;
            }
            $child = $child->nextSibling;
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
        $values = null;
        if ($val === 'NOW') {
            $values = date(DATE_ISO8601);
        } elseif ($val === 'URI' || $val === 'URL') {
            $values = $this->res->getUri();
        } elseif ($val === 'METAURL') {
            $values = $this->res->getUri() . '/metadata';
        } elseif ($val === 'OAIID') {
            $values = $this->res->getGraph()->get($this->format->uriProp);
        } elseif ($val === 'OAIURL') {
            $id     = rawurlencode((string) $this->res->getGraph()->get($this->format->uriProp));
            $prefix = rawurlencode($this->format->metadataPrefix);
            $values = $this->format->info->baseURL . '?verb=GetRecord&metadataPrefix=' . $prefix . '&identifier=' . $id;
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
                $values = null;
            } else {
                $values = $this->extractMetaValues($meta, $prop, $subprop, $extUriProp, $el->getAttribute('dateFormat'), $el->getAttribute('format'));
            }
        }
        if ($values !== null) {
            $values = $this->processValues($el, $values, $val === 'OAIID' ? '@' : '');
            $remove = $this->insertValues($el, $values);
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
                                          string $component, string $prop): void {
        $oldMeta = $this->res->getGraph();

        $format                = clone($this->format);
        $format->schemaDefault = null;
        $format->schemaProp    = 'https://enforced/schema';

        $count = $el->getAttribute('count');
        if (empty($count)) {
            $count = '1';
        }

        $resources = [];
        $cache     = [];
        foreach ($meta->all($prop) as $i) {
            if ($i instanceof Literal || $prop === $this->format->idProp) {
                // use a copy of the metadata with only a single value of the filtered property
                $resTmp      = new RepoResourceDb($meta->getUri(), $this->res->getRepo());
                $metaTmp     = $meta->copy();
                $metaTmp->delete($prop);
                $metaTmp->addLiteral($prop, (string) $i);
                $resTmp->setGraph($metaTmp);
                $resources[] = $resTmp;
                $cache[]     = false;
            } elseif (count($i->propertyUris()) === 0) {
                $resources[] = $this->getRdfResource($i);
                $cache[]     = true;
            } else {
                $resTmp      = new RepoResourceDb($i->getUri(), $this->res->getRepo());
                $resTmp->setGraph($i);
                $resources[] = $resTmp;
                $cache[]     = true;
                $this->maintainRdfCache($resTmp);
            }
            if (in_array($count, ['1', '?'])) {
                break;
            }
        }
        if (in_array($count, ['1', '+']) && count($resources) === 0) {
            $graph       = new Graph();
            $meta        = $graph->addLiteral('https://dummy/res', 'https://dummy/property', 'dummy value');
            $this->res->setGraph($graph->resource('https://dummy/res'));
            $resources[] = $this->res;
            $cache[]     = true;
        }

        try {
            foreach ($resources as $n => $res) {
                $meta = $res->getGraph();
                $meta->delete($format->schemaProp);
                foreach ($res->GetGraph()->allResources(RDF::RDF_TYPE) as $i) {
                    $meta->addLiteral($format->schemaProp, $component . '/' . $i);
                }
                $meta->addLiteral($format->schemaProp, $component);
                $res->setGraph($meta);
                $componentObj = new LiveCmdiMetadata($res, new stdClass(), $format);
                $componentXml = $componentObj->getXml($this->depth + 1, $cache[$n]);
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
     * Extracts metadata values from a resource
     * @return array<string, array<int, mixed>>
     */
    private function extractMetaValues(Resource $meta, string $prop,
                                       ?string $subprop, ?string $extUriProp,
                                       ?string $dateFormat, ?string $format): array {
        $values = [];
        foreach ($meta->all($prop) as $i) {
            if ($extUriProp !== null && $i instanceof Resource) {
                if (count($i->propertyUris()) === 0) {
                    $metaTmp = $this->getRdfResource($i)->getGraph();
                } else {
                    $metaTmp = $i;
                }
                foreach ($metaTmp->all($extUriProp) as $j) {
                    $this->collectMetaValue($values, $j, null, $dateFormat, $format);
                }
            } else {
                $this->collectMetaValue($values, $i, $subprop, $dateFormat, $format);
            }
        }
        return $values;
    }

    /**
     * 
     * @param DOMElement $el
     * @param string|array<string, array<mixed>> $values
     * @param string $formatPrefix
     * @return array<string, array<mixed>>
     */
    private function processValues(DOMElement $el, string | array $values,
                                   string $formatPrefix): array {
        if (!is_array($values)) {
            $values = ['' => [$values]];
        }

        $match   = $el->getAttribute('match');
        $replace = $el->getAttribute('replace');
        if (!empty($match)) {
            $oldValues = $values;
            $values    = [];
            foreach ($oldValues as $lang => $vals) {
                $vals = array_filter($vals, fn($x) => preg_match("`$match`", $x));
                if (count($vals) > 0) {
                    if (!empty($replace)) {
                        $vals = array_map(fn($x) => preg_replace("`$match`", "$replace", $x), $vals);
                    }
                    $values[$lang] = $vals;
                }
            }
        }

        $aggregate = $el->getAttribute('aggregate');
        if (!empty($aggregate) && count($values) > 0) {
            $compare = $aggregate == "min" ? fn($x, $y) => $x < $y ? $x : $y : fn($x, $y) => $x > $y ? $x : $y;
            $value   = reset($values);
            $value   = reset($value);
            foreach ($values as $vals) {
                foreach ($vals as $v) {
                    if ($aggregate == "min" && $v < $value || $aggregate == "max" && $v > $value) {
                        $value = $v;
                    }
                }
            }
            $values = ['' => [$value]];
        }

        $count = $el->getAttribute('count');
        if ($count === '?' && count($values) > 1) {
            $values = array_slice($values, 0, 1);
        }

        $valueMap    = $el->getAttribute('valueMap');
        $extValueMap = $el->getAttribute('valueMapProp');
        if ($valueMap) {
            $mapMode = substr($valueMap, 0, 1);
            if ($mapMode === self::VALUEMAP_ALL || $mapMode === self::VALUEMAP_STRICT || $mapMode === self::VALUEMAP_FALLBACK) {
                $valueMap = substr($valueMap, 1);
            } else {
                $mapMode = self::VALUEMAP_STRICT;
            }
            $map = $this->format->valueMaps->$valueMap;

            $mapped = [];
            foreach ($values as $lang => $vals) {
                foreach ($vals as $i) {
                    $tmp = $map->$i ?? ($mapMode === self::VALUEMAP_FALLBACK ? $i : null);
                    if ($tmp !== null) {
                        $mapped[$lang][] = $tmp;
                    }
                    if ($mapMode === self::VALUEMAP_ALL) {
                        $mapped[$lang][] = $i;
                    }
                }
            }
            $values = $mapped;
        } elseif ($extValueMap) {
            $keepSrc = $el->getAttribute('valueMapKeepSrc');
            $mapped  = [];
            foreach ($values as &$i) {
                foreach ($i as $j) {
                    $mapped = array_merge($mapped, self::$mapper->getMapping($j, $extValueMap));
                }
                if (!$keepSrc) {
                    $i = [];
                }
            }
            unset($i);
            $dateFormat = $el->getAttribute('dateFormat');
            $format     = $el->getAttribute('format');
            foreach ($mapped as $i) {
                $this->collectMetaValue($values, $i, null, $dateFormat, $format);
            }
        }

        if (count($values) === 0 && in_array($count, ['1', '+'])) {
            $values[''] = [''];
        }
        if ($count === '1') {
            if (isset($values[$this->format->defaultLang])) {
                $values = [$this->format->defaultLang => [reset($values[$this->format->defaultLang])]];
            } else if (isset($values[''])) {
                $values = ['' => [reset($values[''])]];
            } else {
                $tmp    = reset($values);
                $values = ['' => [reset($tmp)]];
            }
        }

        $format = $el->getAttribute('format');
        if (!empty($format) && !empty($formatPrefix) && count($values['']) > 0) {
            $values[''] = array_map(fn($x) => $x . $formatPrefix . "format=" . rawurlencode($format), $values['']);
        }

        return $values;
    }

    private function removeTemplateAttributes(DOMElement $ch): void {
        $ch->removeAttribute('val');
        $ch->removeAttribute('count');
        $ch->removeAttribute('lang');
        $ch->removeAttribute('getLabel');
        $ch->removeAttribute('asXML');
        $ch->removeAttribute('dateFormat');
        $ch->removeAttribute('format');
        $ch->removeAttribute('valueMap');
        $ch->removeAttribute('valueMapProp');
        $ch->removeAttribute('valueMapKeepSrc');
        $ch->removeAttribute('replaceXMLTag');
        $ch->removeAttribute('asAttribute');
        $ch->removeAttribute('match');
        $ch->removeAttribute('aggregate');
        $ch->removeAttribute('replace');
    }

    /**
     * Extracts metadata value from a given EasyRdf node
     * @param array<string> $values
     */
    private function collectMetaValue(array &$values,
                                      Literal | Resource $metaVal,
                                      ?string $subprop, ?string $dateFormat,
                                      ?string $format): void {
        $language = '';
        if ($metaVal instanceof Literal) {
            $language = $metaVal->getLang();
            $value    = $metaVal->getValue();
            if ($subprop !== null) {
                $tmp = yaml_parse($value);
                if (!isset($tmp[$subprop])) {
                    return;
                }
                $value = $tmp[$subprop];
            }
            if (!empty($dateFormat)) {
                try {
                    $date  = new \DateTime($value);
                    $value = $date->format($dateFormat);
                } catch (\Throwable $e) {
                    return;
                }
            }
        } elseif (!empty($format)) {
            if (count($metaVal->propertyUris()) === 0) {
                $metaVal = $this->getRdfResource($metaVal->getUri())->getGraph();
            }
            $ids   = array_map(fn($x) => $x->getUri(), $metaVal->allResources($this->format->idProp));
            $ids   = array_filter($ids, fn($x) => preg_match("`" . $this->format->resolverNmsp . "`", $x));
            $value = reset($ids);
            if ($value !== null) {
                $value .= "?format=" . rawurlencode($format);
            }
        } else {
            $value = $metaVal->getUri();
        }
        if (!isset($values[$language])) {
            $values[$language] = [];
        }
        $values[$language][] = $value;
    }

    /**
     * 
     * @param string $prop
     * @return string
     */
    private function replacePropNmsp(string $prop): string {
        $nmsp = substr($prop, 0, (int) strpos($prop, ':'));
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

    /**
     * 
     * @param DOMElement $el
     * @param array<string, array<mixed>> $values
     * @return bool
     */
    private function insertValues(DOMElement $el, array $values): bool {
        $asAttribute = $el->getAttribute('asAttribute');
        if (!empty($asAttribute)) {
            if (count($values) === 0) {
                return false;
            }
            $value = reset($values);
            $value = reset($value);
            $nmsp  = '';
            $p     = strpos($asAttribute, ':');
            if ($p > 0) {
                $prefix = substr($asAttribute, 0, $p);
                $nmsp   = $el->lookupNamespaceUri($prefix);
            }
            if (!empty($prefix)) {
                $el->setAttributeNS($nmsp, $asAttribute, $value);
            } else {
                $el->setAttribute($asAttribute, $value);
            }
            return false;
        }

        $lang       = $el->getAttribute('lang') === 'true';
        $asXml      = $el->getAttribute('asXML') === 'true';
        $replaceTag = $el->getAttribute('replaceXMLTag');
        $parent     = $el->parentNode;
        foreach ($values as $language => $tmp) {
            foreach ($tmp as $value) {
                /** @var DOMElement $ch */
                $ch = !$replaceTag ? $el->cloneNode(true) : null;
                if ($asXml) {
                    $df = $el->ownerDocument->createDocumentFragment();
                    $df->appendXML($value);
                    if ($replaceTag) {
                        $ch = $df;
                    }
                } else {
                    if (!empty($asAttribute)) {
                        $this->removeTemplateAttributes($ch);
                        $nmsp = '';
                        $p    = strpos($asAttribute, ':');
                        if ($p > 0) {
                            $prefix = substr($asAttribute, 0, $p);
                            $nmsp   = $ch->lookupNamespaceUri($prefix);
                        }
                        if (!empty($prefix)) {
                            $ch->setAttributeNS($nmsp, $asAttribute, $value);
                        } else {
                            $ch->setAttribute($asAttribute, $value);
                        }
                    } elseif ($replaceTag) {
                        $ch = $el->ownerDocument->createTextNode($value);
                    } else {
                        $this->removeTemplateAttributes($ch);
                        $ch->textContent = $value;
                    }
                }
                if ($lang && $language !== '' && $ch instanceof DOMElement) {
                    $ch->setAttribute('xml:lang', $language);
                }
                // append after the template node assuring content will be also processed
                if ($el->nextSibling !== null) {
                    $parent->insertBefore($ch, $el->nextSibling);
                } else {
                    $parent->appendChild($ch);
                }
            }
        }
        return true;
    }

    private function getRdfResource(string $uri): RepoResourceDb {
        if (isset(self::$rdfCache[$uri])) {
            self::$cacheHits['rdf']++;
            return self::$rdfCache[$uri];
        }
        $res = $this->res->getRepo()->getResourceById($uri);
        $this->maintainRdfCache($res);
        return $res;
    }

    private function maintainRdfCache(RepoResourceDb $res): void {
        if ($this->shouldBeCached($res->getGraph())) {
            self::$rdfCache[(string) $res->getUri()] = $res;
        }
    }

    private function getXmlCacheId(): string {
        return $this->template . '|' . $this->res->getUri();
    }

    private function maintainXmlCache(DOMDocument $doc): void {
        // it makes no sense to buffer at depth 0 - records are assumed to be distinct
        if ($this->depth === 0) {
            $perResource = $this->format->cache->perResource ?? false;
            // if the cache is per-resource, clean it up at the depth of 0
            if ($perResource && $this->depth === 0) {
                self::$xmlCache = [];
                self::$rdfCache = [];
            }
            return;
        }

        if ($this->shouldBeCached()) {
            self::$xmlCache[$this->getXmlCacheId()] = $doc;
        }
    }

    private function shouldBeCached(?Resource $meta = null): bool {
        if (!isset($this->format->cache)) {
            return false;
        }

        $meta    ??= $this->res->getGraph();
        $skip    = $this->format->cache->skipClasses ?? [];
        $include = $this->format->cache->includeClasses ?? [];

        $cache = count($include) > 0 ? false : true;
        for ($i = 0; $i < count($include) && !$cache; $i++) {
            $cache = $cache || $meta->isA($include[$i]);
        }
        for ($i = 0; $i < count($skip) && $cache; $i++) {
            $cache = $cache && !$meta->isA($skip[$i]);
        }
        return $cache;
    }

    private function appendCacheStats(DOMDocument $doc): void {
        $stats = $doc->createElement(self::STATS_TAG);
        $stats->appendChild($doc->createElement('MemoryUsageMb', (string) (round(memory_get_usage(true) / 1024 / 1024))));
        $stats->appendChild($doc->createElement('RdfCacheHits', (string) self::$cacheHits['rdf']));
        $stats->appendChild($doc->createElement('RdfCacheCount', (string) count(self::$rdfCache)));
        $stats->appendChild($doc->createElement('XmlCacheHits', (string) self::$cacheHits['xml']));
        $stats->appendChild($doc->createElement('XmlCacheCount', (string) count(self::$xmlCache)));
        $tmp   = [];
        foreach (self::$rdfCache as $i) {
            foreach ($i->getGraph()->allResources(RDF::RDF_TYPE) as $j) {
                $tmp[(string) $j] = ($tmp[(string) $j] ?? 0) + 1;
            }
        }
        foreach ($tmp as $class => $count) {
            $el = $stats->appendChild($doc->createElement('ClassCount'));
            $el->setAttribute('class', $class);
            $el->setAttribute('count', $count);
        }
        $doc->documentElement->appendChild($stats);
    }
}
