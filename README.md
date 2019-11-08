[![Build Status](https://travis-ci.com/DiscipleTools/disciple-tools-dispatcher-tools.svg?branch=master)](https://travis-ci.com/DiscipleTools/disciple-tools-dispatcher-tools)

# Disciple Tools Dispatcher Tools
The Disciple Tools Dispatcher Tools is intended to accelerate integrations and extensions to the Disciple Tools system.
This basic plugin dispatch_Tools has some of the basic elements to quickly launch and extension project in the pattern of
the Disciple Tools system.


### The dispatcher tools is equipped with:
1. Wordpress style requirements
1. Travis Continueous Integration
1. Disciple Tools Theme presence check
1. Remote upgrade system for ongoing updates outside the Wordpress Directory
1. Multilingual ready
1. PHP Code Sniffer support (composer) @use /vendor/bin/phpcs and /vendor/bin/phpcbf
1. Dispatch_Tools Admin menu and options page with tabs.

### Refactoring this plugin as your own:
1. Refactor all occurences of the name `Dispatcher_Tools`, `dispatcher_tools`, `dispatcher-tools`, and `Dispatcher Tools` with you're own plugin
name for the `disciple-tools-dispatcher-tools.php and admin-menu-and-tabs.php files.
1. Update the README.md and LICENSE
1. Update the translation strings inside `default.pot` file with a multilingual sofware like POEdit, if you intend to make your plugin multilingual.
