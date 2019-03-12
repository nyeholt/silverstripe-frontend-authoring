<?php

namespace Symbiote\FrontendEditing;

use SilverStripe\Core\Extension;



class FrontendObjectsAuthoringExtension extends Extension
{
    public function updateObjectCreatorKeywords($object, $keywords)
    {
        $keywords['$EditLink'] = $object->Link('edit');
    }
}
