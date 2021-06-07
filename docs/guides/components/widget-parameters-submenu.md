<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Widget parameters](widget-parameters.md) / Submenu

# Submenu parameter

NOTE: The submenu parameter is only used for GUI. It groups several parameters in the panel.

## Properties

-   `inline` **Bool** - **true**: Group the parameters in the current panel. **false**: Group the parameters in another panel.
-   `collapse` **Bool** - Whether the group of parameters is collapsible.
-   `openByDefault` **Bool** - If the `collapse` is set to true, whether the group of parameters is opened by default.
-   `context` **Bool** - Whether the group of parameters is displayed as an independent module.

## Editor example in GUI

<p align="center">
  <img src="../../assets/components/widget-parameters-submenu.jpg" width="46%" alt="Sub-menu parameter", style="border-radius:10px; border: 1px solid #ddd; margin-right:4%;">
<img src="../../assets/components/widget-parameters-submenu2.jpg" width="46%" alt="Sub-menu parameter", style="border-radius:10px; border: 1px solid #ddd;">
<span style="display:block;">Sub-menu parameter with the <b>inline</b> property set to false</span>
</p>

<p align="center">
  <img src="../../assets/components/widget-parameters-submenu3.jpg" width="46%" alt="Sub-menu parameter", style="border-radius:10px; border: 1px solid #ddd;">
<span style="display:block;">Sub-menu parameter with the <b>inline</b> property set to true</span>
</p>

## Example and explanation

`params.json`

```json
[
    {
        "name": "advancedOptions",
        "type": "submenu",
        "label": "Advanced options",
        "data": [
            {
                "type": "text",
                "name": "text",
                "label": "Content"
            },
            {
                "type": "color",
                "name": "color",
                "label": "Color"
            },
            {
                "type": "media",
                "name": "media",
                "label": "Media"
            }
        ]
    }
]
```
