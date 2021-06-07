<p align="center">
  <img src="../assets/glot_logo_new.svg" width="400px" alt="glot: compositional web framework">
</p>

[Framework](#framwork) / Dictionary object schema

# The GLOT Dictionary object schema

A dictionary object is a JSON object that collects information about a dictionary entry, such as: value, editing status, and localization settings.

## Available Properties

-   `isSource` **Bool** - Define the explicit source on the target language.
-   `status` **String** - Editing status of of the value of the target language. The statuses of the source language and target languages are slightly different.
    - Source language
        - **pending** - The source value is pending to edit.
        - **pending-prepopulated** - The source value is preset from the website template and it's never been changed.
        - **pending-returned** - The source value is returned for revision.
        - **inprogress** - The source value is being edited by someone.
        - **inreview** - Someone finishes editing the source value and the  value is being reviewed.
        - **approved** - The source value is approved.
    - Target language
        - **pending** - The target value is pending to edit.
        - **pending-awaiting** - The target value is awaiting translation.
        - **pending-returned** - The target value is returned for revision.
        - **inprogress** - The target value is being translated by someone.
        - **inprogress-machineTranslated** - The target value is translated by machine translation.
        - **inreview** - Someone finishes editing the target value and the value is being reviewed.
        - **approved** - The target value is approved.
- `author` **String** - The user name who edits the value.
-   `time` **Int** - The timestamp when user edits the value.
-   `autoTranslate` **Bool** - Whether auto translate the value when adding new language to the website in the GUI. If the parameter is used for URL, Image src or similar purpose, this property should be set to **false**.
