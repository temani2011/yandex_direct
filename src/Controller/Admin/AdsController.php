<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace EO\YandexDirect\Controller\Admin;

use Closure;
use Context;
use Module;
use EO\YandexDirect\Grid\Definition\Factory\AdsGridDefinitionFactory;
use EO\YandexDirect\Grid\Filters\AdsFilters;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Service\Grid\ResponseBuilder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use EO\YandexDirect\Repository\YandexDirectRepository;

class AdsController extends FrameworkBundleAdminController
{
    private $tabName  = 'Объявления';
    private $errors   = [];
    private $warnings = [];

    /**
     * List yandex direct ads
     *
     * @param AdsFilters $filters
     * @return Response
     */
    public function indexAction(AdsFilters $filters): Response
    {
        $gridFactory = $this->get('yandex_direct.grid.factory.ads');
        $grid = $gridFactory->getGrid($filters);

        return $this->render('@Modules/eo_yandex_direct/views/templates/admin/ads/index.html.twig', [
            'enableSidebar'          => true,
            'layoutTitle'            => 'Яндекс Директ',
            'headerTabContent'       => $this->getHeaderTabsContent(),
            'layoutHeaderToolbarBtn' => $this->getToolbarButtons(),
            'grid'                   => $this->presentGrid($grid),
        ]);
    }

    /**
     * Edit form
     *
     * @param Request $request
     * @param int $id
     * @return Response
     * @throws \Exception
     */
    public function editAction(Request $request, int $id): Response
    {
        $this->get('yandex_direct.form.data_provider.ads')->setId($id);
        $form = $this->get('yandex_direct.form_handler.ads')->getForm();

        return $this->render('@Modules/eo_yandex_direct/views/templates/admin/ads/form.html.twig', [
            'form' => $form->createView(),
            'enableSidebar' => true,
            'layoutHeaderToolbarBtn' => $this->getToolbarButtons(),
            'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
        ]);
    }

    /**
     * @param Request $request
     * @param int $id
     *
     * @return Response|RedirectResponse
     *
     * @throws \Exception
     */
    public function editProcessAction(Request $request, int $id)
    {
        $formProvider = $this->get('yandex_direct.form.data_provider.ads');
        $formProvider->setId($id);

        /** @var FormHandlerInterface $formHandler */
        $formHandler = $this->get('yandex_direct.form_handler.ads');
        $form = $formHandler->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $saveErrors = $formHandler->save($data);

            if (0 === count($saveErrors)) {
                $this->addFlash('success', $this->trans('Успешно обновлено.', 'Admin.Notifications.Success'));
                return $this->redirectToRoute('admin_yandex_direct_ads_list');
            }

            $this->flashErrors($saveErrors);
        }

        return $this->render('@Modules/eo_yandex_direct/views/templates/admin/ads/form.html.twig', [
            'form' => $form->createView(),
            'enableSidebar' => true,
            'layoutHeaderToolbarBtn' => $this->getToolbarButtons(),
            'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
        ]);
    }

    /**
     * Provides filters functionality.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function searchAction(Request $request): RedirectResponse
    {
        /** @var ResponseBuilder $responseBuilder */
        $responseBuilder = $this->get('prestashop.bundle.grid.response_builder');

        return $responseBuilder->buildSearchResponse(
            $this->get('yandex_direct.grid.definition.factory.ads'),
            $request,
            AdsGridDefinitionFactory::GRID_ID,
            'admin_yandex_direct_ads_list'
        );
    }

    /**
     * Check url action
     *
     * @param int $id
     * @return RedirectResponse|Response
     * @throws \Exception
     */
    public function checkAction(int $id, AdsFilters $filters)
    {
        $filtersList = $filters->getFilters();

        /** @var YandexDirectRepository $repository */
        $repository = $this->get('yandex_direct.repository')->setShopGroup($filtersList['id_shop_group'] ?? 1);

        $this->handleRequest(function () use ($repository, $id) {
            $repository->checkUrl($id);
        }, 'Не удалось проверить объявление.');

        $this->handleNotification();

        return $this->redirectToRoute('admin_yandex_direct_ads_list');
    }

    /**
     * Check url action
     *
     * @param int $id
     * @return RedirectResponse|Response
     * @throws \Exception
     */
    public function syncAction(int $id, AdsFilters $filters)
    {
        $filtersList = $filters->getFilters();

        /** @var YandexDirectRepository $repository */
        $repository = $this->get('yandex_direct.repository')->setShopGroup($filtersList['id_shop_group'] ?? 1);

        $this->handleRequest(function () use ($repository, $id) {
            $repository->syncUrl($id);
        }, 'Не удалось синхронизировать объявление.');

        $this->handleRequest(function () use ($repository, $id) {
            $repository->updateAdRecord($id);
        });

        $this->handleNotification();

        return $this->redirectToRoute('admin_yandex_direct_ads_list');
    }

    /**
     * Resume ad
     *
     * @param int $id
     * @return RedirectResponse|Response
     * @throws \Exception
     */
    public function resumeAction(int $id, AdsFilters $filters)
    {
        $filtersList = $filters->getFilters();

        /** @var YandexDirectRepository $repository */
        $repository = $this->get('yandex_direct.repository')->setShopGroup($filtersList['id_shop_group'] ?? 1);

        $this->handleRequest(function () use ($repository, $id) {
            $repository->resumeAd($id);
        }, 'Не удалось запустить объявление.');

        $this->handleRequest(function () use ($repository, $id) {
            $repository->updateAdRecord($id);
        });

        $this->handleNotification();

        return $this->redirectToRoute('admin_yandex_direct_ads_list');
    }

    /**
     * Suspend ad
     *
     * @param int $id
     * @return RedirectResponse|Response
     * @throws \Exception
     */
    public function suspendAction(int $id, AdsFilters $filters)
    {
        $filtersList = $filters->getFilters();

        /** @var YandexDirectRepository $repository */
        $repository = $this->get('yandex_direct.repository')->setShopGroup($filtersList['id_shop_group'] ?? 1);

        $this->handleRequest(function () use ($repository, $id) {
            $repository->suspendAd($id);
        }, 'Не удалось остановить объявление.');

        $this->handleRequest(function () use ($repository, $id) {
            $repository->updateAdRecord($id);
        });

        $this->handleNotification();

        return $this->redirectToRoute('admin_yandex_direct_ads_list');
    }

    /**
     * Check ads on bulk action
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function bulkCheckAction(AdsFilters $filters, Request $request)
    {
        $ids = $this->getBulkFromRequest($filters, $request);
        $filtersList = $filters->getFilters();

        /** @var YandexDirectRepository $repository */
        $repository = $this->get('yandex_direct.repository')->setShopGroup($filtersList['id_shop_group'] ?? 1);

        $results = $repository->checkBulk($ids);

        $this->handleBulkNotification($results, 'Успешная проверка', 'Ошибки при проверке');

        return $this->redirectToRoute('admin_yandex_direct_ads_list');
    }

    /**
     * Export ads on bulk action
     *
     * @param Request $request
     *
     * @return Response
     */
    public function bulkExportAction(AdsFilters $filters, Request $request)
    {
        $ids = $this->getBulkFromRequest($filters, $request);
        $filtersList = $filters->getFilters();

        /** @var YandexDirectReposiFtory $repository */
        $repository = $this->get('yandex_direct.repository')->setShopGroup($filtersList['id_shop_group'] ?? 1);
        $repository->export($ids);

        return new Response();
    }

    /**
     * Sync ads on bulk action
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function bulkSyncAction(AdsFilters $filters, Request $request)
    {
        $ids = $this->getBulkFromRequest($filters, $request);
        $filtersList = $filters->getFilters();

        /** @var YandexDirectRepository $repository */
        $repository = $this->get('yandex_direct.repository')->setShopGroup($filtersList['id_shop_group'] ?? 1);

        $results = $repository->syncBulk($ids);

        $this->handleBulkNotification($results, 'Успешная синхронизация', 'Ошибки при синхронизации');

        return $this->redirectToRoute('admin_yandex_direct_ads_list');
    }

    /**
     * Resume ads on bulk action
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function bulkResumeAction(AdsFilters $filters, Request $request)
    {
        $ids = $this->getBulkFromRequest($filters, $request);
        $filtersList = $filters->getFilters();

        /** @var YandexDirectRepository $repository */
        $repository = $this->get('yandex_direct.repository')->setShopGroup($filtersList['id_shop_group'] ?? 1);

        $results = $repository->resumeBulk($ids);

        $this->handleBulkNotification($results, 'Успешный запуск', 'Ошибки при запуске');

        return $this->redirectToRoute('admin_yandex_direct_ads_list');
    }

    /**
     * Suspend ads on bulk action
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function bulkSuspendAction(AdsFilters $filters, Request $request)
    {
        $ids = $this->getBulkFromRequest($filters, $request);
        $filtersList = $filters->getFilters();

        /** @var YandexDirectRepository $repository */
        $repository = $this->get('yandex_direct.repository')->setShopGroup($filtersList['id_shop_group'] ?? 1);

        $results = $repository->suspendBulk($ids);

        $this->handleBulkNotification($results, 'Успешная остановка', 'Ошибки при отсановке');

        return $this->redirectToRoute('admin_yandex_direct_ads_list');
    }

    /**
     * Get bulk of ids from bulk request
     *
     * @param  AdsFilters $filters
     * @param  Request    $request
     * @return array
     */
    private function getBulkFromRequest(AdsFilters $filters, Request $request)
    {
        if ($request->query->get('select-all') === 'true') {
            $gridFactory = $this->get('yandex_direct.grid.data.factory.ads');
            $filters->remove('limit');
            $filters->remove('offset');
            $grid = $gridFactory->getData($filters)->getRecords();
            $gridArray = (array) $grid;
            $items = (array_column($gridArray[key($gridArray)], 'id'));
        } else {
            $items = $request->request->get('ads_bulk');
        }

        if (!is_array($items)) {
            return [];
        }

        return $items;
    }

    /**
     * Handle notifications for bulk request
     *
     * @param  array   $results
     * @param  string  $successMessage
     * @param  string  $errorMessage
     * @return void
     */
    public function handleBulkNotification(
        $results,
        $successMessage = 'Успешная операция',
        $errorMessage = 'Ошибки при выполнении операции'
    ): void {
        if (count($results['success'])) {
            $ids = implode(',', array_keys($results['success']));
            $this->addFlash('success', "{$successMessage}: {$ids}");
        }

        if (count($results['errors'])) {
            $message = "{$errorMessage}: <br>" . implode('<br>', $results['errors']);
            $this->flashErrors([$message]);
        }
    }

    /**
     * Handle notifications for single request
     *
     * @return void
     */
    public function handleNotification(): void
    {
        if (0 === count($this->errors)) {
            foreach ($this->warnings as $warning) {
                $this->addFlash('warning', $warning);
            }

            $this->addFlash('success', 'Успешно обновлено.');
        } else {
            $this->flashErrors($this->errors);
        }
    }

    /**
     * Single request wrapper
     *
     * @param  Closure $fn
     * @param  string  $message
     * @return void
     */
    public function handleRequest(Closure $fn, $message = 'Не удалось выполнить операцию.'): void
    {
        try {
            $fn();
        } catch (\Throwable $th) {
            if ($th->getCode() === 1) {
                $this->warnings[] = $th->getMessage();
            } else {
                $this->errors[] = [
                    'key' => "{$message} %s",
                    'domain' => 'Admin.Catalog.Notification',
                    'parameters' => [$th->getMessage()],
                ];
            }
        }
    }

    /**
     * Gets the header tabs content.
     *
     * @return string
     */
    private function getHeaderTabsContent(): string
    {
        $smarty = Context::getContext()->smarty;

        $smarty->assign([
            'tabs' => Module::getInstanceByName('eo_yandex_direct')->getTopTabs($this->tabName),
        ]);

        return $smarty->fetch('module:eo_yandex_direct/views/templates/admin/header_tabs.tpl');
    }

    /**
     * Gets the header toolbar buttons.
     *
     * @return array
     */
    private function getToolbarButtons()
    {
        return [];
    }
}
