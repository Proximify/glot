<p align="center">
  <img src="../../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](../framework.md) / [Components](../components.md) / [Widget package](widget-packages.md) / Change logs

# Change logs of widget package

## 3.0.1

<hr style="border-width:1px; height:1px;"></hr>

_Released 10/14/2020_

-   Create a folder **_settings_** under the root level of the widget package.
-   Move the file from **_maker/params.json_** to **_settings/params.json_**.
-   Move and rename the file from **_store/settings.json_** to **_settings/component.json_**. Head over to [File Structure](../components.md#file-structure) to learn the detail.
-   Move the content of the file **_.config.json_** to the file **_settings/component.json_**.
-   Create a new file **_settings/constraints.json_**. Move the constraints related properties from **_settings/component.json_** to **_settings/constraints.json_**. Head over to [constraints.json](./widget-packages.md#settings/constraints.json) to learn the detail.
-   Move and rename the file **_dependencies/externalLibs.json_** to **_settings/dependency.json_**.
-   Remove the folder **_dependencies_**.
-   Create a new file **_settings/polyfills.json_**. Move the polyfills property from **_settings/dependency.json_** to **_settings/polyfills.json_**. Move the pre-defined IE polyfills property from **_settings/component.json_** to **_settings/polyfills.json_**. The polyfills related properties are placed in the same place. Head over to [polyfills.json](./widget-packages.md#settings/polyfills.json) to learn the detail.
-   Change the folder name from **_maker_** to **_src_**.
-   Move the **_js_** and **_css_** folders from the root level of the widget package to **_src_** folder.

The new file structure of the widget package is as bellow:

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
├── settings
│   ├── component.json
│   ├── constraints.json
│   ├── dependency.json
│   ├── params.json
│   └── polyfills.json
├── src
│   └── MyWidget.php
│   ├── css
│   │   └── MyWidget.scss
│   │   └── MyWidget.css
│   │── js
│   │   └── MyWidget.js
├── store
│   ├── assets
│   │   └── widget_icon.svg
│   └── profile.md
├── LICENSE
├── README.md
└── composer.json
```
