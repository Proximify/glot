<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Data source schema](data-source-schema.md) / $gte

# $gte

Selects those entries where the value of the field is greater than or equal to (i.e. >=) the specified value.

## Syntax

    {field: {$gte: value} }

## Example

The following example select all entries where the value of the **num** field is greater than or equal to 20:

```json
{
    "dataSource":"Blogs",
    "where":{
        "num":{
            "$gte":20
        }
    }
}
```
