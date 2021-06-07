<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Data source schema](data-source-schema.md) / $lte

# $lte

Selects those entries where the value of the field is less than or equal to (i.e. <=) the specified value.

## Syntax

    {field: {$lte: value} }

## Example

The following example select all entries where the value of the **num** field is less than or equal to 20:

```json
{
    "dataSource":"Blogs",
    "where":{
        "num":{
            "$lte":20
        }
    }
}
```
