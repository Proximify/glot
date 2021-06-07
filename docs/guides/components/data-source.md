<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / Data Source

# Data source

Data sources are intended to help users connect data to where it needs to be. Instead of put inline parameter value, users can use [Data source object](data-source-schema.md) as the parameter value. A data source can be a database, a JSON file and so on. Head over to [Data source provider](ds-provider.md) to learn detail.

## Examples & Explanation

The followding example is a data source created by [EXCELFile](ds-provider-excelFile.md) provider:

### File structure

```txt
MyDataSource
├── data
│   ├── MyData.xlsx
│   └── Sheet.json
├── configs
│   ├── settings.json
│   └── schema.json
└── queries
    ├── query1.json
    └── query2.json
```

#### `data/MyData.xlsx`

The excel file is the original data file, when user create the data source, the path to the file is one of the required arguments.

#### `data/Sheet.json`

The JSON-format file parsed from the EXCEL file. Based on the sheets in the EXCEL file. JSON files with the sheet name are generated. They are the real files to be fetched.

#### `configs/settings.json`

```json
{
    "name":"MyDataSource",
    "class":"Proximify\\Glot\\DataSource\\EXCELFile"
}
```

Basic information of the data source. Class name is the PHP class name of the Data source provider. Different data source provider has different way to fetch data or generate schema.

#### `configs/schema.json`

```json
{
    "sheet": {
        "type":"table",
        "columns":["url","img","img_fr","text","text_fr","name","author"]
    }
}
```

The schema file contains the information about the data, like column names. Those information are used in GUI.

#### `query/query1.json`

```json
{
    "dataSource":"MyDataSource",
    "column":"text",
    "from":"Sheet",
    "where":{
        "author": "Diego"
    }
}
```

Pre-set data source object. Users can put query name in the data source object, instead of providing full query information.
