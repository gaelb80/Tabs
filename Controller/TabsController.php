<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Tabs\Controller;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tabs\Event\TabsDeleteEvent;
use Tabs\Event\TabsEvent;
use Tabs\Form\TabsCategoryForm;
use Tabs\Form\TabsContentForm;
use Tabs\Form\TabsFolderForm;
use Tabs\Form\TabsProductForm;
use Tabs\Model\CategoryAssociatedTabQuery;
use Tabs\Model\ContentAssociatedTabQuery;
use Tabs\Model\FolderAssociatedTabQuery;
use Tabs\Model\ProductAssociatedTab;
use Tabs\Model\ProductAssociatedTabQuery;
use Thelia\Controller\Admin\AbstractCrudController;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Form\CategoryModificationForm;
use Thelia\Form\ContentModificationForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\FolderModificationForm;
use Thelia\Form\ProductModificationForm;
use Thelia\Model\ProductQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Tools\URL;

/**
 * Class TabsController
 * @package Tabs\Controller\Admin
 * @author Michaël Espeche <mespeche@openstudio.fr>
 */
class TabsController extends AbstractCrudController
{
	static $possibleVars = [ 'product_id', 'category_id', 'content_id', 'folder_id'];

	public function __construct()
	{
		parent::__construct(
			'tabs',
			'manual',
			'tabs_order',
			'admin.tabs',
			null,
			null,
			TabsEvent::TABS_DELETE,
			null,
			null,
			'Tabs'
		);
	}

	public function config()
	{
		return $this->render('tabs-config');
	}

	public function initPosition()
	{
		$products = ProductQuery::create()
			->find();

		foreach ($products as $product) {
			$productTabs = ProductAssociatedTabQuery::create()
				->filterByProductId($product->getId())
				->find();

			$position = 1;

			/** @var ProductAssociatedTab $productTab */
			foreach ($productTabs as $productTab) {
				$productTab
					->setPosition($position)
					->save();

				$position++;
			}
		}

		return $this->render('tabs-config');
	}

	public function manageTabsContentAssociation($contentId)
	{

		if (null !== $response = $this->checkAuth(array(), array('Tabs'), AccessManager::UPDATE)) {
			return $response;
		}

		$tabId = $this->getRequest()->get('tab_id', null);
		if (null === $tabId) {
			return $this->createNewTabContentAssociation($contentId);
		} else {
			return $this->updateTabContentAssociation($tabId);
		}

	}

	public function manageTabsCategoryAssociation($categoryId)
	{

		if (null !== $response = $this->checkAuth(array(), array('Tabs'), AccessManager::UPDATE)) {
			return $response;
		}

		$tabId = $this->getRequest()->get('tab_id', null);
		if (null === $tabId) {
			return $this->createNewTabCategoryAssociation($categoryId);
		} else {
			return $this->updateTabCategoryAssociation($tabId);
		}

	}

	public function manageTabsFolderAssociation($folderId)
	{

		if (null !== $response = $this->checkAuth(array(), array('Tabs'), AccessManager::UPDATE)) {
			return $response;
		}

		$tabId = $this->getRequest()->get('tab_id', null);
		if (null === $tabId) {
			return $this->createNewTabFolderAssociation($folderId);
		} else {
			return $this->updateTabFolderAssociation($tabId);
		}

	}

	public function createNewTabContentAssociation($contentId)
	{

		$tabsContentForm = new TabsContentForm($this->getRequest());

		$message = false;

		try {
			$content = ContentQuery::create()->findPk($contentId);

			if (null === $content) {
				throw new \InvalidArgumentException(sprintf("%d content id does not exist", $contentId));
			}

			$form = $this->validateForm($tabsContentForm);

			$event = $this->createEventInstance($form->getData());
			$event->setContentId($content->getId());

			$this->dispatch(TabsEvent::TABS_CONTENT_CREATE, $event);

			return $this->generateSuccessRedirect($tabsContentForm);

		} catch (FormValidationException $e) {
			$message = sprintf("Please check your input: %s", $e->getMessage());
		} catch (PropelException $e) {
			$message = $e->getMessage();
		} catch (\Exception $e) {
			$message = sprintf("Sorry, an error occured: %s", $e->getMessage() . " " . $e->getFile());
		}

		if ($message !== false) {
			\Thelia\Log\Tlog::getInstance()->error(sprintf("Error during tabs content association process : %s.", $message));

			$tabsContentForm->setErrorMessage($message);

			$this->getParserContext()
				->addForm($tabsContentForm)
				->setGeneralError($message);
		}

		return $this->updateAction();
	}

	public function updateTabContentAssociation($tabId)
	{

		$tabsContentForm = new TabsContentForm($this->getRequest());

		$message = false;

		try {
			$tab = ContentAssociatedTabQuery::create()->findPk($tabId);

			if (null === $tab) {
				throw new \InvalidArgumentException(sprintf("%d tab id does not exist", $tabId));
			}

			$form = $this->validateForm($tabsContentForm);

			$event = $this->createEventInstance($form->getData());
			$event->setTabId($tab->getId());
			$event->setContentId($tab->getContentId());

			$this->dispatch(TabsEvent::TABS_CONTENT_UPDATE, $event);

			return $this->generateSuccessRedirect($tabsContentForm);

		} catch (FormValidationException $e) {
			$message = sprintf("Please check your input: %s", $e->getMessage());
		} catch (PropelException $e) {
			$message = $e->getMessage();
		} catch (\Exception $e) {
			$message = sprintf("Sorry, an error occured: %s", $e->getMessage() . " " . $e->getFile());
		}

		if ($message !== false) {
			\Thelia\Log\Tlog::getInstance()->error(sprintf("Error during tabs content association process : %s.", $message));

			$tabsContentForm->setErrorMessage($message);

			$this->getParserContext()
				->addForm($tabsContentForm)
				->setGeneralError($message);
		}

		return $this->updateAction();

	}

	public function manageTabsProductAssociation($productId)
	{

		if (null !== $response = $this->checkAuth(array(), array('Tabs'), AccessManager::UPDATE)) {
			return $response;
		}

		$tabId = $this->getRequest()->get('tab_id', null);
		if (null === $tabId) {
			return $this->createNewTabProductAssociation($productId);
		} else {
			return $this->updateTabProductAssociation($tabId);
		}

	}

	public function createNewTabProductAssociation($productId)
	{

		$tabsProductForm = new TabsProductForm($this->getRequest());

		$message = false;

		try {
			$product = ProductQuery::create()->findPk($productId);

			if (null === $product) {
				throw new \InvalidArgumentException(sprintf("%d content id does not exist", $productId));
			}

			$form = $this->validateForm($tabsProductForm);

			$event = $this->createEventInstance($form->getData());
			$event->setProductId($product->getId());

			$this->dispatch(TabsEvent::TABS_PRODUCT_CREATE, $event);

			return $this->generateSuccessRedirect($tabsProductForm);
		} catch (FormValidationException $e) {
			$message = sprintf("Please check your input: %s", $e->getMessage());
		} catch (PropelException $e) {
			$message = $e->getMessage();
		} catch (\Exception $e) {
			$message = sprintf("Sorry, an error occured: %s", $e->getMessage() . " " . $e->getFile());
		}

		if ($message !== false) {
			\Thelia\Log\Tlog::getInstance()->error(sprintf("Error during tabs product association process : %s.", $message));

			$tabsProductForm->setErrorMessage($message);

			$this->getParserContext()
				->addForm($tabsProductForm)
				->setGeneralError($message);
		}

		return $this->updateAction();
	}

	public function updateTabProductAssociation($tabId)
	{

		$tabsProductForm = new TabsProductForm($this->getRequest());

		$message = false;

		try {
			$tab = ProductAssociatedTabQuery::create()->findPk($tabId);

			if (null === $tab) {
				throw new \InvalidArgumentException(sprintf("%d tab id does not exist", $tabId));
			}

			$form = $this->validateForm($tabsProductForm);

			$event = $this->createEventInstance($form->getData());
			$event->setTabId($tab->getId());
			$event->setProductId($tab->getProductId());

			$this->dispatch(TabsEvent::TABS_PRODUCT_UPDATE, $event);

			return $this->generateSuccessRedirect($tabsProductForm);
		} catch (FormValidationException $e) {
			$message = sprintf("Please check your input: %s", $e->getMessage());
		} catch (PropelException $e) {
			$message = $e->getMessage();
		} catch (\Exception $e) {
			$message = sprintf("Sorry, an error occured: %s", $e->getMessage() . " " . $e->getFile());
		}

		if ($message !== false) {
			\Thelia\Log\Tlog::getInstance()->error(sprintf("Error during tabs product association process : %s.", $message));

			$tabsProductForm->setErrorMessage($message);

			$this->getParserContext()
				->addForm($tabsProductForm)
				->setGeneralError($message);
		}

		return $this->updateAction();

	}

	public function createNewTabCategoryAssociation($categoryId)
	{

		$tabsCategoryForm = new TabsCategoryForm($this->getRequest());

		$message = false;

		try {
			$category = CategoryQuery::create()->findPk($categoryId);

			if (null === $category) {
				throw new \InvalidArgumentException(sprintf("%d category id does not exist", $categoryId));
			}

			$form = $this->validateForm($tabsCategoryForm);

			$event = $this->createEventInstance($form->getData());
			$event->setCategoryId($category->getId());

			$this->dispatch(TabsEvent::TABS_CATEGORY_CREATE, $event);

			return $this->generateSuccessRedirect($tabsCategoryForm);

		} catch (FormValidationException $e) {
			$message = sprintf("Please check your input: %s", $e->getMessage());
		} catch (PropelException $e) {
			$message = $e->getMessage();
		} catch (\Exception $e) {
			$message = sprintf("Sorry, an error occured: %s", $e->getMessage() . " " . $e->getFile());
		}

		if ($message !== false) {
			\Thelia\Log\Tlog::getInstance()->error(sprintf("Error during tabs category association process : %s.", $message));

			$tabsCategoryForm->setErrorMessage($message);

			$this->getParserContext()
				->addForm($tabsCategoryForm)
				->setGeneralError($message);
		}

		return $this->updateAction();
	}

	public function updateTabCategoryAssociation($tabId)
	{

		$tabsCategoryForm = new TabsCategoryForm($this->getRequest());

		$message = false;

		try {
			$tab = CategoryAssociatedTabQuery::create()->findPk($tabId);

			if (null === $tab) {
				throw new \InvalidArgumentException(sprintf("%d tab id does not exist", $tabId));
			}

			$form = $this->validateForm($tabsCategoryForm);

			$event = $this->createEventInstance($form->getData());
			$event->setTabId($tab->getId());
			$event->setCategoryId($tab->getCategoryId());

			$this->dispatch(TabsEvent::TABS_CATEGORY_UPDATE, $event);

			return $this->generateSuccessRedirect($tabsCategoryForm);

		} catch (FormValidationException $e) {
			$message = sprintf("Please check your input: %s", $e->getMessage());
		} catch (PropelException $e) {
			$message = $e->getMessage();
		} catch (\Exception $e) {
			$message = sprintf("Sorry, an error occured: %s", $e->getMessage() . " " . $e->getFile());
		}

		if ($message !== false) {
			\Thelia\Log\Tlog::getInstance()->error(sprintf("Error during tabs folder association process : %s.", $message));

			$tabsCategoryForm->setErrorMessage($message);

			$this->getParserContext()
				->addForm($tabsCategoryForm)
				->setGeneralError($message);
		}

		return $this->updateAction();

	}

	public function createNewTabFolderAssociation($folderId)
	{

		$tabsFolderForm = new TabsFolderForm($this->getRequest());

		$message = false;

		try {
			$folder = FolderQuery::create()->findPk($folderId);

			if (null === $folder) {
				throw new \InvalidArgumentException(sprintf("%d folder id does not exist", $folderId));
			}

			$form = $this->validateForm($tabsFolderForm);

			$event = $this->createEventInstance($form->getData());
			$event->setFolderId($folder->getId());

			$this->dispatch(TabsEvent::TABS_FOLDER_CREATE, $event);

			return $this->generateSuccessRedirect($tabsFolderForm);

		} catch (FormValidationException $e) {
			$message = sprintf("Please check your input: %s", $e->getMessage());
		} catch (PropelException $e) {
			$message = $e->getMessage();
		} catch (\Exception $e) {
			$message = sprintf("Sorry, an error occured: %s", $e->getMessage() . " " . $e->getFile());
		}

		if ($message !== false) {
			\Thelia\Log\Tlog::getInstance()->error(sprintf("Error during tabs folder association process : %s.", $message));

			$tabsFolderForm->setErrorMessage($message);

			$this->getParserContext()
				->addForm($tabsFolderForm)
				->setGeneralError($message);
		}

		return $this->updateAction();
	}

	public function updateTabFolderAssociation($tabId)
	{

		$tabsFolderForm = new TabsFolderForm($this->getRequest());

		$message = false;

		try {
			$tab = FolderAssociatedTabQuery::create()->findPk($tabId);

			if (null === $tab) {
				throw new \InvalidArgumentException(sprintf("%d tab id does not exist", $tabId));
			}

			$form = $this->validateForm($tabsFolderForm);

			$event = $this->createEventInstance($form->getData());
			$event->setTabId($tab->getId());
			$event->setFolderId($tab->getFolderId());

			$this->dispatch(TabsEvent::TABS_FOLDER_UPDATE, $event);

			return $this->generateSuccessRedirect($tabsFolderForm);

		} catch (FormValidationException $e) {
			$message = sprintf("Please check your input: %s", $e->getMessage());
		} catch (PropelException $e) {
			$message = $e->getMessage();
		} catch (\Exception $e) {
			$message = sprintf("Sorry, an error occured: %s", $e->getMessage() . " " . $e->getFile());
		}

		if ($message !== false) {
			\Thelia\Log\Tlog::getInstance()->error(sprintf("Error during tabs folder association process : %s.", $message));

			$tabsFolderForm->setErrorMessage($message);

			$this->getParserContext()
				->addForm($tabsFolderForm)
				->setGeneralError($message);
		}

		return $this->updateAction();

	}

	/**
	 * @param $data
	 * @return \Tabs\Event\TabsEvent
	 */
	private function createEventInstance($data)
	{

		$tabsAssociationEvent = new TabsEvent(
			empty($data["description"]) ? null : $data["description"],
			empty($data["locale"]) ? null : $data["locale"],
			empty($data["title"]) ? null : $data["title"],
			empty($data["visible"]) ? null : $data["visible"],
			empty($data["position"]) ? null : $data["position"],
			empty($data["product_id"]) ? null : $data["product_id"],
			empty($data["folder_id"]) ? null : $data["folder_id"],
			empty($data["category_id"]) ? null : $data["category_id"],
			empty($data["contenu_id"]) ? null : $data["contenu_id"]
        );

		return $tabsAssociationEvent;
	}

	/**
	 * Return the creation form for this object
	 */
	protected function getCreationForm()
	{
		// TODO: Implement getCreationForm() method.
	}

	/**
	 * Return the update form for this object
	 */
	protected function getUpdateForm()
	{
		// TODO: Implement getUpdateForm() method.
	}

	/**
	 * Hydrate the update form for this object, before passing it to the update template
	 *
	 * @param unknown $object
	 */
	protected function hydrateObjectForm($object)
	{
		// Hydrate the "SEO" tab form
		$this->hydrateSeoForm($object);

		// Prepare the data that will hydrate the form
		$data = array(
			'id' => $object->getId(),
			'locale' => $object->getLocale(),
			'title' => $object->getTitle(),
			'chapo' => $object->getChapo(),
			'description' => $object->getDescription(),
			'postscriptum' => $object->getPostscriptum(),
			'visible' => $object->getVisible()
		);

		// Get type of association to hydrate the correct modification form
		if ($object->type === 'content') {
			// Setup the object form
			return new ContentModificationForm($this->getRequest(), "form", $data);
		}

		if ($object->type === 'product') {
			// Setup the object form
			return new ProductModificationForm($this->getRequest(), "form", $data);
		}

		if ($object->type === 'category') {
			// Setup the object form
			return new CategoryModificationForm($this->getRequest(), "form", $data);
		}

		if ($object->type === 'folder') {
			// Setup the object form
			return new FolderModificationForm($this->getRequest(), "form", $data);
		}
	}

	/**
	 * Creates the creation event with the provided form data
	 *
	 * @param unknown $formData
	 */
	protected function getCreationEvent($formData)
	{
		// TODO: Implement getCreationEvent() method.
	}

	/**
	 * Creates the update event with the provided form data
	 *
	 * @param unknown $formData
	 */
	protected function getUpdateEvent($formData)
	{
		// TODO: Implement getUpdateEvent() method.
	}

	/**
	 * Creates the delete event with the provided form data
	 */
	protected function getDeleteEvent()
	{
		return new TabsDeleteEvent($this->getRequest()->get('tab_id'), 0);
	}

	/**
	 * Return true if the event contains the object, e.g. the action has updated the object in the event.
	 *
	 * @param unknown $event
	 */
	protected function eventContainsObject($event)
	{
		// TODO: Implement eventContainsObject() method.
	}

	/**
	 * Get the created object from an event.
	 *
	 * @param unknown $event
	 */
	protected function getObjectFromEvent($event)
	{
		// TODO: Implement getObjectFromEvent() method.
	}

	/**
	 * Load an existing object from the database
	 */
	protected function getExistingObject()
	{
		$contentId = $this->getRequest()->get('content_id', null);

		// Create ContentQuery id contentId
		if (null !== $contentId) {
			$query = ContentQuery::create()
				->joinWithI18n($this->getCurrentEditionLocale())
				->findOneById($contentId);

			// Set type of association
			$query->type = 'content';

			return $query;
		}

		$productId = $this->getRequest()->get('product_id', null);

		// Create ContentQuery id contentId
		if (null !== $productId) {
			$query = ProductQuery::create()
				->joinWithI18n($this->getCurrentEditionLocale())
				->findOneById($productId);

			// Set type of association
			$query->type = 'product';

			return $query;
		}

		$folderId = $this->getRequest()->get('folder_id', null);

		// Create ContentQuery id contentId
		if (null !== $folderId) {
			$query = FolderQuery::create()
				->joinWithI18n($this->getCurrentEditionLocale())
				->findOneById($folderId);

			// Set type of association
			$query->type = 'folder';

			return $query;
		}

		$categoryId = $this->getRequest()->get('category_id', null);

		// Create ContentQuery id contentId
		if (null !== $categoryId) {
			$query = CategoryQuery::create()
				->joinWithI18n($this->getCurrentEditionLocale())
				->findOneById($categoryId);

			// Set type of association
			$query->type = 'category';

			return $query;
		}

		return null;
	}

	/**
	 * Returns the object label form the object event (name, title, etc.)
	 *
	 * @param unknown $object
	 */
	protected function getObjectLabel($object)
	{
		// TODO: Implement getObjectLabel() method.
	}

	/**
	 * Returns the object ID from the object
	 *
	 * @param unknown $object
	 */
	protected function getObjectId($object)
	{
		// TODO: Implement getObjectId() method.
	}

	/**
	 * Render the main list template
	 *
	 * @param unknown $currentOrder , if any, null otherwise.
	 */
	protected function renderListTemplate($currentTabs)
	{
		// TODO: Implement renderListTemplate() method.
	}

	protected function getFolderId()
	{
		$folderId = $this->getRequest()->get('folder_id', null);

		if (null === $folderId) {
			$content = $this->getExistingObject();

			if ($content) {
				$folderId = $content->getDefaultFolderId();
			}
		}

		return $folderId ?: 0;
	}

	protected function getCategoryId()
	{
		$category_id = $this->getRequest()->get('category_id', null);

		if ($category_id == null) {
			$product = $this->getExistingObject();

			if ($product !== null) {
				$category_id = $product->getDefaultCategoryId();
			}
		}

		return $category_id != null ? $category_id : 0;
	}

	protected function getEditionArguments()
	{
		$args = array();

		// Return args for content association
		$contentId = $this->getRequest()->get('content_id', null);

		if (null !== $contentId) {
			$args = array(
				'content_id' => $this->getRequest()->get('content_id', 0),
				'current_tab' => $this->getRequest()->get('current_tab', 'general'),
				'folder_id' => $this->getFolderId()
			);
		}

		// Return args for product association
		$productId = $this->getRequest()->get('product_id', null);

		if (null !== $productId) {
			$args = array(
				'product_id' => $this->getRequest()->get('product_id', 0),
				'current_tab' => $this->getRequest()->get('current_tab', 'general'),
				'category_id' => $this->getCategoryId()
			);
		}

		// Return args for category association
		$categoryId = $this->getRequest()->get('category_id', null);

		if (null !== $categoryId) {
			$args = array(
				'category_id' => $this->getRequest()->get('category_id', 0),
				'current_tab' => $this->getRequest()->get('current_tab', 'general'),
			);
		}

		// Return args for folder association
		$folderId = $this->getRequest()->get('folder_id', null);

		if (null !== $folderId) {
			$args = array(
				'folder_id' => $this->getRequest()->get('folder_id', 0),
				'current_tab' => $this->getRequest()->get('current_tab', 'general'),
			);
		}

		return $args;
	}

	/**
	 * Render the edition template
	 */
	protected function renderEditionTemplate()
	{

		$args = $this->getEditionArguments();

		// Render content-edit if content_id
		if (isset($args['content_id']) && null !== $args['content_id']) {
			return $this->render('content-edit', $args);
		}

		// Render product-edit if product_id
		if (isset($args['product_id']) && null !== $args['product_id']) {
			return $this->render('product-edit', $args);
		}

		// Render product-edit if category_id
		if (isset($args['category_id']) && null !== $args['category_id']) {
			return $this->render('category-edit', $args);
		}

		// Render product-edit if folder_id
		if (isset($args['folder_id']) && null !== $args['folder_id']) {
			return $this->render('folder-edit', $args);
		}

		return $this->render('home');
	}

	/**
	 * Redirect to the edition template
	 */
	protected function redirectToEditionTemplate()
	{
		$productId = $this->getRequest()->get('product_id');

		return new RedirectResponse(URL::getInstance()->absoluteUrl("/admin/products/update",
			["product_id" => $productId, "current_tab" => 'modules']));
	}

	/**
	 * Redirect to the list template
	 */
	protected function redirectToListTemplate()
	{
		foreach (self::$possibleVars as $var) {
			if (null !== $resourceId = $this->getRequest()->get($var)) {
				switch ($var) {
					case 'product_id' :
						$uri = '/admin/products/update';
						$param = [$var => $resourceId, "current_tab" => 'modules'];
						break 2;
					case 'category_id' :
						$uri = '/admin/categories/update';
						$param = [$var => $resourceId, "current_tab" => 'modules'];
						break 2;
					case 'folder_id' :
						$uri = '/admin/folders/update/' . $resourceId . '#modules';
						$param = ["current_tab" => 'modules'];
						break 2;
					case 'content_id' :
						$uri = '/admin/content/update/' . $resourceId . '#modules';
						$param = ["current_tab" => 'modules'];
						break 2;
					default :
						throw new NotFoundHttpException('Resource key not found');
				}
			}
		}

		return new RedirectResponse(URL::getInstance()->absoluteUrl($uri, $param));
	}

	/**
	 * Put in this method post object delete processing if required.
	 *
	 * @param  unknown $deleteEvent the delete event
	 * @return Response a response, or null to continue normal processing
	 */
	protected function performAdditionalDeleteAction($deleteEvent)
	{
		if (null !== $deleteEvent->getContentId()) {
			$url = '/admin/content/update/' . $deleteEvent->getContentId();
		}

		if (null !== $deleteEvent->getProductId()) {
			$url = '/admin/products/update?product_id=' . $deleteEvent->getProductId();
		}

		if (null !== $deleteEvent->getCategoryId()) {
			$url = '/admin/categories/update?category_id=' . $deleteEvent->getCategoryId();
		}

		if (null !== $deleteEvent->getFolderId()) {
			$url = '/admin/folders/update/' . $deleteEvent->getFolderId();
		}

		return $this->generateRedirect(
			URL::getInstance()->absoluteUrl($url, ['current_tab' => 'modules'])
		);
	}

	protected function createUpdatePositionEvent($positionChangeMode, $positionValue)
	{
		foreach (self::$possibleVars as $var) {
			if (null !== $this->getRequest()->get($var)) {
				// Change our parent event name :)
				switch ($var) {
					case 'content_id':
						$this->changePositionEventIdentifier = TabsEvent::TABS_CONTENT_POSITION_UPDATE;
						break 2;
					case 'category_id':
						$this->changePositionEventIdentifier = TabsEvent::TABS_CATEGORY_POSITION_UPDATE;
						break 2;
					case 'product_id':
						$this->changePositionEventIdentifier = TabsEvent::TABS_PRODUCT_POSITION_UPDATE;
						break 2;
					case 'folder_id':
						$this->changePositionEventIdentifier = TabsEvent::TABS_FOLDER_POSITION_UPDATE;
						break 2;
				}
			}
		}

		return new UpdatePositionEvent(
			$this->getRequest()->get('tab_id', null),
			$positionChangeMode,
			$positionValue
		);
	}

	protected function performAdditionalUpdatePositionAction($positionChangeEvent)
	{
		return $this->redirectToListTemplate();
	}
}
