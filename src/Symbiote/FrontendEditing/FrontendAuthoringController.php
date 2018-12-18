<?php

namespace Symbiote\FrontendEditing;

use SilverStripe\Core\Extension;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Member;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\Parsers\URLSegmentFilter;


class FrontendAuthoringController extends Extension
{

    private static $allowed_actions = [
        'edit' => 'CMS_ACCESS_CMSMain',
        'AuthoringForm' => 'CMS_ACCESS_CMSMain',
    ];

    public function AuthoringLink()
    {
        $link = $this->owner->Link();

        return Controller::join_links($link, 'edit');
    }

    public function edit()
    {
        if (!($this->owner instanceof Controller)) {
            return;
        }

        $object = $this->owner->data();

        if (!$object->canEdit()) {
            return $this->owner->httpError(403);
        }

        return $this->owner->customise([
            'Form' => $this->AuthoringForm()
        ])->renderWith(['FrontendAuthoringEdit_edit', 'Page']);
    }

    public function AuthoringForm()
    {
        Versioned::set_stage('Stage');

        $object = $this->owner->data();

        $cls = $object->ClassName;
        // reload in the correct stage
        $object = $cls::get()->byID($object->ID);

        $fields = FieldList::create(
            TextField::create('Title', _t('FrontendCreate.TITLE', 'Title'))
        );

        if ($object instanceof Member) {
            $fields = $object->getMemberFormFields();
        } else {
            $fields = $object->getFrontEndFields();
        }

        $fields->push(HiddenField::create('ID', 'ID', $object->ID));

        $action = FormAction::create('saveobject', 'Save Changes');
        $actions = FieldList::create([$action]);

        // and a publish
        if ($object->hasExtension(Versioned::class)) {
            $actions->push(FormAction::create('publishobject', 'Save and Publish'));
        }

        $validator = ($object && $object->hasMethod('getFrontendCreateValidator')) ? $object->getFrontendCreateValidator() : RequiredFields::create(['Title']);

        $form = Form::create($this->owner, 'AuthoringForm', $fields, $actions);

        $this->owner->extend('updateFrontendAuthoringForm', $form);

        if ($object) {
            $form->loadDataFrom($object);
        }

        return $form;
    }

    public function saveobject($data, Form $form, HTTPRequest $req)
    {
        $this->processForm($data, $form, $req);
        return $this->owner->redirect($this->owner->AuthoringLink());
    }

    public function publishobject($data, Form $form, HTTPRequest $req)
    {
        $object = $this->processForm($data, $form, $req);

        if ($object->canPublish()) {
            $object->publishRecursive();
        }
        Versioned::set_stage(Versioned::LIVE);
        return $this->owner->redirect($this->owner->Link());
    }

    protected function processForm($data, Form $form, HTTPRequest $req)
    {
        Versioned::set_stage('Stage');
        $object = $this->owner->data();
        if (!($object instanceof DataObject)) {
            return $this->owner->httpError(400);
        }
        if (!$object->canEdit()) {
            return $this->owner->httpError(403);
        }

        try {
            $form->saveInto($object);
            $this->analyseNewPagesUnder($object);
        } catch (ValidationException $ve) {
            $form->sessionMessage("Could not upload file: " . $ve->getMessage(), 'bad');
            $this->redirect($this->data()->Link());
            return;
        }

        $object->write();

        return $object;
    }

    protected function analyseNewPagesUnder($object) {
        // check changed fields, looking for HTML text fields
        foreach ($object->getChangedFields(true) as $field => $changes) {
            $type = $object->obj($field);
            $cls = $object->ClassName;
            if ($type instanceof DBHTMLText) {
                $content = $object->$field;
                $pageLinks = $this->parseLinksFrom($content);

                $replacements = [];

                foreach ($pageLinks as $slug => $title) {
                    if (strlen($slug) === 0) {
                        $slug = $title;
                    }
                    $slug = URLSegmentFilter::create()->filter($slug);
                    $existing = \Page::get()->filter([
                        'ParentID' => $object->ID,
                        'URLSegment' => $slug,
                    ])->first();
                    if (!$existing) {
                        $existing = $cls::create([
                            'Title' => $title,
                            'URLSegment' => $slug,
                            'ParentID' => $object->ID,
                        ]);
                        $existing->write();
                    }
                    $link = '<a href="[sitetree_link,id=' . $existing->ID . ']">' . $title . '</a>';
                    $replacements["[$title]()"] = $link;
                    $replacements["[$title]($slug)"] = $link;
                }
                $object->$field = str_replace(array_keys($replacements), array_values($replacements), $content);
            }
        }
    }

    protected function parseLinksFrom($content) {
		$pages = array();
		if (preg_match_all('/\[([\w\d\s_.-]+)\]\(([\w\d_-]+)?\)/', $content, $matches)) {
            // exit(print_r($matches));
            $i = 0;
			for ($i = 0, $c = count($matches[1]); $i < $c; $i++) {
                $slug = $matches[2][$i];
                $title = $matches[1][$i];
                if (strlen($slug) === 0) {
                    $slug = $title;
                }
				$pages[$slug] = $title;
			}
		}
		return $pages;
	}
}
