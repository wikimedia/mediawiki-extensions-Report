# [Report][1]

The **Report** extension adds a special page for users to privately report revisions of pages for admin attention, as well as another special page for admins to handle such reports.

## Installation
### Regular installation
Report is on [the MediaWiki extension distributor][2]. Follow the instructions on [the extension page][1] to install it from there like any other extension.

### Developer installation
From the root directory of your wiki, run the following commands:
```
cd extensions
git clone "https://gerrit.wikimedia.org/r/mediawiki/extensions/Report"
```
Then add the following line to your `LocalSettings.php`:
```
wfLoadExtension( 'Report' );
```
Finally, from the root directory of your wiki, run the following command:
```
php maintenance/update.php
```
This will create the necessary tables that the extension needs.

## Usage

Once it's installed, it's in use! The extension adds links next to every revision for reporting them, all of which lead to Special:Report.

For admins, simply navigate to Special:HandleReports to view reports that need handling.

[1]: https://www.mediawiki.org/wiki/Extension:Report
[2]: https://www.mediawiki.org/wiki/Special:ExtensionDistributor/Report
