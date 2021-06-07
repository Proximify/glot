# Sites folder

The root-level `sites` folder is the default location for placing websites folders. 

Normally, a project includes the **Renderer** as a dependency and defines its own `sites` folder. This particular `sites` folder is used in a standalone setting or for testing the **Renderer** without having to create an independent project.

This root-level folder must be named `sites` for the renderer to recognize it. Otherwise an explicit path can be set at construction time:

    $new Renderer([Renderer::PATHS => ['sites' = '...', 'www' => '...']);

If the folder doesn't exist and no path is given, the renderer assumes that there is a single site located at the project's root folder.
