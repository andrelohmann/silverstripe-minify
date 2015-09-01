# minify

## Maintainers

 * Andre Lohmann (Nickname: andrelohmann)
  <lohmann dot andre at googlemail dot com>

## Requirements

Silverstripe 3.2.x

## Introduction

minify html and process javascript and css files

To write cached processing to memcached, you can add the following to your _ss_environment.php

```
// Minify Caching Backend
define('MINIFY_CACHE_BACKEND', serialize(array(
    "Type" => "Libmemcached",
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
This repository uses the git flow paradigm.
After each release cycle, do not forget to push tags, master and develop to the remote origin
```
git push --tags
git push origin develop
git push origin master
```