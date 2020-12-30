# Processing Pivotal Tracker stories #

## Unstarted stories ##

  1. run on CLI: `php get_tasks.php`
    a. Skips existing issues based on the "Pivotal ID" custom field value
    b. creates new issues in Mavenlink and assign to users, based on the $owner array in the settings.php file

## Accepted stories ##

  1. run on CLI: `php get_tasks.php accepted`
    a. Skips PT stories with "accepted" that are not in Mavenlink status
    b. Sets status to "Resoled" on existing issues based on the "Pivotal ID" custom field value

## Archiving stories  ##

  1. run on CLI: `get_resolved.php`
    a. checks for issues on the specified `workspace_id` wth status = resolved and if the creation month is earlier than the current month, archives the issues

## Settings.php ##

This file contains all the settings needed by the scripts. (rename settings_sample.php)
 . PT Token
 . ML Token
 . Custom Field ID
 . ML Workspace ID
 . PT Story Owner
 . Assignee ID (to be implemented)
