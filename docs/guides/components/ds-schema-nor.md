<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Data source schema](data-source-schema.md) / $nor

# $nor

$nor performs a logical **NOR** operation on an array of one or more query expression and selects the documents that fail all the query expressions in the array.

## Syntax

    { $nor: [ { <expression1> }, { <expression2> }, ...  { <expressionN> } ] }

## Example

The following example select all entries where:
- contain the price field whose value is not equal to 12 and contain the color field whose value is not equal to red or
- contain the price field whose value is not equal to 12 but do not contain the color field or
- Do not contain the price field but contain the color field whose value is not equal to red or
- Do not contain the price field and do not contain the color field


```json
{
    "dataSource":"Blogs",
    "where":{
        "$and":{
            "color":"red",
            "price":12
        }
    }
}
```
