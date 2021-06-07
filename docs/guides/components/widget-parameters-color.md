<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Widget parameters](widget-parameters.md) / Color

# Color parameter

| Value type | Explanation                                                                                                                                             | Multilingual |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------ |
| String     | When rendering the markup, the **render()** method receives the parameter value as it is. There is a color picker in GUI to change the parameter value. | No           |

## Editor example in GUI

<p align="center">
  <img src="../../assets/components/widget-parameters-color.jpg" width="400px" alt="Color parameter", style="border-radius:10px; border: 1px solid #ddd;">
<span style="display:block;">Color parameter</span>
</p>

GUI offers a color editor. The editor offers a color picker that users can set the parameter value.

## Example and explanation

`params.json`

```json
[
    {
        "name": "textColor",
        "type": "color",
        "label": "Text color"
    }
]
```

`Widget data` Head over to [JSON schema](#json-schema) to check how the widget data is validated.

```json
{
    "widget": "MyWidget",
    "id": "MyWidget1",
    "params": {
        "textColor": "rgb(0, 0, 0)"
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

        $color = $params['textColor'];

        return [
            'tag' => 'h1',
            'data' => "Sample header.",
            'style'=> "color:$color;"
        ];
    }
}

```

## JSON schema

```json
{
    "type": "string"
}
```
