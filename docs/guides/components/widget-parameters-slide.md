<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Widget parameters](widget-parameters.md) / Slide

# Slide parameter

| Value type | Explanation                                                                                                                                                   | Multilingual |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------ |
| Integer    | When rendering the markup, the **render()** method receives the same number as the parameter value. In GUI, users can change the value from an slider in GUI. | No           |

## Editor example in GUI

<p align="center">
  <img src="../../assets/components/widget-parameters-slide.jpg" width="400px" alt="Slide parameter", style="border-radius:10px; border: 1px solid #ddd;">
<span style="display:block;">Slide parameter</span>
</p>

## Example and explanation

`params.json`

```json
[
    {
        "name": "level",
        "type": "slide",
        "label": "Level"
    }
]
```

`Widget data` Head over to [JSON schema](#json-schema) to check how the widget data is validated.

```json
{
    "widget": "MyWidget",
    "id": "MyWidget1",
    "params": {
        "level": 2
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

        $level = $params['level'];

        $tag = "h$level";

        return ['tag' => $tag, 'data' =>'Sample header.'];
    }
}

```

## JSON schema

```json
{
    "type": "integer"
}
```
