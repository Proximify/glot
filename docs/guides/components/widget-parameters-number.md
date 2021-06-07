<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Widget parameters](widget-parameters.md) / Number

# Number parameter

| Value type | Explanation                                                                                                                                                                                                                           | Multilingual |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------ |
| String     | When rendering the markup, the **render()** method receives the same number as the parameter value, and the value is a number with a unit. Compared with Simple Number parameter, in GUI, users can select unit from a dropdown menu. | No           |

## Editor example in GUI

<p align="center">
  <img src="../../assets/components/widget-parameters-number.jpg" width="400px" alt="Number parameter", style="border-radius:10px; border: 1px solid #ddd;">
<span style="display:block;">Number parameter</span>
</p>

GUI offers a number editor. Compared with simple number editor, the number editor offers a dropdown menu for unit selection.

## Example and explanation

`params.json`

```json
[
    {
        "name": "size",
        "type": "number",
        "label": "Font size"
    }
]
```

`Widget data` Head over to [JSON schema](#json-schema) to check how the widget data is validated.

```json
{
    "widget": "MyWidget",
    "id": "MyWidget1",
    "params": {
        "size": "16px"
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

        $size = $params['size'];

        return [
            'tag' => 'h1',
            'data' =>'Sample header.',
            'style'=>"font-size:$size;"
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
