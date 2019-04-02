# Frontend Editing


[![Build Status](https://travis-ci.org/symbiote/silverstripe-frontend-authoring.svg?branch=master)](https://travis-ci.org/nyeholt/silverstripe-frontend-editauthoringing)
[![Latest Stable Version](https://poser.pugx.org/symbiote/silverstripe-frontend-authoring/version.svg)](https://github.com/nyeholt/silverstripe-frontend-authoring/releases)
[![Latest Unstable Version](https://poser.pugx.org/symbiote/silverstripe-frontend-authoring/v/unstable.svg)](https://packagist.org/packages/symbiote/silverstripe-frontend-authoring)
[![Total Downloads](https://poser.pugx.org/symbiote/silverstripe-frontend-authoring/downloads.svg)](https://packagist.org/packages/symbiote/silverstripe-frontend-authoring)
[![License](https://poser.pugx.org/symbiote/silverstripe-frontend-authoring/license.svg)](https://github.com/nyeholt/silverstripe-frontend-authoring/blob/master/LICENSE.md)

Adds frontend editing capability

## Composer Install

```
composer require symbiote/silverstripe-frontend-authoring:~1.0
```

## Requirements

* SilverStripe 4.1+

## Documentation

Enable the module by adding the following config to your project

```
---
Name: authoring_configuration
---
PageController:
  extensions:
    - Symbiote\FrontendEditing\FrontendAuthoringController
```

After enabling the module, trigger frontend editing by appending `/edit?stage=Stage` to the current URL. 

In your page class, ensure you have a `getFrontEndFields` method declared that returns
fields appropriate for editing your content. 

When editing, you can use the following shortcuts;

* Page creation - enter `[Page Title](my-custom-slug)`, or simplified as 
  `[Page Title]()` for the slug to be generated

### Configuration

You can set the following properties

* page_create_types: The type of the page to create when adding a page using the []() syntax. 
  The key is the 'current' page, the value the page type to create
* page_create_parent_field: The field to use of the 'current' page for newly created pages'
  "parent". Defaults to ID

```
MyController:
  page_create_types:
    Symbiote\Page\NewsHolder: Symbiote\Page\NewsPage
  page_create_parent_field:
    Symbiote\Page\MyPage: ParentID
```

If your editing save process requires a page reload after saving (say, you modify the content
via the page edit) then output the X-Authoring-Reload header with a value of 1

```
Controller::has_curr() ? Controller::curr()->getResponse()->addHeader('X-Authoring-Reload', 1) : false;    
```
