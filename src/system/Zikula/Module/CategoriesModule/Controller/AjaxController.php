<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

namespace Zikula\Module\CategoriesModule\Controller;

use SecurityUtil;
use CategoryUtil;
use FormUtil;
use ZLanguage;
use Zikula_View;
use Symfony\Component\HttpFoundation\Request;
use Zikula\Core\Response\Ajax\AjaxResponse;
use Zikula\Core\Response\Ajax\NotFoundResponse;
use Zikula\Core\Response\Ajax\ForbiddenResponse;
use Zikula\Core\Response\Ajax\BadDataResponse;
use Zikula\Module\CategoriesModule\GenericUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route; // used in annotations - do not remove
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method; // used in annotations - do not remove
use Symfony\Component\Routing\RouterInterface;

/**
 * @Route("/ajax")
 *
 * Ajax controllers for the categories module
 */
class AjaxController extends \Zikula_Controller_AbstractAjax
{
    /**
     * @Route("/resequence", options={"expose"=true})
     * @Method("POST")
     * 
     * Resequence categories
     * 
     * @param Request $request
     *
     * @return AjaxResponse|ForbiddenResponse
     */
    public function resequenceAction(Request $request)
    {
        $this->checkAjaxToken();
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', ACCESS_EDIT)) {
            return new ForbiddenResponse($this->__('No permission for this action'));
        }

        $data  = json_decode($request->request->get('data'), true);
        $cats = CategoryUtil::getSubCategories(1, true, true, true, true, true, '', 'id');

        foreach ($cats as $k => $cat) {
            $cid = $cat['id'];
            if (isset($data[$cid])) {
                $category = $this->entityManager->find('ZikulaCategoriesModule:CategoryEntity', $cid);
                $category['sort_value'] = $data[$cid]['lineno'];
                $category['parent'] = $this->entityManager->getReference('ZikulaCategoriesModule:CategoryEntity', $data[$cid]['parent']);
            }
        }

        $this->entityManager->flush();

        $result = array(
            'response' => true
        );

        return new AjaxResponse($result);
    }

    /**
     * @Route("/edit", options={"expose"=true})
     * @Method("POST")
     *
     * Edit a category
     *
     *      string $mode   the mode of operation (new or edit)
     *      int    $cid    the category id
     *      int    $parent the parent category id
     *                       }
     *
     * @param Request $request
     *
     * @return AjaxResponse|NotFoundResponse ajax response object
     */
    public function editAction(Request $request)
    {
        $this->checkAjaxToken();

        $mode = $request->request->get('mode', 'new');
        $accessLevel = $mode == 'edit' ? ACCESS_EDIT : ACCESS_ADD;
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', $accessLevel)) {
            return new ForbiddenResponse($this->__('No permission for this action'));
        }

        $cid = $request->request->get('cid', 0);
        $parent = $request->request->get('parent', 1);
        $validationErrors = FormUtil::getValidationErrors();
        $editCat = '';

        $languages = ZLanguage::getInstalledLanguages();

        // indicates that we're editing
        if ($mode == 'edit') {
            if (!$cid) {

                return new BadDataResponse($this->__('Error! Cannot determine valid \'cid\' for edit mode in \'Categories_admin_edit\'.'));
            }
            $editCat = CategoryUtil::getCategoryByID($cid);
            if (!$editCat) {
                return new NotFoundResponse($this->__('Sorry! No such item found.'));
            }
        } else {
            // someone just pressed 'new' -> populate defaults
            $editCat['sort_value'] = '0';
            $editCat['parent_id'] = $parent;
        }

        $attributes = isset($editCat['__ATTRIBUTES__']) ? $editCat['__ATTRIBUTES__'] : array();

        $this->setView();
        $this->view->setCaching(Zikula_View::CACHE_DISABLED);

        $this->view->assign('mode', $mode)
                   ->assign('category', $editCat)
                   ->assign('attributes', $attributes)
                   ->assign('languages', $languages);

        $result = array(
            'action' => $mode == 'new' ? 'add' : 'edit',
            'result' => $this->view->fetch('Ajax/edit.tpl'),
            'validationErrors' => $validationErrors
        );

        if ($validationErrors) {
            return new BadDataResponse($validationErrors, $result);
        }

        return new AjaxResponse($result);
    }

    /**
     * @Route("/copy", options={"expose"=true})
     * @Method("POST")
     *
     * Copy a category
     *
     * @param Request $request
     *
     * @return AjaxResponse ajax response object
     */
    public function copyAction(Request $request)
    {
        $this->checkAjaxToken();
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', ACCESS_ADD)) {
            return new ForbiddenResponse($this->__('No permission for this action'));
        }

        $cid = $request->request->get('cid');
        $parent = $request->request->get('parent');

        $cat = CategoryUtil::getCategoryByID($cid);
        CategoryUtil::copyCategoriesByPath($cat['ipath'], $parent);

        $copyParent = CategoryUtil::getCategoryByID($cat['parent_id']);

        $categories = CategoryUtil::getSubCategories($copyParent['id'], true, true, true, true, true);
        $options = array(
            'nullParent' => $copyParent['parent_id'],
            'withWraper' => false,
        );

        $node = CategoryUtil::getCategoryTreeJS((array)$categories, true, true, $options);

        $leafStatus = array(
            'leaf' => array(),
            'noleaf' => array()
        );
        foreach ($categories as $c) {
            if ($c['is_leaf']) {
                $leafStatus['leaf'][] = $c['id'];
            } else {
                $leafStatus['noleaf'][] = $c['id'];
            }
        }
        $result = array(
            'action' => 'copy',
            'cid' => $cid,
            'copycid' => $copyParent['id'],
            'parent' => $copyParent['parent_id'],
            'node' => $node,
            'leafstatus' => $leafStatus,
            'result' => true
        );

        return new AjaxResponse($result);
    }

    /**
     * @Route("/delete", options={"expose"=true})
     * @Method("POST")
     *
     * Delete a category
     *
     * @param Request $request
     *
     * @return AjaxResponse ajax response object
     */
    public function deleteAction(Request $request)
    {
        $this->checkAjaxToken();
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', ACCESS_DELETE)) {
            return new ForbiddenResponse($this->__('No permission for this action'));
        }

        $cid = $request->request->get('cid');
        $cat = CategoryUtil::getCategoryByID($cid);

        CategoryUtil::deleteCategoriesByPath($cat['ipath']);

        $result = array(
            'action' => 'delete',
            'cid' => $cid,
            'result' => true
        );

        return new AjaxResponse($result);
    }

    /**
     * @Route("/deleteandmove", options={"expose"=true})
     * @Method("POST")
     *
     * Delete a category and move any existing subcategories
     *
     * @param Request $request
     *
     * @return AjaxResponse ajax response object
     */
    public function deleteandmovesubsAction(Request $request)
    {
        $this->checkAjaxToken();
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', ACCESS_DELETE)) {
            return new ForbiddenResponse($this->__('No permission for this action'));
        }

        $cid = $request->request->get('cid');
        $parent = $request->request->get('parent');

        $cat = CategoryUtil::getCategoryByID($cid);

        CategoryUtil::moveSubCategoriesByPath($cat['ipath'], $parent);
        CategoryUtil::deleteCategoryByID($cat['id']);

        // need to re-render new parents node
        $newParent = CategoryUtil::getCategoryByID($parent);

        $categories = CategoryUtil::getSubCategories($newParent['id'], true, true, true, true, true);
        $options = array(
            'nullParent' => $newParent['parent_id'],
            'withWraper' => false,
        );
        $node = CategoryUtil::getCategoryTreeJS((array)$categories, true, true, $options);

        $leafStatus = array(
            'leaf' => array(),
            'noleaf' => array()
        );
        foreach ($categories as $c) {
            if ($c['is_leaf']) {
                $leafStatus['leaf'][] = $c['id'];
            } else {
                $leafStatus['noleaf'][] = $c['id'];
            }
        }

        $result = array(
            'action' => 'deleteandmovesubs',
            'cid' => $cid,
            'parent' => $newParent['id'],
            'node' => $node,
            'leafstatus' => $leafStatus,
            'result' => true
        );

        return new AjaxResponse($result);
    }

    /**
     * @Route("/deletedialog", options={"expose"=true})
     * @Method("POST")
     *
     * Display a dialog to get the category to move subcategories to once the parent has been deleted
     *
     * @param Request $request
     *
     * @return AjaxResponse ajax response object
     */
    public function deletedialogAction(Request $request)
    {
        $this->checkAjaxToken();
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', ACCESS_DELETE)) {
            return new ForbiddenResponse($this->__('No permission for this action'));
        }

        $cid = $request->request->get('cid');

        $allCats = CategoryUtil::getSubCategories(1, true, true, true, false, true, $cid);
        $selector = CategoryUtil::getSelector_Categories($allCats);

        $this->setView();
        $this->view->setCaching(\Zikula_View::CACHE_DISABLED);

        $this->view->assign('categorySelector', $selector);
        $result = array(
            'result' => $this->view->fetch('Ajax/delete.tpl'),
        );

        return new AjaxResponse($result);
    }

    /**
     * @Route("/activate", options={"expose"=true})
     * @Method("POST")
     *
     * Activate a category
     *
     * @param Request $request
     *
     * @return AjaxResponse ajax response object
     */
    public function activateAction(Request $request)
    {
        $this->checkAjaxToken();
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', ACCESS_EDIT)) {
            return new ForbiddenResponse($this->__('No permission for this action'));
        }

        $cid = $request->request->get('cid');
        $cat = $this->entityManager->find('ZikulaCategoriesModule:CategoryRegistryEntity', $cid);
        $cat['status'] = 'A';
        $this->entityManager->flush();

        $result = array(
            'action' => 'activate',
            'cid' => $cid,
            'result' => true
        );

        return new AjaxResponse($result);
    }

    /**
     * @Route("/deactivate", options={"expose"=true})
     * @Method("POST")
     *
     * Deactivate a category
     *
     * @param Request $request
     *
     * @return AjaxResponse ajax response object
     */
    public function deactivateAction(Request $request)
    {
        $this->checkAjaxToken();
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', ACCESS_EDIT)) {
            return new ForbiddenResponse($this->__('No permission for this action'));
        }

        $cid = $request->request->get('cid');
        $cat = $this->entityManager->find('ZikulaCategoriesModule:CategoryRegistryEntity', $cid);
        $cat['status'] = 'I';
        $this->entityManager->flush();

        $result = array(
            'action' => 'deactivate',
            'cid' => $cid,
            'result' => true
        );

        return new AjaxResponse($result);
    }

    /**
     * @Route("/save", options={"expose"=true})
     * @Method("POST")
     *
     * Save a category
     *
     * @param Request $request
     *
     * @return AjaxResponse ajax response object
     */
    public function saveAction(Request $request)
    {
        $this->checkAjaxToken();

        $mode = $request->request->get('mode', 'new');
        $accessLevel = $mode == 'edit' ? ACCESS_EDIT : ACCESS_ADD;
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', $accessLevel)) {
            return new ForbiddenResponse($this->__('No permission for this action'));
        }

        // get data from post
        $data = $request->request->get('category', null);

        if (!isset($data['is_locked'])) {
            $data['is_locked'] = 0;
        }
        if (!isset($data['is_leaf'])) {
            $data['is_leaf'] = 0;
        }
        if (!isset($data['status'])) {
            $data['status'] = 'I';
        }

        $valid = GenericUtil::validateCategoryData($data);
        if (!$valid) {
            $args = array(
                'cid' => (isset($data['cid']) ? $data['cid'] : 0),
                'parent' => $data['parent_id'],
                'mode' => $mode
            );

            return $this->editAction($args);
        }

        // process name
        $data['name'] = GenericUtil::processCategoryName($data['name']);

        // process parent
        $data['parent'] = GenericUtil::processCategoryParent($data['parent_id']);
        unset($data['parent_id']);

        // process display names
        $data['display_name'] = GenericUtil::processCategoryDisplayName($data['display_name'], $data['name']);

        // save category
        if ($mode == 'edit') {
            $category = $this->entityManager->find('ZikulaCategoriesModule:CategoryEntity', $data['id']);
        } else {
            $category = new \Zikula\Module\CategoriesModule\Entity\CategoryEntity;
        }
        $prevCategoryName = $category['name'];
        $category->merge($data);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        // process path and ipath
        $category['path'] = GenericUtil::processCategoryPath($data['parent']['path'], $category['name']);
        $category['ipath'] = GenericUtil::processCategoryIPath($data['parent']['ipath'], $category['id']);

        // process category attributes
        $attrib_names = $request->request->get('attribute_name', array());
        $attrib_values = $request->request->get('attribute_value', array());
        GenericUtil::processCategoryAttributes($category, $attrib_names, $attrib_values);

        $this->entityManager->flush();

        // since a name change will change the object path, we must rebuild it here
        if ($prevCategoryName != $category['name']) {
            CategoryUtil::rebuildPaths('path', 'name', $category['id']);
        }

        $categories = CategoryUtil::getSubCategories($category['id'], true, true, true, true, true);
        $options = array(
            'nullParent' => $category['parent']->getId(),
            'withWraper' => false,
        );
        $node = CategoryUtil::getCategoryTreeJS((array)$categories, true, true, $options);

        $leafStatus = array(
            'leaf' => array(),
            'noleaf' => array()
        );
        foreach ($categories as $c) {
            if ($c['is_leaf']) {
                $leafStatus['leaf'][] = $c['id'];
            } else {
                $leafStatus['noleaf'][] = $c['id'];
            }
        }

        $result = array(
            'action' => $mode == 'edit' ? 'edit' : 'add',
            'cid' => $category['id'],
            'parent' => $category['parent']->getId(),
            'node' => $node,
            'leafstatus' => $leafStatus,
            'result' => true
        );

        return new AjaxResponse($result);
    }
}
