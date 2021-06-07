<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Widget parameters](widget-parameters.md) / Dropdown CSS

# Dropdown CSS parameter

NOTE: The dropdown-css parameter is only used for GUI.

| Value type | Explanation                                                                                                                                 | Multilingual |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------- | ------------ |
| String     | This parameter is only used for GUI. Choosing the value from the options, CSS rules that are declared in different options will be applied. | No           |

## Properties

All properties are used for GUI only.

-   `options` **Array** - Dropdown options, each option is an **Object** with label, value and properties. Properties are pair of CSS property and value.

## Example and explanation

`params.json`

```json
[
    {
        "name": "fontSize",
        "type": "dropdown-css",
        "label": "Font size",
        "description": "Font size",
        "options": [
            {
                "value": "12px",
                "label": "Thin",
                "properties": {
                    "font-size": "12px",
                    "font-weight": 300
                }
            },
            {
                "value": "13px",
                "label": "Normal",
                "properties": {
                    "font-size": "13px",
                    "font-weight": 400
                }
            },
            {
                "value": "15px",
                "label": "Bold",
                "properties": {
                    "font-size": "16px",
                    "font-weight": "bold"
                }
            }
        ]
    }
]
```
