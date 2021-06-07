<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Widget parameters](widget-parameters.md) / Switch CSS

# Switch CSS parameter

NOTE: The switch-css parameter is only used for GUI.

| Value type | Explanation                                                                                                                                            | Multilingual |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------ |
| Bool       | This parameter is only used for GUI. Switching **true** and **false** of the parameter, the pairs of CSS property and value will be applied and reset. | No           |

## Properties

-   `properties` **Object** - Pairs of CSS property and value.

## Example and explanation

`params.json`

```json
[
    {
        "name": "theme1",
        "type": "switch-css",
        "label": "Theme 1",
        "description": "Round theme",
        "properties": {
            "border": "1px solid #ccc",
            "border-radius": "8px"
        }
    }
]
```
