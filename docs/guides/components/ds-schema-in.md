<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Data source schema](data-source-schema.md) / $in

# $in

Selects those entries where the value of a field equals any value in the specified array.

## Syntax

    { field: { $in: [<value1>, <value2>, ... <valueN> ] } }

Although you can express this query using the \$or operator, choose the $in operator rather than the \$or operator when performing equality checks on the same field.

## Example

The following example select all entries where the ***qty*** field value is either 5 or 15:

```json
{
    "dataSource":"Blogs",
    "where":{
        "qty":{
            "$in": [ 5, 15 ]
        }
    }
}
```
