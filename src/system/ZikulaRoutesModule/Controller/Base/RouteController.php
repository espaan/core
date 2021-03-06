<?php
/**
 * Routes.
 *
 * @copyright Zikula contributors (Zikula)
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @author Zikula contributors <support@zikula.org>.
 * @link http://www.zikula.org
 * @link http://zikula.org
 * @version Generated by ModuleStudio 0.7.0 (http://modulestudio.de).
 */

namespace Zikula\RoutesModule\Controller\Base;

use Zikula\RoutesModule\Entity\RouteEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use FormUtil;
use JCSSUtil;
use ModUtil;
use SecurityUtil;
use System;
use UserUtil;
use Zikula_AbstractController;
use Zikula_View;
use ZLanguage;
use Zikula\Core\Hook\ProcessHook;
use Zikula\Core\Hook\ValidationHook;
use Zikula\Core\Hook\ValidationProviders;
use Zikula\Core\ModUrl;
use Zikula\Core\RouteUrl;
use Zikula\Core\Response\PlainResponse;

/**
 * Route controller base class.
 */
class RouteController extends Zikula_AbstractController
{
    /**
     * Post initialise.
     *
     * Run after construction.
     *
     * @return void
     */
    protected function postInitialize()
    {
        // Set caching to false by default.
        $this->view->setCaching(Zikula_View::CACHE_DISABLED);
    }

    /**
     * This method is the default function handling the main area called without defining arguments.
     * @Cache(expires="+7 days", public=true)
     *
     * @param Request  $request      Current request instance
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     */
    public function indexAction(Request $request)
    {
        $legacyControllerType = $request->query->filter('lct', 'user', FILTER_SANITIZE_STRING);
        System::queryStringSetVar('type', $legacyControllerType);
        $request->query->set('type', $legacyControllerType);
    
        $controllerHelper = $this->serviceManager->get('zikularoutesmodule.controller_helper');
        
        // parameter specifying which type of objects we are treating
        $objectType = 'route';
        $utilArgs = array('controller' => 'route', 'action' => 'main');
        $permLevel = $legacyControllerType == 'admin' ? ACCESS_ADMIN : ACCESS_OVERVIEW;
        if (!SecurityUtil::checkPermission($this->name . ':' . ucfirst($objectType) . ':', '::', $permLevel)) {
            throw new AccessDeniedException();
        }
        
        if ($legacyControllerType == 'admin') {
            
            $redirectUrl = $this->serviceManager->get('router')->generate('zikularoutesmodule_route_view', array('lct' => $legacyControllerType));
            
            return new RedirectResponse(System::normalizeUrl($redirectUrl));
        }
        
        // set caching id
        $this->view->setCacheId('route_index');
        
        // return index template
        return $this->response($this->view->fetch('Route/index.tpl'));
    }
    
    /**
     * This method provides a item list overview.
     * @Cache(expires="+2 hours", public=false)
     *
     * @param Request  $request      Current request instance
     * @param string  $sort         Sorting field.
     * @param string  $sortdir      Sorting direction.
     * @param int     $pos          Current pager position.
     * @param int     $num          Amount of entries to display.
     * @param string  $tpl          Name of alternative template (to be used instead of the default template).
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     */
    public function viewAction(Request $request, $sort, $sortdir, $pos, $num)
    {
        $legacyControllerType = $request->query->filter('lct', 'user', FILTER_SANITIZE_STRING);
        System::queryStringSetVar('type', $legacyControllerType);
        $request->query->set('type', $legacyControllerType);
    
        $controllerHelper = $this->serviceManager->get('zikularoutesmodule.controller_helper');
        
        // parameter specifying which type of objects we are treating
        $objectType = 'route';
        $utilArgs = array('controller' => 'route', 'action' => 'view');
        $permLevel = $legacyControllerType == 'admin' ? ACCESS_ADMIN : ACCESS_READ;
        if (!SecurityUtil::checkPermission($this->name . ':' . ucfirst($objectType) . ':', '::', $permLevel)) {
            throw new AccessDeniedException();
        }
        $repository = $this->serviceManager->get('zikularoutesmodule.' . $objectType . '_factory')->getRepository();
        $repository->setRequest($this->request);
        $viewHelper = $this->serviceManager->get('zikularoutesmodule.view_helper');
        
        // parameter for used sorting field
        if (empty($sort) || !in_array($sort, $repository->getAllowedSortingFields())) {
            $sort = $repository->getDefaultSortingField();
        }
        
        // parameter for used sort order
        $sortdir = strtolower($sortdir);
        
        // convenience vars to make code clearer
        $currentUrlArgs = array();
        
        $where = '';
        
        $selectionArgs = array(
            'ot' => $objectType,
            'where' => $where,
            'orderBy' => $sort . ' ' . $sortdir
        );
        
        $showOwnEntries = (int) $request->query->filter('own', $this->getVar('showOnlyOwnEntries', 0), false, FILTER_VALIDATE_INT);
        $showAllEntries = (int) $request->query->filter('all', 0, false, FILTER_VALIDATE_INT);
        
        if (!$showAllEntries) {
            $csv = $request->getRequestFormat() == 'csv' ? 1 : 0;
            if ($csv == 1) {
                $showAllEntries = 1;
            }
        }
        
        $this->view->assign('showOwnEntries', $showOwnEntries)
                   ->assign('showAllEntries', $showAllEntries);
        if ($showOwnEntries == 1) {
            $currentUrlArgs['own'] = 1;
        }
        if ($showAllEntries == 1) {
            $currentUrlArgs['all'] = 1;
        }
        
        // prepare access level for cache id
        $accessLevel = ACCESS_READ;
        $component = 'ZikulaRoutesModule:' . ucfirst($objectType) . ':';
        $instance = '::';
        if (SecurityUtil::checkPermission($component, $instance, ACCESS_COMMENT)) {
            $accessLevel = ACCESS_COMMENT;
        }
        if (SecurityUtil::checkPermission($component, $instance, ACCESS_EDIT)) {
            $accessLevel = ACCESS_EDIT;
        }
        
        $templateFile = $viewHelper->getViewTemplate($this->view, $objectType, 'view', $request);
        $cacheId = $objectType . '_view|_sort_' . $sort . '_' . $sortdir;
        $resultsPerPage = 0;
        if ($showAllEntries == 1) {
            // set cache id
            $this->view->setCacheId($cacheId . '_all_1_own_' . $showOwnEntries . '_' . $accessLevel);
        
            // if page is cached return cached content
            if ($this->view->is_cached($templateFile)) {
                return $viewHelper->processTemplate($this->view, $objectType, 'view', $request, $templateFile);
            }
        
            // retrieve item list without pagination
            $entities = ModUtil::apiFunc($this->name, 'selection', 'getEntities', $selectionArgs);
        } else {
            // the current offset which is used to calculate the pagination
            $currentPage = $pos;
        
            // the number of items displayed on a page for pagination
            $resultsPerPage = $num;
            if ($resultsPerPage == 0) {
                $resultsPerPage = $this->getVar('pageSize', 10);
            }
        
            // set cache id
            $this->view->setCacheId($cacheId . '_amount_' . $resultsPerPage . '_page_' . $currentPage . '_own_' . $showOwnEntries . '_' . $accessLevel);
        
            // if page is cached return cached content
            if ($this->view->is_cached($templateFile)) {
                return $viewHelper->processTemplate($this->view, $objectType, 'view', $request, $templateFile);
            }
        
            // retrieve item list with pagination
            $selectionArgs['currentPage'] = $currentPage;
            $selectionArgs['resultsPerPage'] = $resultsPerPage;
            list($entities, $objectCount) = ModUtil::apiFunc($this->name, 'selection', 'getEntitiesPaginated', $selectionArgs);
        
            $this->view->assign('currentPage', $currentPage)
                       ->assign('pager', array('numitems'     => $objectCount,
                                               'itemsperpage' => $resultsPerPage));
        }
        
        foreach ($entities as $k => $entity) {
            $entity->initWorkflow();
        }
        
        // build ModUrl instance for display hooks
        $currentUrlObject = new ModUrl($this->name, 'route', 'view', ZLanguage::getLanguageCode(), $currentUrlArgs);
        
        // assign the object data, sorting information and details for creating the pager
        $this->view->assign('items', $entities)
                   ->assign('sort', $sort)
                   ->assign('sdir', $sortdir)
                   ->assign('pageSize', $resultsPerPage)
                   ->assign('currentUrlObject', $currentUrlObject)
                   ->assign($repository->getAdditionalTemplateParameters('controllerAction', $utilArgs));
        
        $modelHelper = $this->serviceManager->get('zikularoutesmodule.model_helper');
        $this->view->assign('canBeCreated', $modelHelper->canBeCreated($objectType));
        
        // fetch and return the appropriate template
        return $viewHelper->processTemplate($this->view, $objectType, 'view', $request, $templateFile);
    }
    
    /**
     * This method provides a handling of edit requests.
     * @Cache(lastModified="route.getUpdatedDate()", ETag="'Route' ~ route.getid() ~ route.getUpdatedDate().format('U')")
     *
     * @param Request  $request      Current request instance
     * @param string  $tpl          Name of alternative template (to be used instead of the default template).
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     * @throws NotFoundHttpException Thrown by form handler if item to be edited isn't found.
     * @throws RuntimeException      Thrown if another critical error occurs (e.g. workflow actions not available).
     */
    public function editAction(Request $request)
    {
        $legacyControllerType = $request->query->filter('lct', 'user', FILTER_SANITIZE_STRING);
        System::queryStringSetVar('type', $legacyControllerType);
        $request->query->set('type', $legacyControllerType);
    
        $controllerHelper = $this->serviceManager->get('zikularoutesmodule.controller_helper');
        
        // parameter specifying which type of objects we are treating
        $objectType = 'route';
        $utilArgs = array('controller' => 'route', 'action' => 'edit');
        $permLevel = $legacyControllerType == 'admin' ? ACCESS_ADMIN : ACCESS_EDIT;
        if (!SecurityUtil::checkPermission($this->name . ':' . ucfirst($objectType) . ':', '::', $permLevel)) {
            throw new AccessDeniedException();
        }
        
        // create new Form reference
        $view = FormUtil::newForm($this->name, $this);
        
        // build form handler class name
        $handlerClass = '\\Zikula\\RoutesModule\\Form\\Handler\\Route\\EditHandler';
        
        // determine the output template
        $viewHelper = $this->serviceManager->get('zikularoutesmodule.view_helper');
        $template = $viewHelper->getViewTemplate($this->view, $objectType, 'edit', $request);
        
        // execute form using supplied template and page event handler
        return $this->response($view->execute($template, new $handlerClass()));
    }
    
    /**
     * This method provides a item detail view.
     * @ParamConverter("route", class="ZikulaRoutesModule:RouteEntity", options={"id" = "id", "repository_method" = "selectById"})
     * @Cache(lastModified="route.getUpdatedDate()", ETag="'Route' ~ route.getid() ~ route.getUpdatedDate().format('U')")
     *
     * @param Request  $request      Current request instance
     * @param RouteEntity $route      Treated route instance.
     * @param string  $tpl          Name of alternative template (to be used instead of the default template).
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     * @throws NotFoundHttpException Thrown by param converter if item to be displayed isn't found.
     */
    public function displayAction(Request $request, RouteEntity $route)
    {
        $legacyControllerType = $request->query->filter('lct', 'user', FILTER_SANITIZE_STRING);
        System::queryStringSetVar('type', $legacyControllerType);
        $request->query->set('type', $legacyControllerType);
    
        $controllerHelper = $this->serviceManager->get('zikularoutesmodule.controller_helper');
        
        // parameter specifying which type of objects we are treating
        $objectType = 'route';
        $utilArgs = array('controller' => 'route', 'action' => 'display');
        $permLevel = $legacyControllerType == 'admin' ? ACCESS_ADMIN : ACCESS_READ;
        if (!SecurityUtil::checkPermission($this->name . ':' . ucfirst($objectType) . ':', '::', $permLevel)) {
            throw new AccessDeniedException();
        }
        $repository = $this->serviceManager->get('zikularoutesmodule.' . $objectType . '_factory')->getRepository();
        
        $entity = $route;
        
        $entity->initWorkflow();
        
        // build ModUrl instance for display hooks; also create identifier for permission check
        $currentUrlArgs = $entity->createUrlArgs();
        $instanceId = $entity->createCompositeIdentifier();
        $currentUrlArgs['id'] = $instanceId; // TODO remove this
        $currentUrlObject = new ModUrl($this->name, 'route', 'display', ZLanguage::getLanguageCode(), $currentUrlArgs);
        
        if (!SecurityUtil::checkPermission($this->name . ':' . ucfirst($objectType) . ':', $instanceId . '::', $permLevel)) {
            throw new AccessDeniedException();
        }
        
        $viewHelper = $this->serviceManager->get('zikularoutesmodule.view_helper');
        $templateFile = $viewHelper->getViewTemplate($this->view, $objectType, 'display', $request);
        
        // set cache id
        $component = $this->name . ':' . ucfirst($objectType) . ':';
        $instance = $instanceId . '::';
        $accessLevel = ACCESS_READ;
        if (SecurityUtil::checkPermission($component, $instance, ACCESS_COMMENT)) {
            $accessLevel = ACCESS_COMMENT;
        }
        if (SecurityUtil::checkPermission($component, $instance, ACCESS_EDIT)) {
            $accessLevel = ACCESS_EDIT;
        }
        $this->view->setCacheId($objectType . '_display|' . $instanceId . '|a' . $accessLevel);
        
        // assign output data to view object.
        $this->view->assign($objectType, $entity)
                   ->assign('currentUrlObject', $currentUrlObject)
                   ->assign($repository->getAdditionalTemplateParameters('controllerAction', $utilArgs));
        
        // fetch and return the appropriate template
        return $viewHelper->processTemplate($this->view, $objectType, 'display', $request, $templateFile);
    }
    
    /**
     * This method provides a handling of simple delete requests.
     * @ParamConverter("route", class="ZikulaRoutesModule:RouteEntity", options={"id" = "id", "repository_method" = "selectById"})
     * @Cache(lastModified="route.getUpdatedDate()", ETag="'Route' ~ route.getid() ~ route.getUpdatedDate().format('U')")
     *
     * @param Request  $request      Current request instance
     * @param RouteEntity $route      Treated route instance.
     * @param boolean $confirmation Confirm the deletion, else a confirmation page is displayed.
     * @param string  $tpl          Name of alternative template (to be used instead of the default template).
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     * @throws NotFoundHttpException Thrown by param converter if item to be deleted isn't found.
     * @throws RuntimeException      Thrown if another critical error occurs (e.g. workflow actions not available).
     */
    public function deleteAction(Request $request, RouteEntity $route)
    {
        $legacyControllerType = $request->query->filter('lct', 'user', FILTER_SANITIZE_STRING);
        System::queryStringSetVar('type', $legacyControllerType);
        $request->query->set('type', $legacyControllerType);
    
        $controllerHelper = $this->serviceManager->get('zikularoutesmodule.controller_helper');
        
        // parameter specifying which type of objects we are treating
        $objectType = 'route';
        $utilArgs = array('controller' => 'route', 'action' => 'delete');
        $permLevel = $legacyControllerType == 'admin' ? ACCESS_ADMIN : ACCESS_DELETE;
        if (!SecurityUtil::checkPermission($this->name . ':' . ucfirst($objectType) . ':', '::', $permLevel)) {
            throw new AccessDeniedException();
        }
        $entity = $route;
        
        $entity->initWorkflow();
        
        // determine available workflow actions
        $workflowHelper = $this->serviceManager->get('zikularoutesmodule.workflow_helper');
        $actions = $workflowHelper->getActionsForObject($entity);
        if ($actions === false || !is_array($actions)) {
            $this->request->getSession()->getFlashBag()->add('error', $this->__('Error! Could not determine workflow actions.'));
            $logger = $this->serviceManager->get('logger');
            $logger->error('{app}: User {user} tried to delete the {entity} with id {id}, but failed to determine available workflow actions.', array('app' => 'ZikulaRoutesModule', 'user' => UserUtil::getVar('uname'), 'entity' => 'route', 'id' => $entity->createCompositeIdentifier()));
            throw new \RuntimeException($this->__('Error! Could not determine workflow actions.'));
        }
        
        // check whether deletion is allowed
        $deleteActionId = 'delete';
        $deleteAllowed = false;
        foreach ($actions as $actionId => $action) {
            if ($actionId != $deleteActionId) {
                continue;
            }
            $deleteAllowed = true;
            break;
        }
        if (!$deleteAllowed) {
            $this->request->getSession()->getFlashBag()->add('error', $this->__('Error! It is not allowed to delete this route.'));
            $logger = $this->serviceManager->get('logger');
            $logger->error('{app}: User {user} tried to delete the {entity} with id {id}, but this action was not allowed.', array('app' => 'ZikulaRoutesModule', 'user' => UserUtil::getVar('uname'), 'entity' => 'route', 'id' => $entity->createCompositeIdentifier()));
        }
        
        $confirmation = (bool) $request->request->filter('confirmation', false, false, FILTER_VALIDATE_BOOLEAN);
        if ($confirmation && $deleteAllowed) {
            $this->checkCsrfToken();
        
            $hookAreaPrefix = $entity->getHookAreaPrefix();
            $hookType = 'validate_delete';
            // Let any hooks perform additional validation actions
            $hook = new ValidationHook(new ValidationProviders());
            $validators = $this->dispatchHooks($hookAreaPrefix . '.' . $hookType, $hook)->getValidators();
            if (!$validators->hasErrors()) {
                // execute the workflow action
                $success = $workflowHelper->executeAction($entity, $deleteActionId);
                if ($success) {
                    $this->request->getSession()->getFlashBag()->add('status', $this->__('Done! Item deleted.'));
                    $logger = $this->serviceManager->get('logger');
                    $logger->notice('{app}: User {user} deleted the {entity} with id {id}.', array('app' => 'ZikulaRoutesModule', 'user' => UserUtil::getVar('uname'), 'entity' => 'route', 'id' => $entity->createCompositeIdentifier()));
                }
        
                // Let any hooks know that we have created, updated or deleted the route
                $hookType = 'process_delete';
                $hook = new ProcessHook($entity->createCompositeIdentifier());
                $this->dispatchHooks($hookAreaPrefix . '.' . $hookType, $hook);
        
                // The route was deleted, so we clear all cached pages this item.
                $cacheArgs = array('ot' => $objectType, 'item' => $entity);
                ModUtil::apiFunc($this->name, 'cache', 'clearItemCache', $cacheArgs);
        
                // redirect to the list of routes
                $redirectUrl = $this->serviceManager->get('router')->generate('zikularoutesmodule_route_view', array('lct' => $legacyControllerType));
                return new RedirectResponse(System::normalizeUrl($redirectUrl));
            }
        }
        
        $repository = $this->serviceManager->get('zikularoutesmodule.' . $objectType . '_factory')->getRepository();
        
        // set caching id
        $this->view->setCaching(Zikula_View::CACHE_DISABLED);
        
        // assign the object we loaded above
        $this->view->assign($objectType, $entity)
                   ->assign($repository->getAdditionalTemplateParameters('controllerAction', $utilArgs));
        
        // fetch and return the appropriate template
        $viewHelper = $this->serviceManager->get('zikularoutesmodule.view_helper');
        
        return $viewHelper->processTemplate($this->view, $objectType, 'delete', $request);
    }
    
    /**
     * This is a custom method.
     *
     * @param Request  $request      Current request instance
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     */
    public function reloadAction(Request $request)
    {
        $legacyControllerType = $request->query->filter('lct', 'user', FILTER_SANITIZE_STRING);
        System::queryStringSetVar('type', $legacyControllerType);
        $request->query->set('type', $legacyControllerType);
    
        $controllerHelper = $this->serviceManager->get('zikularoutesmodule.controller_helper');
        
        // parameter specifying which type of objects we are treating
        $objectType = 'route';
        $utilArgs = array('controller' => 'route', 'action' => 'reload');
        $permLevel = $legacyControllerType == 'admin' ? ACCESS_ADMIN : ACCESS_OVERVIEW;
        if (!SecurityUtil::checkPermission($this->name . ':' . ucfirst($objectType) . ':', '::', $permLevel)) {
            throw new AccessDeniedException();
        }
        /** TODO: custom logic */
        
        // return template
        return $this->response($this->view->fetch('Route/reload.tpl'));
    }
    
    /**
     * This is a custom method.
     *
     * @param Request  $request      Current request instance
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     */
    public function renewAction(Request $request)
    {
        $legacyControllerType = $request->query->filter('lct', 'user', FILTER_SANITIZE_STRING);
        System::queryStringSetVar('type', $legacyControllerType);
        $request->query->set('type', $legacyControllerType);
    
        $controllerHelper = $this->serviceManager->get('zikularoutesmodule.controller_helper');
        
        // parameter specifying which type of objects we are treating
        $objectType = 'route';
        $utilArgs = array('controller' => 'route', 'action' => 'renew');
        $permLevel = $legacyControllerType == 'admin' ? ACCESS_ADMIN : ACCESS_OVERVIEW;
        if (!SecurityUtil::checkPermission($this->name . ':' . ucfirst($objectType) . ':', '::', $permLevel)) {
            throw new AccessDeniedException();
        }
        /** TODO: custom logic */
        
        // return template
        return $this->response($this->view->fetch('Route/renew.tpl'));
    }
    

    /**
     * Process status changes for multiple items.
     *
     * This function processes the items selected in the admin view page.
     * Multiple items may have their state changed or be deleted.
     *
     * @param string $action The action to be executed.
     * @param array  $items  Identifier list of the items to be processed.
     *
     * @return bool true on sucess, false on failure.
     *
     * @throws RuntimeException Thrown if executing the workflow action fails
     */
    public function handleSelectedEntriesAction(Request $request)
    {
        $this->checkCsrfToken();
        
        $redirectUrl = $this->serviceManager->get('router')->generate('zikularoutesmodule_route_index', array('lct' => 'admin'));
        
        $objectType = 'route';
        
        // Get parameters
        $action = $request->request->get('action', null);
        $items = $request->request->get('items', null);
        
        $action = strtolower($action);
        
        $workflowHelper = $this->serviceManager->get('zikularoutesmodule.workflow_helper');
        
        // process each item
        foreach ($items as $itemid) {
            // check if item exists, and get record instance
            $selectionArgs = array('ot' => $objectType,
                                   'id' => $itemid,
                                   'useJoins' => false);
            $entity = ModUtil::apiFunc($this->name, 'selection', 'getEntity', $selectionArgs);
        
            $entity->initWorkflow();
        
            // check if $action can be applied to this entity (may depend on it's current workflow state)
            $allowedActions = $workflowHelper->getActionsForObject($entity);
            $actionIds = array_keys($allowedActions);
            if (!in_array($action, $actionIds)) {
                // action not allowed, skip this object
                continue;
            }
        
            $hookAreaPrefix = $entity->getHookAreaPrefix();
        
            // Let any hooks perform additional validation actions
            $hookType = $action == 'delete' ? 'validate_delete' : 'validate_edit';
            $hook = new ValidationHook(new ValidationProviders());
            $validators = $this->dispatchHooks($hookAreaPrefix . '.' . $hookType, $hook)->getValidators();
            if ($validators->hasErrors()) {
                continue;
            }
        
            $success = false;
            try {
                // execute the workflow action
                $success = $workflowHelper->executeAction($entity, $action);
            } catch(\Exception $e) {
                $this->request->getSession()->getFlashBag()->add('error', $this->__f('Sorry, but an unknown error occured during the %s action. Please apply the changes again!', array($action)));
                $logger = $this->serviceManager->get('logger');
                $logger->error('{app}: User {user} tried to execute the {action} workflow action for the {entity} with id {id}, but failed. Error details: {errorMessage}.', array('app' => 'ZikulaRoutesModule', 'user' => UserUtil::getVar('uname'), 'action' => $action, 'entity' => 'route', 'id' => $itemid, 'errorMessage' => $e->getMessage()));
            }
        
            if (!$success) {
                continue;
            }
        
            if ($action == 'delete') {
                $this->request->getSession()->getFlashBag()->add('status', $this->__('Done! Item deleted.'));
                $logger = $this->serviceManager->get('logger');
                $logger->notice('{app}: User {user} deleted the {entity} with id {id}.', array('app' => 'ZikulaRoutesModule', 'user' => UserUtil::getVar('uname'), 'entity' => 'route', 'id' => $itemid));
            } else {
                $this->request->getSession()->getFlashBag()->add('status', $this->__('Done! Item updated.'));
                $logger = $this->serviceManager->get('logger');
                $logger->notice('{app}: User {user} executed the {action} workflow action for the {entity} with id {id}.', array('app' => 'ZikulaRoutesModule', 'user' => UserUtil::getVar('uname'), 'action' => $action, 'entity' => 'route', 'id' => $itemid));
            }
        
            // Let any hooks know that we have updated or deleted an item
            $hookType = $action == 'delete' ? 'process_delete' : 'process_edit';
            $url = null;
            if ($action != 'delete') {
                $urlArgs = $entity->createUrlArgs();
                $url = new RouteUrl('zikularoutesmodule_route_display', $urlArgs);
            }
            $hook = new ProcessHook($entity->createCompositeIdentifier(), $url);
            $this->dispatchHooks($hookAreaPrefix . '.' . $hookType, $hook);
        
            // An item was updated or deleted, so we clear all cached pages for this item.
            $cacheArgs = array('ot' => $objectType, 'item' => $entity);
            ModUtil::apiFunc($this->name, 'cache', 'clearItemCache', $cacheArgs);
        }
        
        // clear view cache to reflect our changes
        $this->view->clear_cache();
        
        return new RedirectResponse(System::normalizeUrl($redirectUrl));
    }

    /**
     * This method cares for a redirect within an inline frame.
     *
     * @param string  $idPrefix    Prefix for inline window element identifier.
     * @param string  $commandName Name of action to be performed (create or edit).
     * @param integer $id          Id of created item (used for activating auto completion after closing the modal window).
     *
     * @return boolean Whether the inline redirect has been performed or not.
     */
    public function handleInlineRedirectAction($idPrefix, $commandName, $id = 0)
    {
        if (empty($idPrefix)) {
            return false;
        }
        
        $this->view->assign('itemId', $id)
                   ->assign('idPrefix', $idPrefix)
                   ->assign('commandName', $commandName)
                   ->assign('jcssConfig', JCSSUtil::getJSConfig());
        
        return new PlainResponse($this->view->display('Route/inlineRedirectHandler.tpl'));
    }
}
