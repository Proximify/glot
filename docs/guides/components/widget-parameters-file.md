<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / [Widget parameters](widget-parameters.md) / File

# File parameter

| Value type | Explanation                                                                                                                                                                                                            | Multilingual |
| ---------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------ |
| String     | The value of the parameter is the relative path to the asset based on the **assets** folder. And the **render()** method receives the same value as it is. In GUI, users can choose asset of the website as the value. | Yes          |

## Editor example in GUI

<p align="center">
  <img src="../../assets/components/widget-parameters-media.jpg" width="400px" alt="File parameter", style="border-radius:10px; border: 1px solid #ddd;">
<span style="display:block;">File parameter</span>
</p>

GUI offers a file editor. Users can select the asset from the asset panel.

<p align="center">
  <img src="../../assets/components/widget-parameters-media2.jpg" width="400px" alt="File parameter", style="border-radius:10px; border: 1px solid #ddd;">
</p>

After clicking the button, the panel is switched. Users can select an asset to be the parameter value.

## Example and explanation

`params.json`

```json
[
    {
        "name": "img",
        "type": "media",
        "label": "Image",
        "description": "Path of the image."
    }
]
```

`Widget data` Head over to [JSON schema](#json-schema) to check how the widget data is validated.

```json
{
    "widget": "MyWidget",
    "id": "MyWidget1",
    "params": {
        "img": {
            "en": "sky.jpg",
            "zh": "sub-folder/star.png",
            "$en": {
                "status": "approved",
                "author": "Diego",
                "time": 1599675492,
                "isSource": true
            },
            "$zh": {
                "status": "pending"
            }
        }
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

        $img = $params['img'];
        $alt = $params['alt'];

        return [
            'class'=>'figure',
            'data'=>[
                [
                    'class'=>'caption',
                    'data'=>$img
                ],
                [
                    'tag' => 'img',
                    'src' => $this->makeAssetUrl($img),
                    'alt' => $alt
                ]
            ]
        ];
    }
}

```

## JSON schema

```json
{
    "type": "object",
    "description": "Href parameter",
    "properties": {
        "$": {
            "type": "object",
            "description": "Meta data of translation",
            "properties": {
                "state": {
                    "type": "object",
                    "description": "state. Pairs of language and state",
                    "patternProperties": {
                        "^[a-z]?$": {
                            "type": "object",
                            "properties": {
                                "master": {
                                    "type": "string",
                                    "enum": [
                                        "pending",
                                        "inprogress",
                                        "translated",
                                        "approved"
                                    ]
                                },
                                "secondary": {
                                    "type": "string",
                                    "enum": [
                                        "returned",
                                        "prepopulated",
                                        "awaiting",
                                        "machineTranslated"
                                    ]
                                },
                                "isSource": {
                                    "type": "boolean"
                                }
                            }
                        }
                    }
                },
                "lastAuthor": {
                    "type": "object",
                    "description": "Last edit user. Pairs of language and user id.",
                    "patternProperties": {
                        "^[a-z]?$": {
                            "type": "integer"
                        }
                    }
                },
                "time": {
                    "type": "object",
                    "description": "Last edit time. Pairs of language and value.",
                    "patternProperties": {
                        "^[a-z]?$": {
                            "type": ["integer", "string"]
                        }
                    }
                }
            },
            "additionalProperties": false
        }
    }
}
```
