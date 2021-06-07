<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Widget parameters](widget-parameters.md) / Color CSS

# Color CSS parameter

NOTE: The color-css parameter is only used for GUI.

| Value type | Explanation                                                                                                                                                         | Multilingual |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------ |
| String     | This parameter is only used for GUI. Changing the parameter from the color picker in GUI, the CSS property that is defined as the parameter `name` will be changed. | No           |

## Properties

-   `name` **String** - The name property should be a valid css property which is related to color. Eg, `color` `background-color`.

## Example and explanation

`params.json`

```json
[
    {
        "name": "background-color",
        "type": "color-css",
        "label": "Background color"
    }
]
```
