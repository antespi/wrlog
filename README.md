wrlog
=====

PHP library for logging to file (or html output) for debugging propuses. Usefull with Moodle, Wordpress, and PHP CMSs

Installation
============

Wordpress
---------

Copy wrlog.php to your wordpress root directory

Add this lines to wp-load.php file, just before including wp-config.php

	// Write debug log library - START
    date_default_timezone_set('Europe/Madrid');
    if ( file_exists( ABSPATH . 'wrlog.php') ) {
       require_once( ABSPATH . 'wrlog.php' );
    }
    wrlog::$enabled = true;
    wrlog::$path = ABSPATH . 'wp-content/logs';
    wrlog_request();
    // Write debug log library - END

Create a folder 'logs' in wp-content

Use wrlog procedure functions or create wrlog objects


Moodle
------

Copy wrlog.php to your moodle source directory

Add this lines to config.php file, just before including lib/setup.php

    // Write debug log library - START
	require_once(dirname(__FILE__) . '/wrlog.php');
	wrlog::$enabled = true;
	wrlog::$path = $CFG->dataroot . '/logs';
	wrlog_request();
    // Write debug log library - END

Create a folder 'logs' in moodle data directory

Use wrlog procedure functions or create wrlog objects



Use for debugging your code
===========================

Controller or Model
-------------------

In order to debug code in a controller or a model we wouldn't use echo to output, because no output has been sent yet to browser.
So, in this cases, log is the best solution because you are sure that every message is there in the right order.

Log a message : wrlog($msg, $return = false, $out = false)

	wrlog('Hello World!');

Log a variable as a JSON human readable syntax : wrlog_json($msg, $var, $return = false, $out = false)

    wrlog('Flowers : ', $flowers);

Log call stack : wrlog_btrace($trace = null, $return = false, $out = false)

    wrlog_btrace();

View
----

When we are debugging in view, we have already sent output to browser.
So, if we want to show a preformatted message we have to use wrout functions family

Show a preformatted message : wrout($msg)

	wrout('Hello world!');

Show a variable as a JSON human readable syntax : wrout_json($msg, $var)

    wrout('Flowers : ', $flowers);

Show call stack : wrout_btrace($trace = null)

    wrout_btrace();



Use for profiling your code
===========================

TODO


Understanding log line prefix
=============================

Sample : `2013/10/31-11:53:56(0.006)[192.168.122.1/5093]: Hello world!`

1.  **Date** - `2013/10/31-11:53:56` : Date and time of log line
2.  **Time from timestart** - `(0.006)` : Number of seconds (and milliseconds) from timestart.
	* Timestart is reset when wrlog object is created or when wrlog->reset() is called.
    * Usefull for profiling execution time of some parts of the code
3.  **IP and PID** - `[192.168.122.1/5093]` : Remote IP address and PID of process executing this request.
    * Usefull when you have several request in parallel, for example in production instances
4.  **Log message** - `Hello world!` : Message to log. You can be creative to log usefull messages
