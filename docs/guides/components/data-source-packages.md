# Data Source Packages

A **data source package** can define the logic to **read**, **store**, and/or **query** data. One data source might perform al those three roles or it might rely on other data sources for them. Because of that, the querying of data might be resolved with one, two or three *data source packages*. The objective of such flexibility is to enable the independent reusability of readers, data and queries across website projects.

## Folder structure

All folders below are optional since a data source might focus on implementing a reading logic, storing data or defining queries.

```txt
MyDataSource
├── data
│   └── sheet1.xlsx
├── var
│   └── sheet1.json
├── config
│   ├── settings.json
│   └── schema.json
├── src
│   └── MyDataSource.php
├── queries
│    ├── query1.json
│    └── query2.json
├── composer.json
├── README
└── LICENSE
```

### `settings.json`

The settings file can refer to other data sources to fetch the data or query it. This flexibility is convenient to reuse functionality and data. For example, it's natural to have one data source with the logic to read from a particular type of source, such as *airtable.com* or an Excel file. For example, two different websites might store data as Excel files, and both will use the same ExcelFile data source to read their data.

In some advanced cases, the **queries** themselves might be reused across independent data sources, since the queries are abstract definitions of what is to be selected from the available data. That can be achieved by having a data source with query definitions that are applied to data sources with independent data.

The choices of which data source to use for each roles is set in the `settings.json` file.

```json
{
    "readerClass": "Namespace\\ClassName",
    "dataClass": "Namespace\\ClassName",
    "queryClass": "Namespace\\ClassName"
}
```

All classes default to the current data source class, which is to be defined in `src/PackageName.php`.

## Referencing a Data Source

A data source is referenced by an UTF-8 URI, which is also know as an [IRI](https://en.wikipedia.org/wiki/Internationalized_Resource_Identifier). A [URL](https://en.wikipedia.org/wiki/URL) is a particular type of URI (and IRI). For example,

```json
// in a GLOT webpage
{
    "widget": "Xyz\\MyWidget",
    "params": {
        "$names": "json://members/active?select=name&$where=age>35"
    }
}
```
```json
// in data/members.json
{
    "active": [
        {"name": "Diego", "age": 40}, 
        {"name": "Tianyu", "age": 30}
    ]
}
```

A **Data Source URI** can be given as a string or as an object (with the same keys as the output of the `parse_url()` function).

**URI** = scheme:[//authority]path[?query][#fragment]

The **Data Source URI** expands the classical components of an URI in order to refer to custom data types:

- `scheme`: A standard protocol (http, https, data, file, etc.), a **class name** of a *data source package*, or `json` to read the data from a JSON file.
- `authority`: `[user@pass:]host[:port]`;
    - `host`: name of the **data source package**. The path to the host named can be aliased by defining a map under `config/dataSources.json`. If an alias is not present, the default path is `{project-root}/data/{data-source-name}`. If the data source has periods, they are interpreted as folder names within the root data folder (e.g. `conference.members` is  `data/conference/members`);
    - `port`: not used;
    - `user`: not used;
    - `pass`: not used;
- `path`: selected path within the data source. E.g. /sheet1;
- `query`: query arguments. If the first key is set to the empty string, it is considered to be a **query name**. All other arguments must be `key=value` pairs. Custome query arguments must be prefixed with a dollar sign. So if the query has a variable named x, the value for it can be given as `$x=N`. The keywords `select` and `where` can be used to define the query;
- `fragment`: not used.

> The meaning of the `path` part depends on the data source type. A JSON data source might take the path as a folder path and/or keys within the data.

## Example Data Source URIs

1. `json://members/sheet1/key.sub-key.sub-sub-key?queryName`<br>The data is read from a JSON file named *members.json* by calling the query named *queryName*.<br><br>

1. `Namespace\Excel://conference.members/sheet1?queryName&$minAge=25&$maxAge=45`<br>The piece of data name *sheet1* in the data source *data/conference/members* will be queried. The query arguments *minAge* and *maxAge* are set to 25 and 45, respectively. The reading logic is defined in the data source  *Namespace\Excel*.<br><br>

1. `conference.members/sheet1?queryName`<br>The piece of data name *sheet1* in the data source *data/conference/members* will be queried with the reader class defined in its `config/settings.json`.

> Note: if the scheme is not given, then the `readerClass` must be defined in the `config/settings.json` file of the referenced data source. The recommended option is to define the reader class in the settings of the data source with the data.