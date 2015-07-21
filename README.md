# minify

## Maintainers

 * Andre Lohmann (Nickname: andrelohmann)
  <lohmann dot andre at googlemail dot com>

## Introduction

minify html and process javascript and css files

To write cached processing to memcached, you can add the following to your _ss_environment.php

```
// Minify Caching Backend
define('MINIFY_CACHE_BACKEND', serialize(array(
    "Type" => "Memcached",
    "Options" => array(
        "servers" => array(
            'host' => 'localhost', 
            'port' => 11211, 
            'persistent' => true, 
            'weight' => 1, 
            'timeout' => 5,
            'retry_interval' => 15, 
            'status' => true, 
            'failure_callback' => ''
        )
    )
)));

define('MINIFY_CACHE_LIFETIME', -1); // Lifetime in seconds, -1 for unlimited
```

### Notice
 * After each Update, set the new Tag
```
git tag -a v1.2.3.4 -m 'Version 1.2.3.4'
git push -u origin v1.2.3.4
```
 * Also update the requirements in andrelohmann/silverstripe-apptemplate