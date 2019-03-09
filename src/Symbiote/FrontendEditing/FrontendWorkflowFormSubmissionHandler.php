<?php

namespace Symbiote\FrontendEditing;

use SilverStripe\Forms\FormRequestHandler;
use SilverStripe\Control\HTTPRequest;


class FrontendWorkflowFormSubmissionHandler extends FormRequestHandler
{
    private static $allowed_actions = array(
        'handleField',
        'httpSubmission',
        'forTemplate',
    );

    public function httpSubmission($request)
    {
        $vars = $request->postVars();

        $newVars = [];

        foreach ($vars as $name => $val) {
            if (strpos($name, 'action_startworkflow') !== false) {
                $workflowDefId = str_replace('action_startworkflow-', '', $name);
                $newVars['start_workflow'] = $workflowDefId;
            } else if (strpos($name, 'action_transition') !== false) {
                $transition = str_replace('action_startworkflow_', '', $name);
                $newVars['workflow_transition'] = $transition;
            } else {
                $newVars[$name] = $val;
            }
        }

        $newRequest = $this->recreateRequest($request, $newVars);
        return parent::httpSubmission($newRequest);
    }

    /**
     * @return HTTPRequest
     */
    protected function recreateRequest(HTTPRequest $oldRequest, $newPost = null, $newGet = null)
    {
        $get = $newGet ? $newGet : $oldRequest->getVars();
        $post = $newPost ? $newPost : $oldRequest->postVars();

        $request = new HTTPRequest(
            $oldRequest->httpMethod(),
            $oldRequest->getURL(),
            $get,
            $post,
            $oldRequest->getBody()
        );

        $request->setScheme($oldRequest->getScheme());
        $request->setIP($oldRequest->getIP());

        foreach ($oldRequest->getHeaders() as $header => $value) {
            $request->addHeader($header, $value);
        }

        $request->setSession($oldRequest->getSession());
        return $request;
    }
}
