# OAI-PMH service for ACDH repository solution

[![Latest Stable Version](https://poser.pugx.org/acdh-oeaw/arche-oaipmh/v/stable)](https://packagist.org/packages/acdh-oeaw/arche-oaipmh)
[![License](https://poser.pugx.org/acdh-oeaw/arche-oaipmh/license)](https://packagist.org/packages/acdh-oeaw/arche-oaipmh)

* Is flexible:
    * RDF metadata to OAI-PMH facets (id, date, set) mappings are provided in a configuration file.
    * It's shipped with classes implementing OAI-PMH metadata generation by:
        * Serializing whole repository resource RDF metadata to XML.
        * Using Dublin Core and Dublin Core terms contained in the repository resource RDF metadata.
        * Serving another linked repository resource as the OAI-PMH metadata.
            * With additional filtering based on the binary resource RDF metadata.
        * Filling an XML template with repository resource RDF metadata.
    * It's easy to extend
        * implement your own metadata sources
        * implement your own search class
        * implement your own sets

# Installation

* Run in your webroot:
  ```bash
  composer require acdh-oeaw/arche-oaipmh
  cp vendor/acdh-oeaw/arche-oaipmh/.htaccess vendor/acdh-oeaw/arche-oaipmh/index.php .
  cp vendor/acdh-oeaw/arche-oaipmh/config-sample.yaml config.yaml
  ```
* Adjust `config.yaml` according to comments in the file.

# Unsupported features

* *resumptionTokens*  
  Implementing *resumptionTokens* in a right way is difficult and in my personal opinion it is better to avoid resumption queries at all.   
  The main problem is Fedora does not provide transaction consistency and isolation - see [ACID](https://en.wikipedia.org/wiki/ACID) - and if I can not guarantee immutable repository state between following resumption queries I do not see a way to support such queries in a right way.  
  Also the service is written in a way it can handle responses of virtually any size (it doesn't buffer the response so memory usage shouldn't be a problem) so it should not be needed to split a response into parts.

# Required repository structure

Much attention was paid to make the service flexible.
To achieve that different implementations were provided for each of main components (set handling component, metadata source component).
These implementations provide various features and put various requirements on your repository structure.
Please read the documentation provided in the `config.ini.inc` and, if needed, the documentation of particular classes to get more information.

# Architecture

Source code documentation can be found at https://acdh-oeaw.github.io/arche-docs/arche-oaipmh/

```
    +------------------+
    | DeletedInterface |
    +------------------+
     ^        ^
     |        |
+-----+    +-----------+
|     |    | Search    |    +-----------+
| Oai |--->| Interface |--->| Metadata  |
|     |    +-----------+    | Interface |
+-----+       |             +-----------+
     |        |
     v        v
    +--------------+  
    | SetInterface |
    +--------------+
```

## Oai

The main class is `acdhOeaw\oai\Oai` which works as a controller. It:

* checks OAI-PMH requests correctness, 
* handles OAI-PMH `identify` and `ListMetadataFormats` commands
    * the `deletedRecord` field value of the `identify` request response is
      fetched using the `DeletedInterface`
* delegates OAI-PMH `GetRecord`, `ListIdentifiers` and `ListRecords` commands 
  to a chosen class implementing the `acdhOeaw\oai\search\SearchInterface`
* delegates OAI-PMH `ListSets` command to a chosen class extending the
  `acdhOeaw\oai\set\SetInterface` class.
* generates OAI-PMH compliant output from results of above mentioned actions
* catches errors and generates OAI-PMH compliant error responses

**Until you want to implement the resumption tokens there should be no need to 
alter this class.**

## SetInterface

The `acdhOeaw\oai\set\SetInterface` provides an API:

* for the `Oai` class to handle the `ListSets` OAI-PMH requests
* for the `SearchInterface` implementations to include set information in searches

Currenlty there are three implementations of the `SetInterface`:

* `acdhOeaw\oai\set\NoSet` simply throwing the `noSetHierarchy` OAI-PMH error
* `acdhOeaw\oai\set\Simple` where set membership is fetched from a given RDF
  metadata property. This property value is taken as both &lt;setSpec&gt; and
  &lt;setName&gt; values and no &lt;setDescription&gt; is provided.
* `acdhOeaw\oai\set\Complex` where a given RDF metadata property points to
  another repository resource describing the set.

If exsisting implementations don't fulfil your needs, you need to write your own
class extending the `acdhOeaw\oai\set\SetInterface` one and set the `oaiSetClass`
in the `config.ini` file to your class name.

## DeletedInterface

The `acdhOeaw\oai\deleted\DeletedInterface` provides an API:

* for the `Oai` class to get the `deletedRecord` field value to be reported
  in the OAI-PMH `identify` response
* for the `SearchInterface` implementations to include information on resource
  deletion

Currently there are two implementations of the `DeletedInterface`:

* `acdhOeaw\oai\deleted\No` reporting no support for deleted resources
* `acdhOeaw\oai\deleted\RdfProeprty` where a resource having a given metadata 
  property (no matter its value) is assumed to be deleted

If exsisting implementations don't fulfil your needs, you need to write your own
class extending the `acdhOeaw\oai\deleted\DeletedInterface` one and set the 
`oaiDeletedClass` in the `config.ini` file to your class name.

## SearchInterface

The `acdhOeaw\oai\search\SearchInterface` provides an API for the `Oai` class to:

* Perform search for resources.
* Get basic resource metadata required to serve the `ListIdentifiers` OAI-PMH request.  
  Data for each resource are returned as `acdhOeaw\oai\data\Headerdata` objects.
* Get full resource metadata required to serve `ListResources` and `GetRecord` OAI-PMH requests.  
  Data for each resource are returned as `acdhOeaw\oai\metadata\MetadataInterface` objects.

The `SearchInterface` implementations depend on three other interfaces:

* `SetInterface` for getting SPARQL search query extensions for including
  information and/or filters on sets.
* `DeletedInterface` for including information on resources deletion.
* `MetadataInterface` for getting the full resource metadata required to serve 
  `ListResources` and `GetRecord` OAI-PMH requests.  

The current implementation (`acdhOeaw\oai\search\BasicSearch`) is quite flexible:

* takes OAI-PMH `identifier` and `datestamp` mappings from the configuration file
  (`oaiIdProp` and `oaiDateProp`)
* honors set, deleted and metadata format SPARQL search query extensions 
  provided by the `SetInterface`, `DeletedInterface` and `MetadataInterface`

Until you don't need to alter the way `identifier` and `datestamp` are fetched
there should be no need to develop an alternative implementation (possibly with
additional filters).

## MetadataInterface

The `acdhOeaw\oai\metadata\MetadataInterface` provides a common API for fetching
full OAI-PMH metadata from different sources, e.g. the repository resource
metadata (by applying different mappings) or other repository resource.

To make it as flexible as possible:

* It allows to extend SPARQL search queries performed by the `SearchInterface`
  implementations.
* The `MetadataInterface` object constructor is provided with repository
  resource object, the metadata format description and full set of SPARQL search
  query data describing a given resource.

Existing implementations are:

* `acdhOeaw\oai\metadata\RdfXml` serializes all resource's RDF metadata into XML.
* `acdhOeaw\oai\metadata\DcMetadata` like the `RdfXml` but returns only Dublin Core
  and Dublin Core Terms metadata properties.
* `acdhOeaw\oai\metadata\ResMetadata` uses another (linked trough metadata) 
  resource's binary payload as the OAI-PMH metadata.
* `acdhOeaw\oai\metadata\CmdiMetadata` a specialization of the `ResMetadata`
  additionaly checking for the binary resource content schema.
* `acdhOeaw\oai\metadata\AcdhDcMetadata` creats Dublin Core metadata by mapping
  resource's metadata properties to their DC equivalents. The mappings are taken
  from the ontology being part of the repository.
* `acdhOeaw\oai\metadata\LiveCmdiMetadata` creats CMDI metadata by filling up
  XML templates with data fetched from resource's metadata.

It's likely that you'll need to generate OAI-PMH metadata in (yet) another way.
In such a case you must develop your own class implementing the `MetadataInterface`.
Taking look at already existing implementations should be a good starting point.
