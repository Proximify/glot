<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Data source schema](data-source-schema.md) / $eq

# $eq

Specifies equality condition. The $eq operator matches documents where the value of a field equals the specified value.

## Syntax

    { <field>: { $eq: <value> } }

Specifying the $eq operator is equivalent to using the form { field: \<value> }

## Example

The following example select all entries where the value of the color field equals red:

```json
{
    "dataSource":"Blogs",
    "where":{
        "color":{
            "$eq":"red"
        }
    }
}
```

```json
{
    "dataSource":"Blogs",
    "where":{
        "color":"red"
    }
}
```
