# Website Speed

The Website Speed module provides an easy to use mechanism to check the
speed of your website. It monitors the page generation performance of
the different routes / URLs in the website.

The module keeps track of the time to generate the response and also till
the end of PHP execution on the page. Different reports are provided
through the administration reports section and this can be used to monitor
the performance of the website and take appropriate performance
optimization actions to improve it.

## Installation

To install this module, `composer require` it, or  place it in your modules
folder and enable it on the modules page.

The module supports showing charts in the admin reports. If you want to see
these charts you will need to install the charts module and install a
supported library. The module has been tested with billboard.js library.

Charts module and supporting charts_billboard module has to be installed
first. Follow the instructions in the README for the charts module and the
charts_billboard module to install these modules.

You can install using composer and this requires adding the billboard.js and
D3js repositories to the main composer.json. If your site is not set up with
composer, you can also install these manually like you would install
any other Drupal module or library. Follow the instructions in the README
for the charts_billboard submodule.

Once charts module is installed, you will also have to go to the charts
default configuration page, pick a library and save the default configuration
for charts module. You might also have to save default configuration for the
selected charting library. At the time of writing this, the billboard.js
library does not need any additional configuration.

Although the module has been tested with billboard.js, it might work
very well with any other charting library supported by the charts module. 
If you experience issues, please share in the Drupal issue queue for this
project.

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