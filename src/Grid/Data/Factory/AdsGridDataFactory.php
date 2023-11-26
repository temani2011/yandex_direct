<?php
/**
 * 2007-2019 PrestaShop and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace EO\YandexDirect\Grid\Data\Factory;

use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;
use PrestaShop\PrestaShop\Core\Grid\Data\Factory\GridDataFactoryInterface;
use EO\YandexDirect\Model\YandexDirectAd;
use ShopUrl;

/**
 * AdsGridDataFactory
 */
final class AdsGridDataFactory implements GridDataFactoryInterface
{
    /**
     * @var GridDataFactoryInterface
     */
    private $adsDataFactory;

    /**
     * @param GridDataFactoryInterface $adsDataFactory
     */
    public function __construct(GridDataFactoryInterface $adsDataFactory)
    {
        $this->adsDataFactory = $adsDataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(SearchCriteriaInterface $searchCriteria)
    {
        $adsData = $this->adsDataFactory->getData($searchCriteria);

        $modifiedRecords = $this->applyModification(
            $adsData->getRecords()->all()
        );

        return new GridData(
            new RecordCollection($modifiedRecords),
            $adsData->getRecordsTotal(),
            $adsData->getQuery()
        );
    }

    /**
     * @param array $ads
     *
     * @return array
     */
    private function applyModification(array $adsList)
    {
        $entityTypeList = YandexDirectAd::getEntityChoices();
        $errorList      = YandexDirectAd::getErrorChoices();
        $statusList     = YandexDirectAd::getStatusChoices();
        $stateList      = YandexDirectAd::getStateChoices();

        foreach ($adsList as $key => $ad) {
            $adsList[$key]['entity_type'] = $ad['entity_type'] ? $entityTypeList[$ad['entity_type']] : '';
            $adsList[$key]['error']       = $ad['error']       ? $errorList[$ad['error']]            : '';
            $adsList[$key]['status']      = $ad['status']      ? $statusList[$ad['status']]          : '';
            $adsList[$key]['state']       = $ad['state']       ? $stateList[$ad['state']]            : '';

            if ($ad['ad_url']) {
                $urlParts = parse_url($ad['ad_url']);
                $shopUrlId = ShopUrl::getShopUrlId($urlParts['host'], true);
                $shopUrl = new ShopUrl($shopUrlId);
                $adsList[$key]['product_link'] = 'https://' . $shopUrl->domain . $ad['entity_url'];
            }
        }

        return $adsList;
    }
}
