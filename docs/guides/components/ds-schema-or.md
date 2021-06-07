<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Data source schema](data-source-schema.md) / $or

# $or

$or performs a logical **OR** operation on an array of two or more \<expressions> and selects the documents that satisfy at least one of the \<expressions>.

## Syntax

    { $or: [ { <expression1> }, { <expression2> } , ... , { <expressionN> } ] }

## Example

The following example selects all entries where:
- the color field value is not equal to red or
- the price field is greater than 12.

```json
{
    "dataSource":"Blogs",
    "where":{
        "$or":{
            "color":{
                "$ne":"red"
            },
            "price":{
                "$gt":12
            }
        }
    }
}
```
