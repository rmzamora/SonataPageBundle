<?php
/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\PageBundle\Controller\Api;

use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\View;
use JMS\Serializer\SerializationContext;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sonata\BlockBundle\Model\BlockManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Model\PageManagerInterface;
use FOS\RestBundle\View\View as FOSRestView;
use Sonata\NotificationBundle\Backend\BackendInterface;
use Sonata\BlockBundle\Model\BlockInterface;

/**
 * Class PageController
 *
 * @package Sonata\PageBundle\Controller\Api
 *
 * @author Hugo Briand <briand@ekino.com>
 */
class PageController
{
    /**
     * @var PageManagerInterface
     */
    protected $pageManager;

    /**
     * @var BlockManagerInterface
     */
    protected $blockManager;

    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Constructor
     *
     * @param PageManagerInterface  $pageManager
     * @param BlockManagerInterface $blockManager
     * @param FormFactoryInterface  $formFactory
     * @param BackendInterface      $backend
     */
    public function __construct(PageManagerInterface $pageManager, BlockManagerInterface $blockManager, FormFactoryInterface $formFactory, BackendInterface $backend)
    {
        $this->pageManager  = $pageManager;
        $this->blockManager = $blockManager;
        $this->formFactory  = $formFactory;
        $this->backend      = $backend;
    }

    /**
     * Retrieves the list of pages (paginated)
     *
     * @ApiDoc(
     *  resource=true,
     *  output={"class"="Sonata\PageBundle\Model\PageInterface", "groups"="sonata_api_read"}
     * )
     *
     * @QueryParam(name="page", requirements="\d+", default="1", description="Page for 'page' list pagination")
     * @QueryParam(name="count", requirements="\d+", default="10", description="Number of pages by page")
     * @QueryParam(name="enabled", requirements="0|1", nullable=true, strict=true, description="Enabled/Disabled pages filter")
     * @QueryParam(name="orderBy", array=true, requirements="ASC|DESC", nullable=true, strict=true, description="Order by array (key is field, value is direction)")
     *
     * @View(serializerGroups="sonata_api_read", serializerEnableMaxDepthChecks=true)
     *
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return PageInterface[]
     */
    public function getPagesAction(ParamFetcherInterface $paramFetcher)
    {
        $supportedFilters = array(
            'enabled' => "",
        );

        $page    = $paramFetcher->get('page') - 1;
        $count   = $paramFetcher->get('count');
        $orderBy = $paramFetcher->get('orderBy');
        $filters = array_intersect_key($paramFetcher->all(), $supportedFilters);

        foreach ($filters as $key => $value) {
            if (null === $value) {
                unset($filters[$key]);
            }
        }
        return $this->pageManager->findBy($filters, $orderBy, $count, $page);
    }

    /**
     * Retrieves a specific page
     *
     * @ApiDoc(
     *  requirements={
     *      {"name"="id", "dataType"="integer", "requirement"="\d+", "description"="page id"}
     *  },
     *  output={"class"="Sonata\PageBundle\Model\PageInterface", "groups"="sonata_api_read"},
     *  statusCodes={
     *      200="Returned when successful",
     *      404="Returned when page is not found"
     *  }
     * )
     *
     * @View(serializerGroups="sonata_api_read", serializerEnableMaxDepthChecks=true)
     *
     * @param $id
     *
     * @return PageInterface
     */
    public function getPageAction($id)
    {
        return $this->getPage($id);
    }

    /**
     * Retrieves a specific page's blocks
     *
     * @ApiDoc(
     *  requirements={
     *      {"name"="id", "dataType"="integer", "requirement"="\d+", "description"="page id"}
     *  },
     *  output={"class"="Sonata\BlockBundle\Model\BlockInterface", "groups"="sonata_api_read"},
     *  statusCodes={
     *      200="Returned when successful",
     *      404="Returned when page is not found"
     *  }
     * )
     *
     * @View(serializerGroups="sonata_api_read", serializerEnableMaxDepthChecks=true)
     *
     * @param $id
     *
     * @return BlockInterface[]
     */
    public function getPageBlocksAction($id)
    {
        return $this->getPage($id)->getBlocks();
    }

    /**
     * Adds a block
     *
     * @ApiDoc(
     *  input={"class"="sonata_page_api_form_block", "name"="", "groups"={"sonata_api_write"}},
     *  output={"class"="Sonata\PageBundle\Model\Block", "groups"={"sonata_api_read"}},
     *  statusCodes={
     *      200="Returned when successful",
     *      400="Returned when an error has occurred while block creation",
     *      404="Returned when unable to find page"
     *  }
     * )
     *
     * @param Request $request A Symfony request
     *
     * @return BlockInterface
     *
     * @throws NotFoundHttpException
     */
    public function postPageBlockAction(Request $request)
    {
        return $this->handleWriteBlock($request);
    }

    /**
     * Updates a block
     *
     * @ApiDoc(
     *  requirements={
     *      {"name"="id", "dataType"="integer", "requirement"="\d+", "description"="block identifier"}
     *  },
     *  input={"class"="sonata_page_api_form_block", "name"="", "groups"={"sonata_api_write"}},
     *  output={"class"="Sonata\PageBundle\Model\Block", "groups"={"sonata_api_read"}},
     *  statusCodes={
     *      200="Returned when successful",
     *      400="Returned when an error has occurred while block creation",
     *      404="Returned when unable to find page"
     *  }
     * )
     *
     * @param int     $id      Block id
     * @param Request $request A Symfony request
     *
     * @return BlockInterface
     *
     * @throws NotFoundHttpException
     */
    public function putPageBlockAction($id, Request $request)
    {
        return $this->handleWriteBlock($request, $id);
    }

    /**
     * Deletes a block
     *
     * @ApiDoc(
     *  requirements={
     *      {"name"="id", "dataType"="integer", "requirement"="\d+", "description"="block identifier"}
     *  },
     *  statusCodes={
     *      200="Returned when block is successfully deleted",
     *      400="Returned when an error has occured while block deletion",
     *      404="Returned when unable to find block"
     *  }
     * )
     *
     * @param integer $id A Block identifier
     *
     * @return \FOS\RestBundle\View\View
     *
     * @throws NotFoundHttpException
     */
    public function deletePageBlockAction($id)
    {
        $block = $this->getBlock($id);

        $this->blockManager->delete($block);

        return array('deleted' => true);
    }

    /**
     * Adds a page
     *
     * @ApiDoc(
     *  input={"class"="sonata_page_api_form_page", "name"="", "groups"={"sonata_api_write"}},
     *  output={"class"="Sonata\PageBundle\Model\Page", "groups"={"sonata_api_read"}},
     *  statusCodes={
     *      200="Returned when successful",
     *      400="Returned when an error has occurred while page creation",
     *      404="Returned when unable to find page"
     *  }
     * )
     *
     * @param Request $request A Symfony request
     *
     * @return PageInterface
     *
     * @throws NotFoundHttpException
     */
    public function postPageAction(Request $request)
    {
        return $this->handleWritePage($request);
    }

    /**
     * Updates a page
     *
     * @ApiDoc(
     *  requirements={
     *      {"name"="id", "dataType"="integer", "requirement"="\d+", "description"="page identifier"}
     *  },
     *  input={"class"="sonata_page_api_form_page", "name"="", "groups"={"sonata_api_write"}},
     *  output={"class"="Sonata\PageBundle\Model\Page", "groups"={"sonata_api_read"}},
     *  statusCodes={
     *      200="Returned when successful",
     *      400="Returned when an error has occurred while page update",
     *      404="Returned when unable to find page"
     *  }
     * )
     *
     * @param integer $id      A Page identifier
     * @param Request $request A Symfony request
     *
     * @return PageInterface
     *
     * @throws NotFoundHttpException
     */
    public function putPageAction($id, Request $request)
    {
        return $this->handleWritePage($request, $id);
    }

    /**
     * Deletes a page
     *
     * @ApiDoc(
     *  requirements={
     *      {"name"="id", "dataType"="integer", "requirement"="\d+", "description"="page identifier"}
     *  },
     *  statusCodes={
     *      200="Returned when page is successfully deleted",
     *      400="Returned when an error has occurred while page deletion",
     *      404="Returned when unable to find page"
     *  }
     * )
     *
     * @param integer $id A Page identifier
     *
     * @return \FOS\RestBundle\View\View
     *
     * @throws NotFoundHttpException
     */
    public function deletePageAction($id)
    {
        $page = $this->getPage($id);

        $this->pageManager->delete($page);

        return array('deleted' => true);
    }

    /**
     * Creates snapshots of a page
     *
     * @ApiDoc(
     *  requirements={
     *      {"name"="id", "dataType"="integer", "requirement"="\d+", "description"="page identifier"}
     *  },
     *  statusCodes={
     *      200="Returned when snapshots are successfully queued for creation",
     *      400="Returned when an error has occurred while snapshots creation",
     *      404="Returned when unable to find page"
     *  }
     * )
     *
     * @param integer $id A Page identifier
     *
     * @return \FOS\RestBundle\View\View
     *
     * @throws NotFoundHttpException
     */
    public function postPageSnapshotAction($id)
    {
        $page = $this->getPage($id);

        $this->backend->createAndPublish('sonata.page.create_snapshot', array(
            'pageId' => $page->getId(),
        ));

        return array('queued' => true);
    }

    /**
     * Retrieves page with id $id or throws an exception if it doesn't exist
     *
     * @param $id
     *
     * @return PageInterface
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    protected function getPage($id)
    {
        $page = $this->pageManager->findOneBy(array('id' => $id));

        if (null === $page) {
            throw new NotFoundHttpException(sprintf('Page (%d) not found', $id));
        }

        return $page;
    }

    /**
     * Retrieves Block with id $id or throws an exception if it doesn't exist
     *
     * @param $id
     *
     * @return BlockInterface
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    protected function getBlock($id)
    {
        $block = $this->blockManager->findOneBy(array('id' => $id));

        if (null === $block) {
            throw new NotFoundHttpException(sprintf('Block (%d) not found', $id));
        }

        return $block;
    }

    /**
     * Write a page, this method is used by both POST and PUT action methods
     *
     * @param Request      $request Symfony request
     * @param integer|null $id      A page identifier
     *
     * @return \FOS\RestBundle\View\View|FormInterface
     */
    protected function handleWritePage($request, $id = null)
    {
        $page = $id ? $this->getPage($id) : null;

        $form = $this->formFactory->createNamed(null, 'sonata_page_api_form_page', $page, array(
            'csrf_protection' => false
        ));

        $form->bind($request);

        if ($form->isValid()) {
            $page = $form->getData();
            $this->pageManager->save($page);

            $view = FOSRestView::create($page);
            $serializationContext = SerializationContext::create();
            $serializationContext->setGroups(array('sonata_api_read'));
            $serializationContext->enableMaxDepthChecks();
            $view->setSerializationContext($serializationContext);

            return $view;
        }

        return $form;
    }

    /**
     * Write a Block, this method is used by both POST and PUT action methods.
     *
     * @param Request      $request Symfony request
     * @param integer|null $id      A Block identifier
     *
     * @return \FOS\RestBundle\View\View|FormInterface
     */
    protected function handleWriteBlock($request, $id = null)
    {
        $block = $id ? $this->getBlock($id) : null;

        $form = $this->formFactory->createNamed(null, 'sonata_page_api_form_block', $block, array(
            'csrf_protection' => false
        ));

        $form->bind($request);

        if ($form->isValid()) {
            $block = $form->getData();
            $this->blockManager->save($block);

            $view = FOSRestView::create($block);
            $serializationContext = SerializationContext::create();
            $serializationContext->setGroups(array('sonata_api_read'));
            $serializationContext->enableMaxDepthChecks();
            $view->setSerializationContext($serializationContext);

            return $view;
        }

        return $form;
    }
}
