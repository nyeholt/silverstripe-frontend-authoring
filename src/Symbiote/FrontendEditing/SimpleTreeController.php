<?php

namespace Symbiote\FrontendEditing;

use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Convert;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Assets\File;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\Assets\Image;


/**
 * Controller that handles requests for data to manage the tree
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class SimpleTreeController extends Controller
{

    private static $allowed_actions = array(
        'childnodes',
    );

    private static $type_map = [
        'page' => SiteTree::class,
        'file' => File::class,
    ];

    /**
     * Request nodes from the server
     *
     * @param SS_HTTPRequest $request
     * @return JSONString
     */
    public function childnodes($request)
    {
        $data = array();

        $searchType = 'page';
        if ($request->param('ID')) {
            $searchType = $request->param('ID');
        }

        $rootObjectType = $this->config()->type_map[$searchType];
        if ($request->getVar('search')) {
            return $this->performSearch($request->getVar('search'), $rootObjectType);
        }

        $parentId = (int) $request->getVar('id');
        if (!$parentId || $parentId == '#') {
            $parentId = 0;
        }

        $selectable = null;

        if ($request->param('OtherID')) {
            $selectable = explode(',', $request->param('OtherID'));
        }

        $type = $rootObjectType;
        $id = $parentId;

        if (!$type || $id < 0) {
            $data = array(0 => array('data' => 'An error has occurred'));
        } else {
            $children = null;
            if ($id === 0) {
                $children = DataObject::get($rootObjectType)->filter('ParentID', 0);
            } else {
                $object = DataObject::get_by_id($type, $id);
                $children = $this->childrenOfNode($object);
            }

            $data = array();
            if ($children && count($children)) {
                foreach ($children as $child) {
                    if ($child->ID < 0) {
                        continue;
                    }

                    $haskids = $child->numChildren() > 0;
                    $nodeData = [
                        'text' => isset($child->MenuTitle) ? $child->MenuTitle : $child->Title,
                        'id' => $child->ID,
                    ];
                    if ($selectable && !in_array($child->ClassName, $selectable)) {
                        $nodeData['clickable'] = false;
                    }

                    $thumbs = null;
                    if ($child->ClassName == Image::class) {
                        $thumbs = $this->generateThumbnails($child);
                        $nodeData['icon'] = $thumbs['x32'];
                    } else if (!$haskids) {
                        // $nodeData['icon'] = ModuleResourceLoader::singleton()->resolvePath('symbiote/silverstripe-frontend-authoring: client/images/page.png');
                        $nodeData['icon'] = 'resources/symbiote/silverstripe-frontend-authoring/client/images/page.png';
                    }

                    $nodeData['children'] = $haskids;

                    $nodeData['data'] = [
                        'link' => $child instanceof File ? $child->getURL() :  $child->RelativeLink()
                    ];

                    // $nodeEntry = array(
                    //     'attributes' => array('id' => $child->ClassName . '-' . $child->ID, 'text' => Convert::raw2att($nodeData['text']), 'link' => $child->RelativeLink()),
                    //     'data' => $nodeData,
                    //     'state' => $haskids ? 'closed' : 'open'
                    // );

                    // if ($thumbs) {
                    //     $nodeEntry['thumbs'] = $thumbs;
                    // }

                    $data[] = $nodeData;
                }
            }
        }

        $this->getResponse()->addHeader('Content-Type', 'application/json');
        return Convert::raw2json($data);
    }

    /**
     * Called to generate thumbnails before sending the image data back
     *
     * @param Image $image
     */
    protected function generateThumbnails(Image $image)
    {
        $thumbs = array();
        $thumbs['x16'] = $image->Fit(16, 16)->Link();
        $thumbs['x32'] = $image->Fit(32, 32)->Link();
        $thumbs['x128'] = $image->Fit(128, 128)->Link();
        return $thumbs;
    }

    /**
     * Method to work around bug where Hierarchy.php directly refers to
     * ShowInMenus, which is only available on SiteTree
     *
     * @param DataObject $node
     * @return DataList
     */
    protected function childrenOfNode($node)
    {
        $result = $node->stageChildren(true);
        if (isset($result)) {
            foreach ($result as $child) {
                if (!$child->canView()) {
                    $result->remove($child);
                }
            }
        }

        return $result;
    }

    /**
     * Search for a node based on the passed in criteria. The output is a list
     * of nodes that should be opened from the top down
     *
     */
    protected function performSearch($query, $rootObjectType = 'SiteTree')
    {
        $item = null;

        if (preg_match('/\[sitetree_link id=([0-9]+)\]/i', $query, $matches)) {
            $item = DataObject::get_by_id($rootObjectType, $matches[1]);
        } else if (preg_match('/^assets\//', $query)) {
			// search for the file based on its filepath
            $item = DataObject::get_one($rootObjectType, singleton('FEUtils')->dbQuote(array('Filename =' => $query)));
        }

        if ($item && $item->ID) {
            $items = array();
            while ($item->ParentID != 0) {
                array_unshift($items, $rootObjectType . '-' . $item->ID);
                $item = $item->Parent();
            }

            array_unshift($items, $rootObjectType . '-' . $item->ID);
            return implode(',', $items);
        }

        return '';
    }
}
