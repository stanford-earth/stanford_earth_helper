# Stanford Earth Helper

8.x-1.0-alpha46
-------------------------------------------------------------------------------_
_Release Date: 2021-05-26

_EARTH-000 Delete extraneous url aliases after capx and event imports.

8.x-1.0-alpha45
-------------------------------------------------------------------------------_
_Release Date: 2021-04-21

_EARTH-000 Trim extra space from event feed "bookmark" titles.
_EARTH-000 Redirect profiles to Stanford Profiles when no longer SE3.

8.x-1.0-alpha44
-------------------------------------------------------------------------------_
_Release Date: 2021-01-19

_EARTH-000 Make sure "bookmarked" is removed from event feed descriptions.

8.x-1.0-alpha43
-------------------------------------------------------------------------------_
_Release Date: 2020-09-17

_EARTH-000 Create separate people search MS tag for E-IPER non phd grads.

8.x-1.0-alpha42
-------------------------------------------------------------------------------_
_Release Date: 2020-09-16

_EARTH-000 Update department tagging in events importer.
_EARTH-000 Update phd workgroup handling for E-IPER.

8.x-1.0-alpha41
-------------------------------------------------------------------------------_
_Release Date: 2020-07-03

_EARTH-000 Prevent duplicate event images from propagating.

8.x-1.0-alpha40
-------------------------------------------------------------------------------_
_Release Date: 2020-05-13

_EARTH-000 Trim when string in events import if bigger than 512.
  
8.x-1.0-alpha39
-------------------------------------------------------------------------------_
_Release Date: 2020-03-07

_EARTH-000 Codesniffer fixes.
_EARTH-000 Refactor profile migrations configuration.
_EARTH-1241 Remove daily repeating events.
_EARTH-1282 Add additional events field to import & turn keywords into string.
_EARTH-1282 Force alt tag for event images to ‘’.

8.x-1.0-alpha38
-------------------------------------------------------------------------------_
_Release Date: 2020-02-10

_EARTH-1368 Fix dttucker email address on default contact card.

8.x-1.0-alpha37
-------------------------------------------------------------------------------_
_Release Date: 2020-01-14

_EARTH-1329 Fix event tagging from Sustainability event feeds.
_EARTH-1325 Fixes for upgrade to Drupal 8.8.1 and CAP image import.

8.x-1.0-alpha36
-------------------------------------------------------------------------------_
_Release Date: 2019-12-04

_EARTH-1178 Cleanup profiles import to remove tags; import individual sunet.
_EARTH-1261 Tagging for farm, wrigley, ssp workgroups.
_EARTH-1262 Add metags_alter hook for subside titles.
_EARTH-1300 Update faculty and alumni links.
_EARTH-1312 Add b-lazy class.

8.x-1.0-alpha35
-------------------------------------------------------------------------------_
_Release Date: 2019-09-26

_Tweak to Stanford Events ical feed.

8.x-1.0-alpha34
-------------------------------------------------------------------------------_
_Release Date: 2019-09-10

_Updates to Mosaic Blocks submodule.
_Tweaks to Stanford Events ical feed.

8.x-1.0-alpha33
-------------------------------------------------------------------------------_
_Release Date: 2019-07-28

_Code for MAINSITE tag in news and spotlights, coopts event image cleanup code.
_Add support for ical feed for events.

8.x-1.0-alpha32
-------------------------------------------------------------------------------_
_Release Date: 2019-07-08

_Events image cleanup.
_Add Farm tag to event department field.

8.x-1.0-alpha31
--------------------------------------------------------------------------------
_Release Date: 2019-06-25

_Profile media cleanup bug fix.

8.x-1.0-alpha30
--------------------------------------------------------------------------------
_Release Date: 2019-06-25

_Clean up duplicate and unused profile images.
_Update mosaic images to be more efficient.
_Code cleanup.

8.x-1.0-alpha29
--------------------------------------------------------------------------------
_Release Date: 2019-06-05

_Fix events import problem for events with no images in feed.
_Add update hook for profile imports to fix cap profile links with no scheme.

8.x-1.0-alpha28
--------------------------------------------------------------------------------
_Release Date: 2019-06-03

_Fix bug in links from Stanford Profiles (CAP) API missing URL Scheme.

8.x-1.0-alpha27
--------------------------------------------------------------------------------
_Release Date: 2019-04-18

_Improves batch processing of event importers via migration API.
_Fixes up management of pre and post process orphan cleanup.
_Implements a EarthMigrationLock class so only one session at a time kills orphans.
_Wait, that didn't sound right. Deletes orphan events, I should say.
_Ensure duplicate events don't get imported, especially after adding/removing feeds.

8.x-1.0-alpha26
-------------------------------------------------------------------------------
¯\_(ツ)_/¯

8.x-1.0-alpha25
--------------------------------------------------------------------------------
_Release Date: 2019-03-27

_Adds pre-process and post-process migrations to clean up orphan events.
_Set up to now run using "drush migrate:import --group=earth_events --update=UPDATE.
  
8.x-1.0-alpha24
--------------------------------------------------------------------------------
_Release Date: 2019-03-26

_Rewrite of stanford_earth_events module to be more like stanford_earth_capx
_Creates individual migrations for each Stanford Events URL.
_Updates previously imported events when changes have been made on Stanford Events.
_Removes previously imported events when removed completely from Stanford Events.
_Keeps "Unlisted" events unlisted even if status changes to "Canceled".

8.x-1.0-alpha23
--------------------------------------------------------------------------------
_Release Date: 2019-03-08

_Move code to fix up existing profile search terms from installN to a routed func.

8.x-1.0-alpha22
--------------------------------------------------------------------------------
_Release Date: 2019-03-07

_Set first and last names in profiles for workgroup people w/o cap profiles.

8.x-1.0-alpha21
--------------------------------------------------------------------------------
_Release Date: 2019-03-07

_Trim profiles Appointments field if greater than 255 characters.

8.x-1.0-alpha20
--------------------------------------------------------------------------------
_Release Date: 2019-03-06

_Fix missing use statement.

8.x-1.0-alpha19
--------------------------------------------------------------------------------
_Release Date: 2019-03-06

_Add workgroup members not found in CAP to profile imports.
_Clean up code.

8.x-1.0-alpha18
--------------------------------------------------------------------------------
_Release Date: 2019-02-07

_Fix email bug.

8.x-1.0-alpha17
--------------------------------------------------------------------------------
_Release Date: 2019-02-06

_Updates to improve batch processing and manage regular faculty vs associate.

8.x-1.0-alpha16
--------------------------------------------------------------------------------
_Release Date: 2018-12-04

_Updates CAP-X config form to use batch API to create migration for each workgroup.
_Mucks with migrate_tools batch migration to make fewer calls to API.

8.x-1.0-alpha15
--------------------------------------------------------------------------------
_Release Date: 2018-11-07

_Added option to refresh *all* profile images on capx_update_all, even if current.

8.x-1.0-alpha14
--------------------------------------------------------------------------------
_Release Date: 2018-11-05

_Remove one-time fix for Drupal account names.
_Change capx profile image from 'bigger' to 'square'.
_Make batch processing of capx import more efficient.

8.x-1.0-alpha13
--------------------------------------------------------------------------------
_Release Date: 2018-10-17

_Allow more time in capx submodule for process of creating migrations per wg.
_One time fix for existing Drupal account names.

8.x-1.0-alpha12
--------------------------------------------------------------------------------
_Release Date: 2018-10-15

_ Add fields contact phone, contact email, admission info, & map_url to events.
_ Add workgroup tracking and better SAML-account integration to profiles.

8.x-1.0-alpha11
--------------------------------------------------------------------------------
_Release Date: 2018-10-03

_ Updated cap-x migrations with all faculty workgroups.
_ Creates taxonomies for people search.

8.x-1.0-alpha10
--------------------------------------------------------------------------------
_Release Date: 2018-08-16

_ Updated event migration configurations per EARTH-831
_ Fixed event migration problem on first URL in array (index 0).

8.x-1.0-alpha9
--------------------------------------------------------------------------------
_Release Date: 2018-07-18

_ Fixed Events import to display proper "more info" link.
_ Tag imported events with appropriate department/program.
_ Added support for next version which will track Profile import workgroups.

8.x-1.0-alpha8
--------------------------------------------------------------------------------
_Release Date: 2018-06-15

_ Fixed stanford_earth_capx event listeners running for stanford_earth_event rows.
_ Fixed stanford_earth_capx hook_migrate_prepare_row from running on event rows.

8.x-1.0-alpha7
--------------------------------------------------------------------------------
_Release Date: 2018-06-14_

- Added configuration admin form for feeds list.
- Added initial importer for Stanford Profiles (CAP-X).

8.x-1.0-alpha6
--------------------------------------------------------------------------------  
_Release Date: 2018-05-17_

- Added new dependencies on migrate_plus, migrate_tools, migrate_file in composer.json
- Added new stanford_earth_events module for importing events from events.stanford.edu
- Changed default contact on news media contact from Barb to Josie

8.x-1.0-alpha5
--------------------------------------------------------------------------------  
_Release Date: 2018-02-14_

- Added stanford_subsites module and functionality.

8.x-1.0-alpha4
--------------------------------------------------------------------------------  
_Release Date: 2018-02-14_

- Changed the default media contact

8.x-1.0-alpha3
--------------------------------------------------------------------------------  
_Release Date: 2017-12-04_

- Added spaces to Mosaic blocks.
- Set new News content to have a hero banner with the tall option as default.


8.x-1.0-alpha2
--------------------------------------------------------------------------------  
_Release Date: 2017-11-06_

- Changed links for Mosaic blocks.


8.x-1.0-alpha1
--------------------------------------------------------------------------------  
_Release Date: 2017-10-27_

- Initial Release
