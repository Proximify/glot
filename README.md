> [!IMPORTANT]
> The expected release date of this project is September 1, 2020.
> We are just setting things up for now.

# Building websites on a modular and multilingual framework

## The Generative Language of Objects and Text (GLOT)

GLOT is a markup language for generating websites in terms of objects and text. The language allows for **generative rules** that define the rendering of **object instances** and **localized text**. The _rules_ are written in JavaScript Object Notation (JSON). The language was designed for the building of modular and multilingual webpages.

GLOT can be seen as an intermediate language, but it is more accurate to describe it as a generative language because its objective is to take **parametric rules** and produce source code in standard web languages.
The process starts with **GLOT markup** and goes through a **rendering** step that outputs new source code in Hypertext Markup Language (HTML), JavaScript (JS), and Cascade Style Sheets (CSS).

Assets referenced by the input code, such as graphics and fonts, are considered to be both input and output of the generative process.

The language aims to capture compositional relations between _objects_. Such relations are the input to the **GLOT Renderer**, which in turn outputs the generated HTML markup. An **object** is defined loosely to include simple HTML elements and complex combination of HTML, JS and CSS. For example, an _object_ can be a navigation bar, a footer, an image, or just a DIV.

Each _object class_ referenced in the markup triggers the loading of a **PHP object package**, which is a type of plugin for the generation of the HTML, CSS, JS, and assets needed to _render_ each object defined in the source code.

<img src="docs/assets/glot_pipeline.svg" width="600" alt="GLOT Pipeline">

GLOT markup can be rendered very efficiently because it does not depend on a complex compiling process. That efficiency enables the GLOT Rendered to be an effective engine for both static and dynamic webpages.

<!-- Explain that this a reference implementation. And that it is normally used as the engine
of a GUI tool when building a site. What it provides is a target language for the GUI that abstracts out all the complexity of putting things together and managing plugin dependencies. -->

## Comparison to other website building frameworks

GLOT is complementary to general-purpose web frameworks, such as React and AngularJS, because it is not opinionated regarding the type of HTML, CSS and JS is generated with it. The language is a straightforward set of compositional and **parametric rules**. A piece of GLOT code can be understood as a generative, parametric model of any type of webpage.

The language abstracts out the complexity of putting things together and managing widget and plugin dependencies. In this respect, it is a replacement of website builders and CMS, such as WordPress or Drupal, that define frameworks based on their own types of web components. The key difference is that GLOT is designed to be a portable definition of a website that works across different software tools.

## The GLOT language

A **GLOT element** is defined as an unordered set of name/value pairs of the form `{"name": value, "name": value, ...}`, such that each pair is either:

-   A valid combination of HTML attribute/value pair. E.g. `"type": "checkbox"`; or
-   The name `tag` and a value equal to a valid HTML tag. E.g. `"tag": "input"`; or
-   The name `widget` and a value that matches the name of a widget package available to the GLOT rendered. E.g. `"widget": "Navbar"`; or
-   The name `params` and a value set to a JSON object with its own set of name/value pairs. The valid name/value pairs of `params` are widget-dependent, and therefore are only meaningful if a `widget` value is also given; or
-   The name `data` and a value equal to a GLOT element, a plain string, or an array of GLOT elements and/or strings.

The `tag` property name defines the type of rendered HTML element while the `data` property name defines the _contents_ of the element and the _inclusion_ relation between elements.

All property names are optional and their values default to `unset` if not given, with one exception: the property name `tag` defaults to `div` if not given. In other words, if not present, the name/value pair `"tag": "div"` is assumed to be implicitly given.

By definition, the GLOT markup language is a strict superset of the HTML markup language and can represent generative rules for any possible HTML webpage.

**Example 1: GLOT input and HTML output**

```json
// Input in GLOT
{
    "tag": "h1",
    "data": "Hello World!",
    "tag": "a",
    "href": "https://proximify.com",
    "data": {
        "tag": "img",
        "src": "hello_world.png"
    }
}
```

```html
<!-- HTML Output (body contents only) -->
<h1>Hello World!</h1>
<a href="https://proximify.com">
    <img src="hello_world.png" />
</a>
```

### Generating object instances and localized text

GLOT elements can represent rules for the generation of object instances and localized text as a function of given data and parameters.

#### Object rules

GLOT defines **object** as an element whose rendered attributes and content depend on a plugin module. The external module is defined by a **widget package** and is responsible for generating a subset of the rendered output.

The simplest example of a widget package is given by a PHP class with a `render($data, $params)` method that returns an HTML string. Widget packages can be quite sophisticated and include JavaScript, CSS, file assets, dependency rules, and other types of documents.

The objective of a _GLOT object_ is to capture repetitive web patterns that can be parameterized to achieve a meaningful range of useful renderings.

**Example 2: Component Package**

```php
// A GLOT object class
class SearchBar extends Widget
{
    function render($data, $params)
    {
        // Use $data and $params to generate some HTML markup.
        $markup = '...';

        return $markup;
    }
}
```

The rendering of an object instance is controlled by the special keys `widget` and `params`. For example,

```json
// Object rule in GLOT
{
    "widget": "SearchBar",
    "data": { "tag": "span", "data": "Search for..." },
    "params": { "mode": "compact" }
}
```

will invoke the rendering of a `SearchBar` object with object-dependent parameters (in this case, an imaginary _compact_ display mode).

Since the example above does not set an explicit `tag` key, the HTML tag of the object is assumed to be `div` by default. The attributes and contents of the output DIV element depend entirely on the rendering function defined in the `SearchBar` object package, and the given data and parameters set for this particular `SearchBar` instance.

#### Text rules

GLOT defines **text** as either a generic string or a JSON object from _W3C-language-region-codes_ to _strings_. The JSON object form is called **localizable text**. A localizable text also allows for the special key **@** to capture metadata regarding text editing and translations (see XXX for a definition of the metadata).

**Example 3: Localizable Text**

```json
{
    "en": "Hello World!",
    "fr": "Bonjour le monde!",
    "es-ar": "¡Hola Mundo!",
    "@": { "author": "Jon" }
}
```

A localizable text can be used as a value for an object parameter or for an HTML attribute. For example,

```json
// Object with localizable text as:
// - attribute value; and
// - parameter value
{
    "widget": "Picture",
    "src": {
        "en": "forest_in_england.png",
        "fr": "forest_in_france.png"
    },
    "params": {
        "mode": "compact",
        "caption": {
            "en": "A nice forest",
            "fr": "Une belle forêt"
        }
    }
}
```

### Pages, Master pages and Super-master pages

A file with GLOT markup is considered to be a single webpage. A GLOT object takes the _role_ of **Page** when it is the top container of all other elements in a file. In addition, pages are given some special considerations that go beyond those of regular widgets.

One important aspect of pages is that they normally render objects that are common across several other pages of the same website. To accommodate this need, GLOT defines a type of object instance named **Master Page**.

Any widget that fulfills the rendering needs of a webpage can be selected as the basis to build a master page. This means that a master page is not a GLOT object but an _instance_ of a GLOT object.

An instance of a widget taking the role **Page** is allowed to link to a master page. The rendering process for a page widget linked to a master is to merge the result of rendering the master and then the widget instance into a single output.

<!-- The concept of master page is common in rich text editors and presentation programs since document pages and presentation slides tend to have common elements such as headers and footers. In general, common elements have a similar structure, but sometimes they vary in some way. For example, the footer looks the same but the page number is different in each page. -->

By merging the renderings of a master page with that of a page instance, GLOT is able to represent both the commonality across pages and their individual variability.

Websites can be often be quite complex and require several master pages to capture commonalities within different subsets of pages of a website. When this happens, it is often common to have _master pages_ sharing commonalities between them.

GLOT allows a master page to link to another master page. A master that is used as master by another master is said to take a **super-master** role.

The rendering process of a page linking to a master page that links to a super-master page is to first render the super-master, then the master and then the page, and merge the result in a single output.

A super-master page is a regular master page and can be used as such by a regular page. The only special property of a super-master page is that it cannot link to another master page when it takes the role of super-master.

<!-- In other words, there is no explicit notion of a page. For as long as the top-level widget renders into an HTML page structure, with `<head>...</head>` and `<body>...</body>` elements, then the final output should be a valid webpage. -->

## Installing the GLOT Renderer

Get it from GitHub. You need PHP 7.4 or later.

## Coding webpages in GLOT

Any text editor can be used to write GLOT source code. The best approach is to choose an editor that simplifies the writing of PHP, JS, and CSS code, such as [Visual Studio Code](https://code.visualstudio.com/).

As an example, we will create a website with two simple webpages. Both webpages will using a Page widget to create the standard HTML code of a webpage, and to additional widgets to generate the navbar and footer in each page. The rest of the pages will be defined in terms of simple GLOT markup and localizable text.

## Demos and examples

-   Point to pages made in GLOT.
-   Offer downloads of example GLOT projects.

## Graphic User Interface

List of GLOT graph user interfaces for creating GLOT projects.

-   UNIWeb Studio

### Creating Object Packages

<!-- ![404 example](/docs/assets/glot/widget_pkg_tree.png) -->
