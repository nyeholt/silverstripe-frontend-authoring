<?php

namespace Symbiote\FrontendEditing;

use SilverStripe\Forms\FormRequestHandler;
use SilverStripe\Control\HTTPRequest;


class FrontendWorkflowFormSubmissionHandler extends FormRequestHandler
{
    private static $allowed_actions = array(
        'httpSubmission',
    );

    public function httpSubmission($request)
    {
        $vars = $request->postVars();

        $newVars = [];

        $workflowDefId = 0;
        $transitionId = 0;

        foreach ($vars as $name => $val) {
            if (strpos($name, 'action_startworkflow') !== false) {
                $workflowDefId = str_replace('action_startworkflow-', '', $name);
                $newVars['start_workflow'] = $workflowDefId;
                $newVars['action_runworkflow'] = 1;
            } else if (strpos($name, 'action_transition') !== false) {
                $transitionId = str_replace('action_transition_', '', $name);
                $newVars['TransitionID'] = $transitionId;
                $newVars['action_runworkflow'] = 1;
            } else {
                $newVars[$name] = $val;
            }
        }

        $newRequest = $this->recreateRequest($request, $newVars);
        $result = parent::httpSubmission($newRequest);

        return $result;
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
