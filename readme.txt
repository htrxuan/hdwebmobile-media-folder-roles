=== HDWebmobile Media Folder Roles ===
Contributors: htrxuan
Donate link: https://paypal.me/htrxuan/20
Tags: media, folders, permissions, roles, publishpress
Requires at least: 5.8
Tested up to: 7.1
Stable tag: 1.1.5
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage media folders and set role-based permissions for your WordPress Media Library. Compatible with PublishPress Capabilities.

== Description ==

HDWebmobile Media Folder Roles allows you to organize your WordPress Media Library into hierarchical folders and restrict access to these folders based on user roles.

Key Features:
* Create and manage hierarchical media folders.
* Assign role-based permissions to each folder.
* Restrict media viewing and editing based on folder permissions.
* Fully compatible with PublishPress Capabilities for advanced role management.

== Installation ==

1. Upload the `hdwebmobile-media-folder-roles` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Media > Folders to start organizing your library.

== How to Use ==

= 1. Create your folders =
Go to **Media > Folders** (Screenshot 3). This is a standard WordPress taxonomy screen — add a folder by name the same way you'd add a category, and optionally assign a **Parent Media Folder** to build a hierarchy (e.g. "Team Photos" under "Marketing").

= 2. Set who can access each folder =
Click into any folder to edit it and you'll see an **Allowed Roles** checklist (Screenshot 2) alongside the normal name/slug/parent/description fields. Tick every role that should be able to view and use media inside that folder — Administrators can always access everything regardless of what's ticked. Leaving every box unticked means only Administrators can see that folder's contents.

= 3. Organize and browse from the Media Library =
Open **Media > Library** and you'll see a **Folders** panel added to the left of the normal grid/list view (Screenshot 1). From here you can:

* Click **New Folder** to create one without leaving the Library.
* Drag and drop media items onto a folder in the sidebar to file them.
* Click a folder to filter the grid down to just its contents; click **All Files** to clear the filter.
* Use **Rename** / **Delete** above the folder tree while a folder is selected.

= 4. What non-admin users see =
Once you save role restrictions on a folder, any logged-in user whose role isn't on that folder's Allowed Roles list will simply not see that folder or its media in their own Media Library — the filtering happens automatically on both the classic list view and the AJAX-driven media grid/uploader, so it applies consistently everywhere media is selected (post editor, featured image, etc.).

= 5. Using PublishPress Capabilities (optional) =
If you also run PublishPress Capabilities, its more granular capability controls layer on top of this plugin's role-based folder access without any extra setup — this plugin doesn't require PublishPress Capabilities to work on its own.

== Screenshots ==

1. The Media Library with the Folders panel: browse, create, and drag-and-drop media between folders.
2. Editing a folder's Allowed Roles — pick exactly which WordPress roles can see this folder's media.
3. The Media > Folders management screen for creating folder hierarchies.

== Changelog ==

= 1.1.5 =
* Confirmed compatibility with WordPress 7.1.

= 1.1.4 =
* Confirmed compatibility with WordPress 7.0.

= 1.1.3 =
* Remove PHP session usage and switch selected-folder persistence to user meta.
* Strengthen security by requiring nonce and capability checks for upload and AJAX flows.
* Global rename of namespace, constants, classes, functions, and options to use the `hdwemefo` prefix for unique naming as per WordPress.org requirements.
* Replace generic taxonomy key with unique prefixed taxonomy (`hdwemefo_media_folder`) and include one-time migration from legacy `media_folder`.

= 1.1.2 =
* Update version for WordPress.org upload.
* Add plugin list Donate link and taxonomy-page support notice.

= 1.0.0 =
* Initial release with branding update and hdwemefo_ prefix.
