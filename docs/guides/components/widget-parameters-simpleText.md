<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Widget parameters](widget-parameters.md) / Simple text

# Simple text parameter

| Value type | Explanation                                                                                                | Multilingual |
| ---------- | ---------------------------------------------------------------------------------------------------------- | ------------ |
| String     | When rendering the markup, the **render()** method receives the same simple string as the parameter value. | No           |

## Editor example in GUI

<p align="center">
  <img src="../../assets/components/widget-parameters-SimpleText.jpg" width="400px" alt="Simple text widget parameter", style="border-radius:10px; border: 1px solid #ddd;">
<span style="display:block;">Simple text parameter editor</span>
</p>

GUI offers a text editor that can let users edit the parameter.

## Example and explanation

`params.json`

```json
[
    {
        "name": "brand",
        "type": "simpeText",
        "label": "Brand name",
        "description": "The brand name of the product."
    }
]
```

`Widget data` Head over to [JSON schema](#json-schema) to check how the widget data is validated.

```json
{
    "widget": "MyWidget",
    "id": "MyWidget1",
    "params": {
        "brand": "GLOT"
    }
}
```

Example of the usage of the parameter in PHP class:

```php
namespace X\Y;

/**
 * Example entry-point class for the component.
 */
class MyWidget extends Widget
{
    public function render($data, $params)
    {
        // Add a call to the JS 'render' method into the "document ready"
        // event of the webpage. It does nothing if there is no JS code.
        $this->initJavaScriptWidget($params, 'render');

        $brand = $params['brand'];

        return ['tag' => 'h1', 'data' => "The brand name is $brand"];
    }
}

```

## JSON schema

```json
{
    "type": "string"
}
```
