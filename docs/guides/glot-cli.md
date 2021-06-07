# GLOT CLI

## Actions

-   create ENTITY-TYPE ENTITY-NAME --target=PATH
-   edit ENTITY-TYPE ENTITY-NAME
-   save ENTITY-TYPE ENTITY-NAME
-   sync ENTITY-TYPE ENTITY-NAME // saves and fetches
-   delete ENTITY-TYPE ENTITY-NAME
-   build PIPELINE-NAME
-   publish HOST-NAME // requires GitHubClient?
-   record // auto on build/save. can be turned off. set max history.
-   watch
-   undo
-   redo

Head over to [CLI Actions](cli-actions.md) to learn more detail.

## Sub-modules

### PackageEditor

Create and edit component packages. Use templates for them. Set the right Composer properties.

### GitToolkit

Methods to resolve git conflicts plus standard git commands, like fetch, commit, etc. It should also manage virtual Composer repositories, git subtrees,
and git submodules. All those make sense for widgets that are developed within a website project.

Make sure to add gitHub as remote. Maybe use the GitHubClient to create a repo.

Add tags and versions on publish. Add glot tags.

### GitHubClient

Wrapper for the GitHub API. Create repos, etc. Maybe use uploader class. The point is to ???


### ActionRecorder
