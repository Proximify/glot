<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Data source schema](data-source-schema.md) / $nin

# $nin

Selects those entries where the value of a field equals any value is not in the specified array or the field does not exist.

## Syntax

    { field: { $nin: [<value1>, <value2>, ... <valueN> ] } }

## Example

The following example select all entries where the ***qty*** field value does not equal 5 nor 15. The selected entries will include those entries that do not contain the qty field.

```json
{
    "dataSource":"Blogs",
    "where":{
        "qty":{
            "$nin": [ 5, 15 ]
        }
    }
}
```
