<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Data source schema](data-source-schema.md) / $and

# $and

$and performs a logical **AND** operation on an array of one or more expressions (e.g. \<expression1>, \<expression2>, etc.) and selects the entries that satisfy all the expressions in the array.

## Syntax

    { $and: [ { <expression1> }, { <expression2> } , ... , { <expressionN> } ] }

## Example

The following example selects all entries where:
- the color field value is not equal to red and
- the price field is greater than 12.


```json
{
    "dataSource":"Blogs",
    "where":{
        "$and":{
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
