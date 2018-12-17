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
        } catch (ValidationException $ve) {
            $form->sessionMessage("Could not upload file: " . $ve->getMessage(), 'bad');
            $this->redirect($this->data()->Link());
            return;
        }

        $object->write();

        return $object;
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
}
