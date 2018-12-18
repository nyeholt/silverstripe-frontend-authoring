# Frontend Editing


[![Build Status](https://travis-ci.org/symbiote/silverstripe-frontend-editing.svg?branch=master)](https://travis-ci.org/symbiote/silverstripe-frontend-editing)
[![Latest Stable Version](https://poser.pugx.org/symbiote/silverstripe-frontend-editing/version.svg)](https://github.com/symbiote/silverstripe-frontend-editing/releases)
[![Latest Unstable Version](https://poser.pugx.org/symbiote/silverstripe-frontend-editing/v/unstable.svg)](https://packagist.org/packages/symbiote/silverstripe-frontend-editing)
[![Total Downloads](https://poser.pugx.org/symbiote/silverstripe-frontend-editing/downloads.svg)](https://packagist.org/packages/symbiote/silverstripe-frontend-editing)
[![License](https://poser.pugx.org/symbiote/silverstripe-frontend-editing/license.svg)](https://github.com/symbiote/silverstripe-frontend-editing/blob/master/LICENSE.md)

Adds frontend editing capability

![TODO_CHANGE_THIS](docs/images/main.png)

## Composer Install

```
composer require symbiote/silverstripe-newmodule:~1.0
```

## Requirements

* SilverStripe 4.1+

## Documentation

After installing the module, trigger frontend editing by appending `/edit` to the current URL. 

In your page class, ensure you have a `getFrontEndFields` method declared that returns
fields appropriate for editing your content. 

When editing, you can use the following shortcuts;

* Page creation - enter `[Page Title](my-custom-slug)`, or simplified as `[Page Title]()` for the slug to be generated

