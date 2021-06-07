<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / Widget package

# Widget package

At a minimum, **widget package** defines a class in **PHP or NodeJS** with a render method that returns GLOT or HTML code for the given parameters of a widget. The package can also define client-side JavaScript, CSS, and general assets such as fonts and images.

## File structure

```txt
MyWidget
├── assets
│   ├── images
│   │   └── example.svg
│   └── fonts
├── dictionaries
│   ├── markup.json
│   ├── profile.json
│   └── panel.json
├── config
│   ├── settings.json
│   ├── constraints.json
│   ├── dependency.json
│   ├── params.json
│   └── polyfills.json
├── src
│   ├── MyWidget.php
│   ├── css
│   │   └── MyWidget.scss
│   │   └── MyWidget.css
│   └── js
│       └── MyWidget.js
├── store
│   ├── assets
│   │   └── widget_icon.svg
│   └── profile.md
├── styles
│   ├── default.json
│   └── widget_classes.json
├── templates
│   ├── 1.json
│   └── templates.json
├── LICENSE
├── README.md
└── composer.json
```

### Examples & Explanation

#### `src/css/MyWidget.css`

```css
.MyWidget {
    background-color: white;
}
```

The widget package allows for other CSS Pre-Processors enabled via optional plugins. Developers can write stylings in `css/MyWidget.scss` or `css/MyWidget.less` in those case. During development, the [Builder](builder.md) generates their minimized css file.

#### `dictionaries/markup.json`

The dictionary of the widget markup. When developers try to make a multilingual widget, they can put the multilingual text in the `markup.json`.

`dictionaries/markup.json`

```json
{
    "label": {
        "en": "Label",
        "fr": "Étiquette"
    }
}
```

`src/MyWidget.php`

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

        $text = $this->getText('label');

        return ['tag' => 'div', 'data' => $text];
    }
}
```

#### `dictionaries/panel.json`

The dictionary of the widget panel in GUI. The keys are the names of the properties.

```json
{
    "img": {
        "label": {
            "en": "Image",
            "fr": "Image"
        },
        "description": {}
    },
    "alt": {
        "label": {
            "en": "Alt",
            "fr": "Alt"
        },
        "description": {}
    },
    "advancedOptions": {
        "label": {
            "en": "Advanced options",
            "fr": "Options avancées"
        },
        "description": {}
    }
}
```

#### `dictionaries/profile.json`

The dictionary of the widget profile in widget store. Multilingual text for widget label and description.

```json
{
    "label": {
        "en": "My Widget",
        "fr": "Mon widget"
    },
    "description": {
        "en": "The first test widget.",
        "fr": "Le premier widget de test."
    }
}
```

#### `src/js/MyWidget.js`

```javascript
class MyWidget extends __Widget {
    /**
     * Called once per page.
     */
    static initClass() {
        // init generic options
    }

    /**
     * Called once per object instance in a page.
     */
    render() {
        console.log(this.holder.id);
        console.log(this.options);
    }
}
```

#### `settings/settings.json`

```json
{
    "version": "1.0.44",
    "name": "MyWidget",
    "label": "My Widget",
    "icon": "widget_icon.svg",
    "storeImage": "widget_card.svg",
    "storeSlides": [],
    "description": "Description of the widget",
    "category": "",
    "tags": [],
    "role": "",
    "needRefresh": true,
    "publishedTime": 1600824350
}
```

Head over to the [Widget basic settings](widget-settings.md) for more information of properties.

#### `settings/constraints.json`

Constraints of children widgets and parent widgets. When adding children widgets into the active widget in GUI, only those widgets whose `implementedConstraints` meet the conditions of `childConstraints` and `parentConstraints` are available options. There are 4 different type of conditions: [`mandatory`](#mandatory) [`optional`](#optional) [`exclude`](#exclude) [`disallowChildren`](#disallowChildren)

```json
{
    "childConstraints": {
        "mandatory": [],
        "optional": [],
        "exclude": [],
        "disallowChildren": false
    },
    "parentConstraints": {
        "mandatory": [],
        "optional": [],
        "exclude": []
    },
    "implementedConstraints": ["navbar_child"]
}
```

##### `mandatory`

Mandatory conditions, children widgets must meet the conditions that declared in this property.

##### `optional`

Optional conditions, children widgets which meet more optional conditions will be highly recommended.

##### `exclude`

Exclude conditions, conditions that the children widgets should not meet.

##### `disallowChildren`

The flag whether the widget can have children widgets in GUI.

#### `settings/dependency.json`

```json
{
    "js": [
        {
            "lib": "jquery",
            "version": "3.5.1",
            "url": "https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"
        },
        {
            "lib": "twitter-bootstrap",
            "version": "4.4.1",
            "url": "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/js/bootstrap.min.js"
        }
    ],
    "css": [
        {
            "lib": "twitter-bootstrap",
            "version": "4.4.1",
            "url": "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/css/bootstrap.min.css"
        }
    ]
}
```

This file declares the external libraries that this widget package needs. These external libraries will be placed before local libraries.

Different widgets may need same libraries in different order or different versions. Only the latest version of the same libraries will be included. During rendering the HTML, the weights of the dependency between libraries will be calculated and they will be used for including libraries in most reasonable order.

#### `settings/params.json`

```json
[
    {
        "name": "label",
        "type": "text",
        "label": {
            "en": "Label"
        },
        "editor": true,
        "live": true,
        "linkOption": false,
        "notranslate": false
    }
];
```

Head over to the [Widget parameters](widget-parameters.md) for more information of widget parameter schema.

##### `settings/polyfills`

```json
{
    "polyfillIO": ["IntersectionObserver", "Array.from"],
    "predefined": {
        "async": true,
        "picturefill": true
    }
}
```

**polyfillIO** are pre-set JavaScript libraries declared in [polyfill.io](https://https://polyfill.io/v3/url-builder/) and **predefined** are predefined polyfills that are declared in the **Builder**. Most predefined polyfills are used for supporting IE 11. Polyfills will be placed before the normal external libraries.

#### Predefined polyfills

```json
{
    "async": {
        "label": "Async/await",
        "src": "https://unpkg.com/regenerator-runtime/runtime.js"
    },
    "svgUseInIE11": {
        "label": "External SVG Polyfill",
        "src": "https://cdnjs.cloudflare.com/ajax/libs/svgxuse/1.2.6/svgxuse.min.js",
        "defer": true
    },
    "promise": {
        "label": "Promise",
        "src": "https://cdn.jsdelivr.net/npm/promise-polyfill@8/dist/polyfill.min.js"
    },
    "fetch": {
        "label": "Fetch",
        "src": "https://cdn.jsdelivr.net/npm/whatwg-fetch@3.4.1/dist/fetch.umd.min.js"
    },
    "picturefill": {
        "label": "Picture fill",
        "src": "https://cdnjs.cloudflare.com/ajax/libs/picturefill/3.0.3/picturefill.min.js",
        "async": true,
        "script": {
            "pos": "pre",
            "data": "<script>document.createElement( 'picture' );</script>"
        }
    }
}
```

#### `src/MyWidget.php`

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

        return ['tag' => 'div', 'data' => $data];
    }
}
```
