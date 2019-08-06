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
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\Parsers\URLSegmentFilter;
use nglasl\mediawesome\MediaPage;
use SilverStripe\View\Parsers\HTMLValue;
use SilverStripe\Assets\Upload;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Image;
use SilverStripe\View\Requirements;
use Symbiote\AdvancedWorkflow\Extensions\WorkflowApplicable;
use Symbiote\AdvancedWorkflow\Services\WorkflowService;
use SilverStripe\Security\Security;
use SilverStripe\ORM\ArrayList;
use SilverStripe\AssetAdmin\Forms\UploadField;

class FrontendAuthoringController extends Extension
{

    private static $allowed_actions = [
        'edit' => '->canWorkflowOrEdit',
        'runworkflow' => '->canWorkflowOrEdit',
        'AuthoringForm' => 'CMS_ACCESS_CMSMain',
        'WorkflowForm' =>  '->canWorkflowOrEdit',
    ];

    /**
     * What types should child pages be created as, for a given type?
     */
    private static $page_create_types = [];

    /**
     * Where should pages be created? as children or siblings?
     */
    private static $page_create_parent_field = [
        MediaPage::class => 'ParentID',
    ];

    public function AuthoringLink()
    {
        $link = $this->owner->Link();

        return Controller::join_links($link, 'edit');
    }

    public function canWorkflowOrEdit() {
        $object = $this->owner->data();
        $form = null;

        $currentUser = Security::getCurrentUser();

        $workflowed = $object->hasExtension(WorkflowApplicable::class);
        $canEdit = $object->canEdit();

        if ($canEdit) {
            return true;
        }

        if ($workflowed) {
            $instance = $object->getWorkflowInstance();
            $members = $instance ? $instance->getAssignedMembers() : ArrayList::create();
            if ($currentUser && $members && $members->find('ID', $currentUser->ID)) {
                return true;
            }
        }

    }

    public function edit()
    {
        if (!($this->owner instanceof Controller)) {
            return;
        }

        $object = $this->owner->data();
        $form = null;

        $currentUser = Security::getCurrentUser();

        if (!$object->canEdit() && $object->hasExtension(WorkflowApplicable::class)) {
            // if we've got workflow applied, we can still show the workflow form
            // if it's an assigned user
            $instance = $object->getWorkflowInstance();
            $members = $instance ? $instance->getAssignedMembers() : ArrayList::create();
            if ($currentUser && $members && $members->find('ID', $currentUser->ID)) {
                $form = $this->WorkflowForm();
            } else {
                // redirect to the non-edit version of the page.
                return $this->owner->redirect($object->Link());
            }
        } else {
            $form = $this->AuthoringForm();
        }

        return $this->owner->customise([
            'Form' => $form
        ])->renderWith(['FrontendAuthoringEdit_edit', 'Page']);
    }

    public function WorkflowForm()
    {
        $authoring = $this->AuthoringForm();
        $fields = FieldList::create();
        if ($authoring) {
            $fields = $authoring->Fields();
            $fields = $fields->makeReadonly();

            foreach ($fields as $f) {
                if ($f instanceof UploadField) {
                    $fields->remove($f);
                }
            }
        }

        $form = Form::create($this->owner, 'WorkflowForm', $fields, FieldList::create());

        $this->addWorkflowDetail($form, $this->owner->data());

        return $form;
    }

    public function AuthoringForm()
    {
        Versioned::set_stage('Stage');

        Requirements::javascript('symbiote/silverstripe-frontend-authoring: client/script/wretch-1.4.2.min.js');
        Requirements::javascript('symbiote/silverstripe-frontend-authoring: client/script/file-uploads.js');
        Requirements::javascript('symbiote/silverstripe-frontend-authoring: client/script/authoring.js');


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

        $actions = FieldList::create([
            FormAction::create('saveobject', 'Save Changes'),
            FormAction::create('done', 'Close without saving'),
        ]);

        // and a publish
        if ($object && $object->hasExtension(Versioned::class) && $object->canPublish()) {
            $actions->push(FormAction::create('publishobject', 'Save and Publish'));
        }

        $validator = ($object && $object->hasMethod('getFrontendCreateValidator')) ? $object->getFrontendCreateValidator() : RequiredFields::create(['Title']);

        $form = Form::create($this->owner, 'AuthoringForm', $fields, $actions);

        if ($validator) {
            $form->setValidator($validator);
        }

        $this->owner->invokeWithExtensions('updateFrontendAuthoringForm', $form);

        $this->addWorkflowDetail($form, $object);


        if ($object) {
            $form->loadDataFrom($object);
        }

        return $form;
    }

    protected function addWorkflowDetail(Form $form, DataObject $object)
    {

        if (!$object || !$object->hasExtension(WorkflowApplicable::class)) {
            return;
        }

        $definition = $object->getWorkflowService()->getDefinitionFor($object);
        if (!$definition) {
            return;
        }

        $form->setRequestHandler(FrontendWorkflowFormSubmissionHandler::create($form));
        $svc            = Injector::inst()->get(WorkflowService::class);
        $active         = $svc->getWorkflowFor($object);

        if (!$active) {
            // add in the 'start workflow' button
            $definitions = $object->getWorkflowService()->getDefinitionsFor($object);
            if ($definitions) {
                foreach ($definitions as $definition) {
                    if ($definition->getInitialAction() && $object->canEdit()) {
                        $action = FormAction::create(
                            "startworkflow-{$definition->ID}",
                            $definition->InitialActionButtonText ?
                                $definition->InitialActionButtonText : $definition->getInitialAction()->Title
                        )
                            ->addExtraClass('start-workflow')
                            ->setAttribute('data-workflow', $definition->ID)
                            ->addExtraClass('btn-primary');

                        $form->Actions()->push($action);
                    }
                }
            }

            return;
        }

        $wfFields     = $active->getFrontEndWorkflowFields();
        $wfActions    = $active->getFrontEndWorkflowActions();
        $wfValidator  = $active->getFrontEndRequiredFields();

        //Get DataObject for Form (falls back to ContextObject if not defined in WorkflowAction)
        $wfDataObject = $active->getFrontEndDataObject();

        // set any requirements spcific to this contextobject
        $active->setFrontendFormRequirements();

        // hooks for decorators
        $object->extend('updateFrontEndWorkflowFields', $wfFields);
        $object->extend('updateFrontEndWorkflowActions', $wfActions);
        $object->extend('updateFrontEndRequiredFields', $wfValidator);
        $object->extend('updateFrontendFormRequirements');

        $form->addExtraClass("FrontendWorkflowForm");

        foreach ($wfFields as $field) {
            $form->Fields()->push($field);
        }

        foreach ($wfActions as $action) {
            $form->Actions()->push($action);
        }

        if ($wfDataObject) {
            $form->loadDataFrom($wfDataObject);
        }

        return $form;
    }

    public function done()
    {
        return $this->owner->redirect($this->owner->Link());
    }

    /**
     * Runs a workflow action / transition
     */
    public function runworkflow($data, Form $form, HTTPRequest $req)
    {
        if ($form->getName() !== 'WorkflowForm') {
            $this->processForm($data, $form, $req);
        }

        $object = $this->owner->data();
        if (isset($data['start_workflow'])) {
            $defId = $data['start_workflow'];
            $definitions = $object->getWorkflowService()->getDefinitionsFor($object);
            foreach ($definitions as $def) {
                if ($def->ID == $defId) {
                    $svc = Injector::inst()->get(WorkflowService::class);
                    $svc->startWorkflow($object, $defId);
                }
            }
        } else if (isset($data['TransitionID'])) {
            $active = $object->getWorkflowInstance();
            if ($active && $active->canEdit()) {
                // because the frontend workflow fields remap this to avoid field conflicts
                if (isset($data['WorkflowActionInstanceComment'])) {
                    $data['Comment'] = $data['WorkflowActionInstanceComment'];
                }
                $active->updateWorkflow($data);
            }
        }

        // the user may or may not be able to edit again; check before redirecting
        $link = $this->owner->Link();
        if ($this->canWorkflowOrEdit()) {
            $link = $this->owner->AuthoringLink();
        }

        return $this->owner->redirect($link);
    }

    public function saveobject($data, Form $form, HTTPRequest $req)
    {
        $this->processForm($data, $form, $req);
        if ($req->isAjax()) {
            $this->owner->getResponse()->addHeader('Content-Type', 'application/json');
            return json_encode(['success' => 1]);
        } else {
            return $this->owner->redirect($this->owner->AuthoringLink());
        }
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
            $changed = $object->getChangedFields(true);
            $object->write();
            $this->analyseContentUpdates($object, $changed);
            $object->write();
        } catch (ValidationException $ve) {
            $form->sessionMessage("Could not save: " . $ve->getMessage(), 'bad');
            $this->redirect($this->data()->Link());
            return;
        }

        return $object;
    }

    protected function analyseContentUpdates($object, $fields)
    {
        $parentFieldMapping = $this->owner->config()->page_create_parent_field;
        $classMapping = $this->owner->config()->page_create_types;

        // check changed fields, looking for HTML text fields
        foreach ($fields as $field => $changes) {
            $type = $object->obj($field);
            $cls = $object->ClassName;

            // see whether we have a specific parent ID field
            $parentIdField = isset($parentFieldMapping[$cls]) ? $parentFieldMapping[$cls] : 'ID';
            $newClassType = isset($classMapping[$cls]) ? $classMapping[$cls] : $cls;

            $parentId = $object->$parentIdField;

            if ($type instanceof DBHTMLText) {
                $content = $object->$field;

                // now parse any uploaded images out
                $content = $this->parsePastedImages($content, $object);

                $pageLinks = $this->parseLinksFrom($content);

                $replacements = [];

                foreach ($pageLinks as $slug => $title) {
                    if (strlen($slug) === 0) {
                        $slug = $title;
                    }
                    $slug = URLSegmentFilter::create()->filter($slug);
                    $existing = \Page::get()->filter([
                        'ParentID' => $parentId,
                        'URLSegment' => $slug,
                    ])->first();
                    if (!$existing) {
                        $existing = $newClassType::create([
                            'Title' => $title,
                            'URLSegment' => $slug,
                            'ParentID' => $parentId,
                        ]);
                        $existing->write();
                    }
                    $link = '<a href="[sitetree_link,id=' . $existing->ID . ']">' . $title . '</a>';
                    $replacements["[$title]()"] = $link;
                    $replacements["[$title]($slug)"] = $link;
                }
                $content = str_replace(array_keys($replacements), array_values($replacements), $content);

                $object->$field = $content;
            }
        }
    }

    protected function parsePastedImages($content, $context)
    {
        $dom = HTMLValue::create($content);

        foreach ($dom->query('//img') as $el) {
            $raw = $el->getAttribute('src');
            if (substr($raw, 0, strlen('data:image/png;base64,')) === 'data:image/png;base64,') {
                $title = $el->getAttribute('title');
                if (!$title) {
                    $title = 'upload';
                }
                $filename = URLSegmentFilter::create()->filter($title) . '.png'; // 'upload.png' : $this->owner->data()->Title . '-upload.png';

                $base64 = substr($raw, strlen('data:image/png;base64,'));
                $tempFilePath = tempnam(TEMP_PATH, 'png');
                file_put_contents($tempFilePath, base64_decode($base64));
                // create a new file and replace the src.
                //data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAABeEAAAL2CAYAAADGqF0yAAAABHNCSVQICAgIfAhkiAAAIABJREFUeF7s3Q9clfXd

                $image = Image::create();
                $path = $context ? $context->RelativeLink() : 'Uploads';

                // $parent = Folder::find_or_make($path);
                // $image->ParentID = $parent->ID;
                $tempFile = [
                    'error' => '',
                    'size' => strlen($raw),
                    'name' => $filename,
                    'tmp_name' => $tempFilePath
                ];

                $upload = Upload::create();
                $upload->setValidator(Injector::inst()->create(ContentUploadValidator::class));

                $result = $upload->loadIntoFile($tempFile, $image, $path);
                $file = $upload->getFile();
                if ($file && $file->ID) {
                    $el->setAttribute('src', $file->getURL());
                    $el->setAttribute('data-id', $file->ID);
                    // $el->setAttribute('data-shortcode', 'image');
                }
                if (file_exists($tempFilePath)) {
                    @unlink($tempFile);
                }
            }
        }

        $content = $dom->getContent();
        return $content;
    }

    protected function parseLinksFrom($content)
    {
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
