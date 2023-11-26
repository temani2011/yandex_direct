<?php

namespace EO\YandexDirect\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\ProgressBar;
use EO\YandexDirect\Model\YandexDirectCampaign;
use EO\YandexDirect\Model\YandexDirectGroup;
use EO\YandexDirect\Model\YandexDirectAd;
use Db;
use Shop;
use Link;
use EOCache;
use DbQuery;
use FileLogger;

class YandexDirectCommand extends ContainerAwareCommand
{
    private $log;
    //https://oauth.yandex.ru/authorize?response_type=token&client_id=0ef7bf05377a4480a6af05fd78516f12
    private $token = '';
    private $clientId = '';
    private $clientSecret = '';
    private $clientLogin = '';
    private $headersData = [];
    private $shopGroupId = 1;

    private const TYPE_CAMPAIGN = 'getCampaigns';
    private const TYPE_GROUP    = 'getAdGroups';

    protected function configure()
    {
        $this
            ->setName('eo:yandex_direct')
            ->addArgument('type', InputArgument::OPTIONAL, 'Name for operation type', 'ads')
            ->addOption('update', 'u', InputOption::VALUE_OPTIONAL, 'Update option', true)
            ->addOption('shop', 's', InputOption::VALUE_OPTIONAL, 'Shop option', true)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
        ini_set('memory_limit', '4096M');
        ini_set('max_execution_time', 0);

        $this->log = new FileLogger();
        $this->log->setFilename(_PS_ROOT_DIR_ . '/log/YandexDirect.log');
        $this->headersData = [
            "Client-Login: {$this->clientLogin}",
            "Authorization: Bearer {$this->token}",
            'Content-Type: application/json',
        ];

        // $cacheKey = 'token-yandex-direct';
        // if (!$this->token = EOCache::getInstance()->get($cacheKey, 3600)) {
        //     $this->token = $this->getAuthToken();
        //     EOCache::getInstance()->set($cacheKey, $this->token, 3600);
        // }

        $type   = $input->getArgument('type');
        $update = $input->getOption('update') ?? true;
        $shop   = $input->getOption('shop') ?? 'express';

        $this->getTokenByShop($shop);

        switch ($type) {
            case 'campaigns':
                $this->getCampaigns($output, $update);
                break;
            case 'groups':
                $this->getAdGroups($output, $update);
                break;
            case 'ads':
                $this->getAds($output, $update);
                break;
            default:
                break;
        }

        $output->writeln('');
        $output->writeln('Finish.');

        return 0;
    }

    public function getAds(OutputInterface $output, $update = false, $method = self::TYPE_CAMPAIGN)
    {
        $adsDb = YandexDirectAd::getAll();
        $adsDb = array_column($adsDb, null, 'id_ad');
        $columnId = $method === self::TYPE_CAMPAIGN ? 'id_campaign' : 'id_group';

        if (!$update && $adsDb) {
            return $adsDb;
        }

        $data = $this->{$method}($output, $update);

        if (!$data) {
            return [];
        }

        $dataToDelete = array_column($data, null, 'Id');
        $dataToDelete = array_intersect(array_keys($adsDb), array_keys($data));

        if ($dataToDelete) {
            Db::getInstance()->delete('yandex_direct_ads', 'id_ad IN (' . implode(',', $dataToDelete) . ')');
        }

        // $data = array_slice($data, 0, 100);

        foreach ($data as $item) {
            $page = 0;

            do {
                $ads = $this->requestAds($item[$columnId], $method, $page);

                $dataToInsert = [];

                $progress = new ProgressBar($output, count($ads));
                $progress->start();
                $progress->setRedrawFrequency(1);

                foreach ($ads as $ad) {
                    $progress->advance();

                    if (isset($adsDb[$ad['Id']])) {
                        $dataToUpdate = [
                            'id_group'    => $ad['AdGroupId'],
                            'id_campaign' => $ad['CampaignId'],
                            'status'      => $ad['Status'],
                            'state'       => $ad['State'],
                            'title'       => $ad['TextAd']['Title'],
                            'date_update' => date(),
                        ];

                        $url = strtok($ad['TextAd']['Href'], '?');

                        if ($adsDb[$ad['Id']]['ad_url'] != $url) {
                            $error = '';
                            $entity  = $this->getEntityData($url);
                            $isValid = YandexDirectAd::validateEntityUrl($entity, $url, $error);

                            if ($entity) {
                                $dataToUpdate['id_entity']   = $entity['id_entity'] ?? 0;
                                $dataToUpdate['entity_type'] = $entity['entity_type'] ?? 'none';
                                $dataToUpdate['entity_url']  = $entity['entity_url'] ?? '';
                            }

                            $dataToUpdate['ad_url']      = $url;
                            $dataToUpdate['error']       = !$isValid && $error ? $error : '';
                        }

                        Db::getInstance()->update('yandex_direct_ads', $dataToUpdate, "id_ad = {$ad['Id']}");
                        continue;
                    }

                    $error   = '';
                    $url     = strtok($ad['TextAd']['Href'], '?');
                    $entity  = $this->getEntityData($url);
                    $isValid = YandexDirectAd::validateEntityUrl($entity, $url, $error);

                    $data = [
                        'id_ad'         => $ad['Id'],
                        'id_group'      => $ad['AdGroupId'],
                        'id_campaign'   => $ad['CampaignId'],
                        'id_entity'     => $entity['id_entity'] ?? 0,
                        'entity_type'   => $entity['entity_type'] ?? 'none',
                        'entity_url'    => $entity['entity_url'] ?? '',
                        'ad_url'        => $url,
                        'error'         => !$isValid && $error ? $error : '',
                        'status'        => $ad['Status'],
                        'state'         => $ad['State'],
                        'title'         => $ad['TextAd']['Title'],
                        'id_shop_group' => $this->shopGroupId,
                        'date_update'   => date(),
                    ];

                    $dataToInsert[] = $data;
                    $adsDb[$ad['Id']] = $data;
                }

                if ($dataToInsert) {
                    Db::getInstance()->insert('yandex_direct_ads', $dataToInsert);
                }

                $page++;
            } while (!$ads);
        }

        $progress->clear();

        return $adsDb;
    }

    public function getAdGroups(OutputInterface $output, $update = false)
    {
        $groupsDb = YandexDirectGroup::getAll();
        $groupsDb = array_column($groupsDb, null, 'id_group');

        if (!$update && $groupsDb) {
            return $groupsDb;
        }

        $campaigns = $this->getCampaigns($output, $update);

        if (!$campaigns) {
            return [];
        }

        $dataToInsert = [];
        foreach ($campaigns as $campaign) {
            $groups = $this->requestGroups($campaign['id_campaign']);

            $progress = new ProgressBar($output, count($groups));
            $progress->start();
            $progress->setRedrawFrequency(1);

            foreach ($groups as $group) {
                $progress->advance();

                if (isset($groupsDb[$group['Id']])) {
                    continue;
                }

                $data = [
                    'id_group'    => $campaign['Id'],
                    'id_campaign' => $campaign['CampaignId'],
                    'name'        => $campaign['Name'],
                    'status'      => $campaign['Status'],
                    'type'        => $campaign['Type'],
                ];

                $dataToInsert[] = $data;
                $groupsDb[$campaign['Id']] = $data;
            }
        }

        if ($dataToInsert) {
            Db::getInstance()->insert('yandex_direct_ad_groups', $dataToInsert);
        }

        $progress->clear();

        return $groupsDb;
    }

    public function getCampaigns(OutputInterface $output, $update = false)
    {
        $campaignsDb = YandexDirectCampaign::getAll();
        $campaignsDb = array_column($campaignsDb, null, 'id_campaign');

        if (!$update && $campaignsDb) {
            return $campaignsDb;
        }

        $campaigns = $this->requestCampaigns();

        $progress = new ProgressBar($output, count($campaigns));
        $progress->start();
        $progress->setRedrawFrequency(1);

        $dataToInsert = [];
        foreach ($campaigns as $campaign) {
            $progress->advance();

            // тестовые
            // if (!in_array($campaign['Id'], ['98294274', '98294585'])) {
            //     continue;
            // }

            if (isset($campaignsDb[$campaign['Id']])) {
                continue;
            }

            $data = [
                'id_campaign' => $campaign['Id'],
                'name'       => $campaign['Name'],
            ];

            $dataToInsert[] = $data;
            $campaignsDb[$campaign['Id']] = $data;
        }

        if ($dataToInsert) {
            Db::getInstance()->insert('yandex_direct_campaigns', $dataToInsert);
        }

        $progress->clear();

        return $campaignsDb;
    }

    public static function getEntityData(string $url)
    {
        $link        = new Link();
        $urlParts    = parse_url($url);
        $shopId      = Shop::getShopIdByDomain($urlParts['domain']);

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

    public function requestAds($id, $method, $page = 0)
    {
        $response = [];

        $postData = [
            'method' => 'get',
            'params' => [
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
            ],
        ];

        $postData['params'] = array_merge($postData['params'], [
            'SelectionCriteria' => [
                ($method === self::TYPE_CAMPAIGN ? 'CampaignIds' : 'AdGroupIds') => [$id],
                'States' => ['OFF', 'ON', 'SUSPENDED'],
            ]
        ]);

        if ($page) {
            $limit = 10000;
            $postData['Page'] = (object) [
                'Limit' => $limit,
                'Offset' => $page * $limit,
            ];
        }

        try {
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL            => 'https://api.direct.yandex.com/json/v5/ads',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_HTTPHEADER     => $this->headersData,
                CURLOPT_POSTFIELDS     => json_encode($postData),
            ]);

            $response = json_decode(curl_exec($curl), true);
            curl_close($curl);
        } catch (\Exception $e) {
            $this->log->logError("While curl request: {$e->getMessage()}");
        }

        if ($this->responseHandler($response, __METHOD__ . "($method)", $id)) {
            return [];
        }

        return $response['result']['Ads'];
    }

    public function requestGroups($campaignId)
    {
        $response = [];

        $postData = [
            'method' => 'get',
            'params' => [
                'SelectionCriteria' => (object) [
                    'CampaignIds' => [$campaignId]
                ],
                'FieldNames' => [
                    'Id',
                    'Name',
                    'CampaignId',
                    'Status',
                    'Type',
                ],
            ],
        ];

        try {
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL            => 'https://api.direct.yandex.com/json/v5/adgroups',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_HTTPHEADER     => $this->headersData,
                CURLOPT_POSTFIELDS     => json_encode($postData),
            ]);

            $response = json_decode(curl_exec($curl), true);
            curl_close($curl);
        } catch (\Exception $e) {
            $this->log->logError("While curl request: {$e->getMessage()}");
        }

        if ($this->responseHandler($response, __METHOD__, $campaignId)) {
            return [];
        }

        return $response['result']['AdGroups'];
    }

    public function requestCampaigns()
    {
        $response = [];

        $postData = [
            'method' => 'get',
            'params' => [
                'SelectionCriteria' => (object) [],
                'FieldNames'        => ['Id', 'Name'],
            ],
        ];

        try {
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL            => 'https://api.direct.yandex.com/json/v5/campaigns',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_HTTPHEADER     => $this->headersData,
                CURLOPT_POSTFIELDS     => json_encode($postData),
            ]);

            $response = json_decode(curl_exec($curl), true);
            curl_close($curl);
        } catch (\Exception $e) {
            $this->log->logError("While curl request: {$e->getMessage()}");
        }

        if ($this->responseHandler($response, __METHOD__)) {
            return [];
        }

        return $response['result']['Campaigns'];
    }

    public function responseHandler($response, $method, $id = null)
    {
        // if (!$response) {
        //     $this->log->logError("Empty response from {$method}" . ($id ? " with ID {$id}" : ""));
        //     return true;
        // }

        if ($response['error'] ?? false) {
            $this->log->logError(
                "Error in {$method} with" . ($id ? " with ID {$id}" : "") . ":\n" . var_export($response['error'], true)
            );
            return true;
        }

        return false;
    }

    protected function getTokenByShop($shop): void
    {
        if ($shop == 'home') {
            $this->clientId = '';
            $this->clientSecret = '';
            $this->clientLogin = '';
            $this->clientToken = '';
            $this->shopGroupId = 3;
        }
    }

    /**
     * Получение токена
     *
     * @return string|null
     */
    protected function getAuthToken(): ?string
    {
        $response = [];

        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => 'https://oauth.yandex.ru/authorize?response_type=token&client_id=' . $this->clientId,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'GET',
            ]);

            $response = json_decode(curl_exec($curl), true);
            curl_close($curl);
        } catch (\Exception $e) {
            $this->log->logError("While curl request: {$e->getMessage()}");
        }

        return $response;
    }
}
