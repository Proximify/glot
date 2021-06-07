<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / Widget parameters

# Widget parameters

The widget parameter is a JSON object with name/value pairs such that:

-   the name is the name property of the parameter and the value is:
    -   one of the available data types based on the type of the parameter. [ String | Object | Array | Number ]; or
    -   the [Localized value](../language.md#localizable-values);
-   the name is start with **\$** and follow with the name property of the parameter and the value is a JSON object that collects information about a data source entry. Head over to [Data source schema](data-source-schema.md) to learn the detail of how to use data source as parameter value.

The inline parameter value has presence over its referenced value from the data source.

The widget parameters have different types. Some types are localized so that the **params** argument received by the **render()** method might not be the same that the value of the params property of the widget.

## Parameter types

The parameter **type** is used for data entry and validation through [JSON schemas](https://json-schema.org/). JSON schemas describe the shape of the JSON file, as well as value sets, default values, and descriptions. GUI also provides different parameter editors for different types to help users edit the parameter value.

-   [text](widget-parameters-text.md)
-   [simpleText](widget-parameters-simpleText.md)
-   [href](widget-parameters-href.md)
-   [switch](widget-parameters-switch.md)
-   [switch-css](widget-parameters-switchCSS.md)
-   [media](widget-parameters-media.md)
-   [file](widget-parameters-file.md)
-   [dropdown](widget-parameters-dropdown.md)
-   [dropdown-css](widget-parameters-dropdownCSS.md)
-   [groupDropdown](widget-parameters-groupDropdown.md)
-   [color](widget-parameters-color.md)
-   [color-css](widget-parameters-colorCSS.md)
-   [simpleNumber](widget-parameters-simpleNumber.md)
-   [number](widget-parameters-number.md)
-   [number-css](widget-parameters-numberCSS.md)
-   [slide](widget-parameters-slide.md)
-   [submenu](widget-parameters-submenu.md)
-   [font](widget-parameters-font.md)
-   [system](widget-parameters-system.md)

### Mandatory properties

All parameters must have these mandatory properties.

-   `type` **String** - The type of the widget parameter.
-   `name` **String** - The name of the widget parameter. The name should be unique within all parameters in the widget. No underscore prefixes allows. The underscore prefixes are reserved for the system.

#### Mandatory properties in GUI

-   `label` **String** - The label of the widget parameter. The label will be displayed in the panel of the GUI.

#### Optional properties in GUI

-   `description` **String** - The description of the widget parameter. The description will be displayed as a tooltip in the panel of the GUI.
-   `conditions` **Object** - The description of the widget parameter. The description will be displayed as a tooltip in the panel of the GUI.

### Advanced example

```json
[
    {
        "name": "color",
        "type": "color",
        "label": {
            "en": "Text color"
        }
    },
    {
        "name": "img",
        "type": "media",
        "label": {
            "en": "Image"
        },
        "targetType": "img"
    },
    {
        "name": "advancedOptions",
        "type": "submenu",
        "label": "Advanced options",
        "data": [
            {
                "name": "alt",
                "type": "text",
                "label": "Alt",
                "editor": false,
                "live": false,
                "linkOption": false,
                "notranslate": false,
                "conditions": {
                    "img": {
                        "value": true,
                        "operator": "0"
                    }
                }
            }
        ],
        "collapse": true,
        "openByDefault": true,
        "inline": true
    }
]
```

### Parameters in widget data

```json
{
    "widget": "MyWidget",
    "id": "MyWidget1",
    "params": {
        "color": "red",
        "alt": {
            "$": 201, // Dictionary keys for all external languages
            "$ch": "alt-key", // Optional per-language key?
            "en": "Hello World!",
            "es-ar": "¡Hola Mundo!",
            "$en": {
                "isSource": true,
                "status": "reviewing"
            }
        },
        "img": {
            "$": 202
        },
        "$img": {
            "dataSource":"Blogs",
            "query":"getBlogTitles"
        }
    }
}
```
