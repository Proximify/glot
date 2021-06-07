<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Data source schema](data-source-schema.md) / $not

# $not

$not performs a logical **NOT** operation on the specified \<operator-expression> and selects the documents that do not match the \<operator-expression>. This includes documents that do not contain the field.

## Syntax

    { field: { $not: { <operator-expression> } } }

## Example

The following example select all entries where:
- the price field value is less than or equal to 12 or
- the price field does not exist.

```json
{
    "dataSource":"Blogs",
    "where":{
        "price":{
            "$not":{ "$gt":12 }
        }
    }
}
```
