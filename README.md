# File Abstraction Layer driver for WebDAV

This extension provides the necessary interfaces to connect a TYPO3 instance to a WebDAV storage. Files can then easily
be embedded into content or listed on the site.

Encrypted communication and user authentication for WebDAV are supported.

## Setup

After downloading/cloning the extension, execute `composer install` in `Resources/Composer/`. This is required to get
the necessary third-party libraries for WebDAV communication.

After that, create a "File Storage" record (on the root page of your installation). Select "WebDAV" as the driver and
fill in the required details.

User authentication can either be done via the URL (`http://user:password@webdav.example.com/`) or in separate fields.
In both cases, the URLs that are displayed publicly do not contain authentication information.

### A note on password-protected WebDAV storages

If your WebDAV requires authentication, do not mark the checkbox "Is publicly available?" in tab "Access". Otherwise,
links will point directly to it, requiring site visitors to enter the password.

In this case, you should also configure a different folder for the processed files, see setting "Folder for manipulated 
and temporary images etc.".

## Developer information

### Creating a ZIP file of the extension (for installation in EM)

Execute the following command in the folder:

    zip -9 -r ../fal_webdav_<version>.zip . -x Resources/Php/* -x .git/* -x */.git/*

Replace `<version>` with the version, e.g. `1.0.0`. Suffixes like `beta`, `alpha`, `dev` can be appended using a slash, 
e.g. `0.2.0-dev` or `1.0.0-beta1`.

## Contact

If you found a bug, create an issue on Github. If you need support or new features, drop me a line at
`dev (at) a-w dot io`.
