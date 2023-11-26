<?php

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

class eo_yandex_direct extends Module
{
    private $tabRepository = null;
    private $topTabs       = [];

    public function __construct()
    {
        $this->name = 'eo_yandex_direct';
        $this->tab = 'administration';
        $this->version = '1.1';
        $this->displayName = 'Яндекс Директ';
        $this->author = 'Express Office';
        $this->description = 'Управление объявлениями Яндекс Директ';

        $container = SymfonyContainer::getInstance();
        $router = $container->get('router');

        if ($container) {
            $this->tabRepository = $container->get('prestashop.core.admin.tab.repository');
        }

        if ($this->isInstalled('eo_yandex_direct')) {
            $this->topTabs = [
                [
                    'name' => 'Объявления',
                    'link' => Context::getContext()->link->getAdminLink('AdminYandexDirectAds'),
                ],
            ];
        }

        parent::__construct();
    }

    public function install()
    {
        if (!parent::install()
            || !$this->installTab()
            || !$this->installTables()
        ) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall()
            || !$this->uninstallTab()
            || !$this->uninstallTables()
        ) {
            return false;
        }

        return true;
    }

    private function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminYandexDirectAds';
        $tab->name = array();
        $tab->name = 'Яндекс Директ';
        $tab->id_parent = (int) $this->tabRepository->findOneIdByClassName('AdminAdvancedParameters');
        $tab->module = $this->name;

        return $tab->save();
    }

    public function uninstallTab()
    {
        $tabId = (int) $this->tabRepository->findOneIdByClassName('AdminYandexDirectAds');
        if (!$tabId) {
            return true;
        }

        $tab = new Tab($tabId);

        return $tab->delete();
    }

    public function installTables()
    {
        return Db::getInstance()->execute(
            "CREATE TABLE IF NOT EXISTS `eo_yandex_direct_campaigns` (
                `id_campaign`        BIGINT unsigned NOT NULL,
                `name`               varchar(255) NOT NULL,
                PRIMARY KEY          (`id_campaign`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            CREATE TABLE IF NOT EXISTS `eo_yandex_direct_ad_groups` (
                `id_group`           BIGINT unsigned NOT NULL,
                `id_campaign`        BIGINT unsigned NOT NULL,
                `name`               varchar(255) NOT NULL,
                `status`             varchar(255) NOT NULL,
                `type`               varchar(255) NOT NULL,
                PRIMARY KEY          (`id_group`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            CREATE TABLE IF NOT EXISTS `eo_yandex_direct_ads` (
                `id_ad`              BIGINT unsigned NOT NULL,
                `id_group`           BIGINT unsigned NOT NULL,
                `id_campaign`        BIGINT unsigned NOT NULL,
                `id_entity`          int(11) unsigned NOT NULL,
                `entity_type`        ENUM('none', 'product', 'category', 'cms', 'brand'),
                `ad_url`             varchar(1024) NOT NULL,
                `entity_url`         varchar(1024) NOT NULL,
                `error`              varchar(255) NOT NULL,
                `status`             varchar(255) NOT NULL,
                `state`              varchar(255) NOT NULL,
                `title`              varchar(255) NOT NULL,
                PRIMARY KEY          (`id_ad`, `id_campaign`, `id_group`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
        );
    }

    public function uninstallTables()
    {
        return Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `eo_yandex_direct_ads`;'
        );
    }

    public function getTopTabs($name)
    {
        foreach ($this->topTabs as $key => $tab) {
            $this->topTabs[$key]['active'] = $tab['name'] === $name ? true : false;
        }

        return $this->topTabs;
    }
}
