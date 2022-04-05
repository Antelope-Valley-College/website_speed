# Website Speed

## Installation

To install this module, `composer require` it, or  place it in your modules
folder and enable it on the modules page.

## Configuration

All settings for this module are on the Website Speed configuration page,
under the Configuration section, in the Development sub menu. You can visit the
configuration page directly at

Admin > Config > Development > Performance > Website Speed

admin/config/development/website-speed

To get more accurate timing of page responses from the point of start
of execution in index.php, you can add the following snippet right
after the opening php tag in index.php

```
$_website_speed_timer = microtime(TRUE);
```

## How this works

Website Speed module keeps track of the time from the start of page
execution (if the timer is initialized in index.php) or from the point
KernelEvents::REQUEST is raised (when the request processing starts) to
the point KernelEvents::RESPONSE is raised (i.e. when the response is ready).

This will give site owners and developers a simple way to see the performance
of the different pages in the website.

You can see the website speed reports from the module at

Admin > Reports > Website Speed

admin/reports/website-speed