<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Data source schema](data-source-schema.md) / $ne

# $ne

Specifies equality condition. The $ne operator matches documents where the value of a field is not equal to the specified value. This includes documents that do not contain the field.

## Syntax

    { <field>: { $ne: <value> } }

## Example

The following example select all entries where the value of the **color** field does not equal red, including those entries that do not contain the **color** field.:

```json
{
    "dataSource":"Blogs",
    "where":{
        "color":{
            "$ne":"red"
        }
    }
}
```
