<?php
/**
 * 2007-2018 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2018 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace EO\YandexDirect\Form;

use EO\YandexDirect\Model\YandexDirectAd;
use EO\YandexDirect\Repository\YandexDirectRepository;
use Exception;
use PrestaShop\PrestaShop\Core\Addon\Module\ModuleRepository;
use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;

/**
 * Class AdsFormDataProvider.
 */
class AdsFormDataProvider implements FormDataProviderInterface
{
    /**
     * @var int|null
     */
    private $id;

    /**
     * @var YandexDirectRepository
     */
    private $repository;

    /**
     * @var ModuleRepository
     */
    private $moduleRepository;

    /**
     * AdsFormDataProvider constructor.
     *
     * @param AdsRepository $repository
     * @param ModuleRepository $moduleRepository
     * @param Router $router
     */
    public function __construct(
        YandexDirectRepository $repository,
        ModuleRepository $moduleRepository
    ) {
        $this->repository = $repository;
        $this->moduleRepository = $moduleRepository;
    }

    /**
     * @return array
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getData()
    {
        if (null === $this->id) {
            return [];
        }

        $ad = new YandexDirectAd($this->id);

        return [
            'ads' => [
                'id_ad'  => $ad->id,
                'title'  => $ad->title,
                'ad_url' => $ad->ad_url,
            ]
        ];
    }

    /**
     * @param array $data
     *
     * @return array
     *
     * @throws \PrestaShop\PrestaShop\Adapter\Entity\PrestaShopDatabaseException
     */
    public function setData(array $data): array
    {
        $adsData = $data['ads'];
        $errors = $this->validate($adsData);

        if (!empty($errors)) {
            return $errors;
        }

        $params = [
            'Id'       => $adsData['id_ad'],
            'TextAd'   => [
                'Href' => $adsData['ad_url'],
            ],
        ];

        $errors = [];

        try {
            $requestedAdData = $this->repository->getAd($adsData['id_ad']);
            $parsedUrl = parse_url($requestedAdData['TextAd']['Href'] ?? '');
            if ($parsedUrl['query'] ?? false) {
                $params['TextAd']['Href'] .= "?{$parsedUrl['query']}";
            }
            $this->repository->updateAd($params);
            $this->repository->updateAdRecord($adsData['id_ad']);
        } catch (\Throwable $th) {
            $errors[] = $th->getMessage();
        }

        return $errors;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     *
     * @return AdsFormDataProvider
     */
    public function setId(?int $id): AdsFormDataProvider
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    private function validate(array $data): array
    {
        $errors = [];

        return $errors;
    }
}
