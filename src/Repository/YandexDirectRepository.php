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

namespace EO\YandexDirect\Repository;

use Doctrine\DBAL\Connection;
use Symfony\Component\Translation\TranslatorInterface;
use EO\YandexDirect\Classes\AdsApi;
use EO\YandexDirect\Model\YandexDirectAd;
use EO\YandexDirect\Model\YandexDirectCampaign;
use DbQuery;
use Db;
use Link;
use Shop;

/**
 * Class YandexDirectRepository.
 */
class YandexDirectRepository
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $dbPrefix;

    /**
     * @var array
     */
    private $languages;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var AdsApi
     */
    private $adsApi;

    /**
     * YandexDirectRepository constructor.
     *
     * @param Connection $connection
     * @param TranslatorInterface $translator
     */
    public function __construct(
        Connection $connection,
        TranslatorInterface $translator
    ) {
        $this->connection = $connection;
        $this->translator = $translator;
        $this->adsApi     = new AdsApi();
    }

    /**
     * setShopGroup
     *
     * @param  int $shopGroupId
     * @return YandexDirectRepository
     */
    public function setShopGroup($shopGroupId = 1): YandexDirectRepository
    {
        $this->adsApi->setApiCredentials($shopGroupId);

        return $this;
    }

    /**
     * Check Url
     *
     * @param int $id
     * @return bool
     */
    public function checkUrl(int $id): bool
    {
        return $this->updateAdRecord($id);
    }

    /**
     * Sync Url
     *
     * @param int $id
     * @return bool
     */
    public function syncUrl(int $id): bool
    {
        try {
            $ad = new YandexDirectAd($id);
        } catch (\PrestaShopException $e) {
            throw new \PrestaShopException('Ошибка получения записи', 0, $e);
        }

        if (
            !$ad->error
            || !in_array($ad->error, [
                'ERROR_PRODUCT_REDIRECT_TO_CATEGORY',
                'ERROR_PRODUCT_REDIRECT_TO_OTHER',
                'ERROR_URL_MISMATCH',
            ])
        ) {
            $statusList = [
                YandexDirectAd::ERROR_PRODUCT_REDIRECT_TO_CATEGORY,
                YandexDirectAd::ERROR_PRODUCT_REDIRECT_TO_OTHER,
                YandexDirectAd::ERROR_URL_MISMATCH,
            ];

            throw new \PrestaShopException('Статус объявления должен быть: [' . implode(',', $statusList) . ']', 1);
        }

        $params = [
            'Id'       => $ad->id_ad,
            'TextAd'   => [
                'Href' => $ad->entity_url,
            ],
        ];

        $requestedAdData = $this->getAd($ad->id_ad);
        $parsedUrl = parse_url($requestedAdData['TextAd']['Href'] ?? '');

        if ($parsedUrl['query'] ?? false) {
            $params['TextAd']['Href'] .= "?{$parsedUrl['query']}";
        }

        return $this->updateAd($params);
    }

    /**
     * Resume Ad
     *
     * @param int $id
     * @return bool
     */
    public function resumeAd(int $id): bool
    {
        try {
            $result = $this->adsApi->resume([$id]);
        } catch (\PrestaShopException $e) {
            throw new \PrestaShopException('Ошибка выполнения запроса', 0, $e);
        }

        $result = end($result);

        if (!$result) {
            return false;
        }

        if ($result['Warnings'] ?? false) {
            $warning = end($result['Warnings']);
            throw new \PrestaShopException($warning['Message'], 1);
        }

        return true;
    }

    /**
     * Suspend Ad
     *
     * @param int $id
     * @return bool
     */
    public function suspendAd(int $id): bool
    {
        try {
            $result = $this->adsApi->suspend([$id]);
        } catch (\PrestaShopException $e) {
            throw new \PrestaShopException('Ошибка выполнения запроса', 0, $e);
        }

        $result = end($result);

        if (!$result) {
            return false;
        }

        if ($result['Warnings'] ?? false) {
            $warning = end($result['Warnings']);
            throw new \PrestaShopException($warning['Message'], 1);
        }

        return true;
    }

    /**
     * Update Ad
     *
     * @param array $adsList
     * @return bool
     */
    public function updateAd(array $adData): bool
    {
        try {
            $result = $this->adsApi->update([$adData]);
        } catch (\PrestaShopException $e) {
            throw new \PrestaShopException('Ошибка выполнения запроса', 0, $e);
        }

        $result = end($result);

        if (!$result) {
            return false;
        }

        if ($result['Warnings'] ?? false) {
            $warning = end($result['Warnings']);
            throw new \PrestaShopException($warning['Message'], 1);
        }

        return true;
    }

    /**
     * Get Ad
     *
     * @param int $id
     * @return array
     */
    public function getAd(int $id): array
    {
        $params = [
            'FieldNames' => [
                'Id',
                'CampaignId',
                'AdGroupId',
                'Status',
                'State',
                'Type',
            ],
            'TextAdFieldNames' => [
                'Title',
                'Href',
            ],
            'SelectionCriteria' => [
                'Ids' => [$id]
            ],
        ];

        try {
            $result = $this->adsApi->get($params);
        } catch (\PrestaShopException $e) {
            throw new \PrestaShopException('Ошибка получения данных из API', 0, $e);
        }

        $result = end($result);

        return $result ?: [];
    }

    /**
     * Update db record
     *
     * @param int $id
     * @return bool
     */
    public function updateAdRecord(int $id): bool
    {
        $params = [
            'FieldNames' => [
                'Id',
                'CampaignId',
                'AdGroupId',
                'Status',
                'State',
                'Type',
            ],
            'TextAdFieldNames' => [
                'Title',
                'Href',
            ],
            'SelectionCriteria' => [
                'Ids' => [$id]
            ],
        ];

        try {
            $result = $this->adsApi->get($params);
        } catch (\PrestaShopException $e) {
            throw new \PrestaShopException('Ошибка выполнения запроса', 0, $e);
        }

        $result = end($result);

        if (!$result) {
            return false;
        }

        try {
            $ad              = new YandexDirectAd($id);
            $ad->id_group    = $result['AdGroupId'];
            $ad->id_campaign = $result['CampaignId'];
            $ad->status      = $result['Status'];
            $ad->state       = $result['State'];
            $ad->title       = $result['TextAd']['Title'];
            $ad->date_update = date('Y-m-d H:i:s');

            $url = strtok($result['TextAd']['Href'], '?');

            if ($ad->entity_url != $url) {
                $error = '';
                $entity  = $this->getEntityData($url);
                $isValid = YandexDirectAd::validateEntityUrl($entity, $url, $error);

                $ad->id_entity   = $entity['id_entity'] ?? 0;
                $ad->entity_type = $entity['entity_type'] ?? 'none';
                $ad->entity_url  = $entity['entity_url'] ?? '';
                $ad->ad_url      = $url;
                $ad->error       = !$isValid && $error ? $error : '';
            }

            $ad->save();
        } catch (\PrestaShopException $e) {
            throw new \PrestaShopException('Ошибка обновления записи. ' . $e->getMessage(), 0, $e);
        }

        return true;
    }

     /**
     * Check ads bulk
     *
     * @param array $ids
     * @return array
     */
    public function checkBulk(array $ids): array
    {
        $success = $errors = [];

        foreach ($ids as $id) {
            try {
                $success[$id] = $this->checkUrl($id);
            } catch (\PrestaShopException $e) {
                $errors[$id] = "[{$id}]: " . $e->getMessage();
            }
        }

        return [
            'success' => $success,
            'errors'  => $errors,
        ];
    }

    /**
     * Sync ads bulk
     *
     * @param array $ids
     * @return array
     */
    public function syncBulk(array $ids): array
    {
        $success = $errors = [];

        foreach ($ids as $id) {
            try {
                $success[$id] = $this->syncUrl($id);
                $this->updateAdRecord($id);
            } catch (\PrestaShopException $e) {
                $errors[$id] = "[{$id}]: " . $e->getMessage();
            } finally {
                $this->updateAdRecord($id);
            }
        }

        return [
            'success' => $success,
            'errors'  => $errors,
        ];
    }

    /**
     * Resume ads bulk
     *
     * @param array $ids
     * @return array
     */
    public function resumeBulk(array $ids): array
    {
        $success = $errors = [];

        foreach ($ids as $id) {
            try {
                $success[$id] = $this->resumeAd($id);
                $this->updateAdRecord($id);
            } catch (\PrestaShopException $e) {
                $errors[$id] = "[{$id}]: " . $e->getMessage();
            } finally {
                $this->updateAdRecord($id);
            }
        }

        return [
            'success' => $success,
            'errors'  => $errors,
        ];
    }

    /**
     * Suspend ads bulk
     *
     * @param array $ids
     * @return array
     */
    public function suspendBulk(array $ids): array
    {
        $success = $errors = [];

        foreach ($ids as $id) {
            try {
                $success[$id] = $this->suspendAd($id);
            } catch (\PrestaShopException $e) {
                $errors[$id] = "[{$id}]: " . $e->getMessage();
            } finally {
                $this->updateAdRecord($id);
            }
        }

        return [
            'success' => $success,
            'errors'  => $errors,
        ];
    }

    /**
     * Export data
     *
     * @param array $ids
     * @return void
     */
    public function export(array $ids): void
    {
        $xls = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $xls->createSheet();
        $xls->setActiveSheetIndex(0);
        $sheet = $xls->getActiveSheet();

        $row = 1;

        $headers = [
            'A' => 'Название компании',
            'B' => 'ID',
            'C' => 'ID сущности',
            'D' => 'Тип сущности',
            'E' => 'URL объявления',
            'F' => 'URL сущности',
            'G' => 'Ошибка соответствия',
            'H' => 'Статус объявления',
            'I' => 'Cостояние объявления',
            'J' => 'Название',
        ];

        foreach ($headers as $key => $header) {
            $sheet->setCellValue("{$key}{$row}", $header);
        }

        if ($ids) {
            $campaignCache = [];

            foreach ($ids as $id) {
                $row++;

                $adRecord = new YandexDirectAd($id);

                if (!is_object($adRecord) || !$adRecord->id) {
                    continue;
                }

                $campaignRecord = $campaignCache[$adRecord->id_campaign] ?? [];

                if (!$campaignRecord) {
                    $campaignRecord = new YandexDirectCampaign($adRecord->id_campaign);
                    $campaignCache[$adRecord->id_campaign] = $campaignRecord;
                }

                $data = [
                    'A' => $campaignRecord->name,
                    'B' => $adRecord->id_ad,
                    'C' => $adRecord->id_entity,
                    'D' => YandexDirectAd::getEntityChoices()[$adRecord->entity_type],
                    'E' => $adRecord->ad_url,
                    'F' => $adRecord->entity_url,
                    'G' => YandexDirectAd::getErrorChoices()[$adRecord->error],
                    'I' => YandexDirectAd::getStateChoices()[$adRecord->state],
                    'H' => YandexDirectAd::getStatusChoices()[$adRecord->status],
                    'J' => $adRecord->title,
                ];

                foreach ($data as $key => $value) {
                    $sheet->setCellValue("{$key}{$row}", $value);
                }
            }
        }

        $date = date('d_m_Y');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="yandex_direct_export_' . $date . '.xlsx"');

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($xls, 'Xlsx');
        $writer->save('php://output');

        return;
    }

    /**
     * getEntityData
     *
     * @param  string $url
     * @return array
     */
    public static function getEntityData(string $url)
    {
        $link     = new Link();
        $urlParts = parse_url($url);
        $domain = $urlParts['domain'] ?? str_replace('www.', '', $urlParts['host']);
        $shopId   = Shop::getShopIdByDomain($domain);

        preg_match('/\/([^\/]+)\/?$/', $urlParts['path'], $linkRewrite);
        $linkRewrite = $linkRewrite ? end($linkRewrite) : '';

        if (!$linkRewrite) {
            return [];
        }

        $tableData = [
            'product'      => [
                'id'       => 'id_product',
                'from'     => 'product_lang',
                'shop'     => true,
                'link'     => 'getProductLink'
            ],
            'category'     => [
                'id'       => 'id_category',
                'from'     => 'category_lang',
                'shop'     => true,
                'link'     => 'getCategoryLink'
            ],
            'cms'          => [
                'id'       => 'id_cms',
                'from'     => 'cms_lang',
                'shop'     => true,
                'link'     => 'getCMSLink'
            ],
            'manufacturer' => [
                'id'       => 'id_manufacturer',
                'from'     => 'manufacturer',
                'shop'     => false,
                'link'     => 'getManufacturerLink'
            ],
        ];

        foreach ($tableData as $tableType => $data) {
            $query = (new DbQuery())
                ->select("{$data['id']} as id, link_rewrite")
                ->from($data['from'])
                ->where("link_rewrite = '{$linkRewrite}'")
            ;

            if ($data['shop']) {
                $query->where("id_shop = {$shopId}");
            }

            $result = Db::getInstance()->getRow($query);

            if ($result) {
                $entityLink = $link->{$data['link']}($result['id']);

                if (strpos($urlParts['path'], 'brand') !== false) {
                    $entityLink = $urlParts['path'];
                }

                return [
                    'id_entity'   => $result['id'],
                    'entity_type' => $tableType,
                    'entity_url'  => $entityLink,
                ];
            }
        }

        return [];
    }
}
