An OAI-PMH service for Fedora 4 repositories.

* Uses metadata from the Fedora repository triplestore
  (it means **you must have Fedora coupled with a triplestore**, e.g. using the [fcrepo-indexing-triplestore](https://github.com/fcrepo4-exts/fcrepo-camel-toolbox/tree/master/fcrepo-indexing-triplestore) Fedora plugin)
* Depends only on the Fedora REST API and the SPARQL endpoint, therefore is not affected by internal changes in Fedora.
* Can handle big repositories (doesn't buffer output data so memory consumption is very low).
* Is very flexible:
    * RDF metadata to OAI-PMH facets (id, date, set) mappings are provided in a configuration file.
    * It's shipped with a classes implementing few metadata sources:
        * Dublin Core and Dublin Core terms in repository resource's RDF metadata.
        * Metadata provided as other binary resources in the repository.
        * Metadata provided as CMDI XMLs stored in other repository resources (but their class is provided in the RDF metadata).
    * It's easy to extend
        * implement your own metadata sources
        * or implement your own search class.

# Installation

* clone the repo
* run `composer update`
    * of course make sure you have `composer` first
* rename `config.ini.inc` to `config.ini`
* update `config.ini` content
    * read the comments - information on describing metadata formats is provided there (as well as examples)
* deploy as a normal PHP website

# Unsupported features

* *resumptionTokens*  
  Implementing *resumptionTokens* in a right way is difficult and in my personal opinion it is better to avoid resumption queries at all (the main problem is Fedora does not provide transaction consistency and isolation - see [ACID](https://en.wikipedia.org/wiki/ACID) - and if I can not guarantee immutable repository state between following resumption queries I do not see a way to support such queries in a right way).  
  Also the service is written in a way it can handle responses of virtually any size (it doesn't buffer the response so memory usage shouldn't be a problem).
* *deleted records*  
  As in our software stack information about deleted resources is removed from the triplestore we did not bother about this feature.  
  Anyway if such data are available in the triplestore it will be easy to add this feature.
* *sets*  
  That's because we are not using sets in our repository.  
  Anyway it won't be difficult to add support for them. Contact us if you need this feature.

# Extending

There are two areas for extending the service:

* implementing new metadata sources
* implementing new search engines

## Architecture

The `acdhOeaw\oai\Oai` class works a a controller. It:

* checks OAI-PMH requests correctness, 
* handles OAI-PMH commands not releated to listing resources (`identify`, 
  `ListMetadataFormats` and `ListSets`)
* delegates OAI-PMH commands related to listing resources to a chosen
  class implementing the `acdhOeaw\oai\search\SearchInterface`
* generates OAI-PMH compliant output from results of above mentioned actions
* catches errors and generates OAI-PMH compliant error responses

Until you want to implement the resumption tokens and/or `ListSets` command there
shouldn't be need to alter this class.

```
+-----+    +-----------+
|     |    | Search    |    +-----------+
| Oai |--->| Interface |--->| Metadata  |
|     |    +-----------+    | Interface |
+-----+                     +-----------+
```

The `Oai` class performs resource search using the **`acdhOeaw\oai\search\SearchInterface`**
interface. This interface **provides methods for both search and accessing search
results** in two formats:

* `acdhOeaw\oai\search\HeaderData` - a minimalistic set of data (id, date, sets)
  required to generate OAI-PMH &lt;header&gt; section
* `acdhOeaw\oai\metadata\MetadataInterface` - a format allowing access to full
  metadata in XML format (as PHP DOM objects)

The `acdhOeaw\oai\search\SearchInterface` responsibility is to perform a search
and return them in one of above-mentioned formats.

* The `HeaderData` data can (and for performance resons should) be 
  typically obtained purely from the SPARQL search query.
  Therefore `HeaderData` serialization is typically provided by the class
  implementing the `SearchInterface`.
* **Obtaining the full metadata** may involve different actions based on the
  OAI-PMH metadata format and its representation in the repository.
  Therefore it **is delegated to specialized classes implementing the
  `acdhOeaw\oai\metadata\MetadataInterface`** interface.
