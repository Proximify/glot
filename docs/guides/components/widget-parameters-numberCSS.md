<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Widget parameters](widget-parameters.md) / Number CSS

# Number CSS parameter

NOTE: The color-css parameter is only used for GUI.

| Value type | Explanation                                                                                                                                   | Multilingual |
| ---------- | --------------------------------------------------------------------------------------------------------------------------------------------- | ------------ |
| String     | This parameter is only used for GUI. Changing the parameter in GUI, the CSS property that is defined as the parameter `name` will be changed. | No           |

## Properties

-   `name` **String** - The name property should be a valid css property which is related to number. Eg, `padding` `font-size`.

## Example and explanation

`params.json`

```json
[
    {
        "name": "margin",
        "type": "number-css",
        "label": "Margin"
    }
]
```
