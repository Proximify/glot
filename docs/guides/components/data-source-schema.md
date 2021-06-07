<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / Data source schema

# Data source schema

The referenced parameter value is a JSON object that collects information about a data source entry.

## Available properties

-   `dataSource` **String** - **Required**. The data source name.
-   `from` **String** - Depends on the type of the data source, set the table name or the file name to query.
-  `column` **String** | **Array** - Field(s) to query. If it is not set, the full entry will be returned.
-   `where` **Object** - A JSON object of the query conditions. Head over to [query conditions](#query-conditions) to learn the detail.
-   `query` **String** - The query name. A query is a JSON-format file which contains the pre-set query properties (`from` and `where`).

### Query conditions

#### ***Specify simple query***

The JSON object declared in **where** property without any operator specifies equality conditions { \<field>: \<value> }. For example, the following query selects all entries where the field **color** equals **red** and the field **number** equals **1**.

```json
{
    "dataSource": "Blogs",
    "where":{
        "color": "red", //field:value,
        "number": 1
    }
}
```

#### ***Specify query with operators***

To specify the complicated conditions, use [Comparison Query Operators](#comparison-query-operators) and/or [Logical Query Operators](#logical-query-operators). The available forms of \<condition-expression> are as following:

```text
{
    "dataSource": "Blogs",
    "where":{
        <field1>: {
            <comparison-operator1>: <value1>,
            ...
        },
        ...
    }
}
```

```text
{
    "dataSource": "Blogs",
    "where":{
        <logical-operator>:[
            {
                <field1>: {
                    <comparison-operator1>: <value1>,
                    ...
                },
                ...
            },
            ...
        ],
        ...
    }
}
```

### Comparison Query Operators

For details on specific operator, including syntax and examples, click on the specific operator to go to its reference page.

| Name | Description|  
| - | - |
| [$eq](ds-schema-eq.md)     | Matches values that are qual to a specific value.
| [$gt](ds-schema-gt.md)     | Matches values that are greater than a specified value.
| [$gte](ds-schema-gte.md)     | Matches values that are greater than or equal to a specified value.
| [$in](ds-schema-in.md)     | Matches any of the values specified in an array. 
| [$lt](ds-schema-lt.md)     | Matches values that are less than a specified value.
| [$lte](ds-schema-lte.md)     | Matches values that are less than or equal to a specified value.
| [$ne](ds-schema-ne.md)     | Matches all values that are not equal to a specified value.
| [$nin](ds-schema-nin.md)     | Matches none of the values specified in an array.

### Logical Query Operators

| Name | Description|  
| - | - |
| [$and](ds-schema-and.md)     | Joins query clauses with a logical AND returns all entries that match the conditions of both clauses.
| [$not](ds-schema-not.md)     | Inverts the effect of a query expression and returns entries that do not match the query expression.
| [$nor](ds-schema-nor.md)     | Joins query clauses with a logical NOR returns all entries that fail to match both clauses.
| [$or](ds-schema-or.md)     | Joins query clauses with a logical OR returns all entries that match the conditions of either clause.
