<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Data source schema](data-source-schema.md) / $lt

# $lt

Selects those entries where the value of the field is less than (i.e. <) the specified value.

## Syntax

    {field: {$lt: value} }

## Example

The following example select all entries where the value of the **num** field is less than 20:

```json
{
    "dataSource":"Blogs",
    "where":{
        "num":{
            "$lt":20
        }
    }
}
```
