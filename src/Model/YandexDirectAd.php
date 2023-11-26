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

namespace EO\YandexDirect\Model;

use Db;
use Link;
use DbQuery;
use Product;

class YandexDirectAd extends \ObjectModel
{
    public const ERROR_EMPTY_ENTITY_URL             = 'Пустой урл сущности';
    public const ERROR_EMPTY_DIRECT_URL             = 'Пустой урл директ API';
    public const ERROR_URL_MISMATCH                 = 'Урл сущности не совпадает c Direct API';
    public const ERROR_PRODUCT_OUT_OF_STOCK         = 'Товар сменил статус: Ожидается';
    public const ERROR_PRODUCT_ON_REQUEST           = 'Товар сменил статус: На заказ';
    public const ERROR_PRODUCT_UNAVAILABLE          = 'Товар сменил статус: Товар недоступен для заказа';
    public const ERROR_PRODUCT_REDIRECT_TO_CATEGORY = 'Товар имеет 301 редирект на разводящую';
    public const ERROR_PRODUCT_REDIRECT_TO_OTHER    = 'Товар имеет редирект на другой товар';

    /** @var int */
    public $id_ad;

    /** @var int */
    public $id_campaign;

    /** @var int */
    public $id_group;

    /** @var int */
    public $id_entity;

    /** @var string */
    public $ad_url;

    /** @var string */
    public $entity_url;

    /** @var string */
    public $entity_type;

    /** @var string */
    public $status;

    /** @var string */
    public $state;

    /** @var string */
    public $error;

    /** @var string */
    public $title;

    /** @var int */
    public $id_shop_group;

    /** @var DateTime */
    public $date_update;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table'     => 'yandex_direct_ads',
        'primary'   => 'id_ad',
        'multilang' => false,
        'fields'    => [
            'id_campaign'   => ['type' => self::TYPE_INT,    'validate' => 'isInt'],
            'id_group'      => ['type' => self::TYPE_INT,    'validate' => 'isInt'],
            'id_entity'     => ['type' => self::TYPE_INT,    'validate' => 'isInt'],
            'ad_url'        => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'entity_url'    => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'status'        => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'state'         => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'error'         => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'title'         => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'id_shop_group' => ['type' => self::TYPE_INT,    'validate' => 'isInt'],
            'date_update'   => ['type' => self::TYPE_DATE,   'validate' => 'isDateFormat'],
            'entity_type'   => [
                'type'      => self::TYPE_STRING,
                'validate'  => 'isString',
                'values'    => ['none', 'product', 'category', 'cms', 'brand'],
                'default'   => 'product',
            ],
        ],
    ];

    /**
     * {@inheritdoc}
     */
    public function __construct($id = null)
    {
        parent::__construct($id);
    }

    /**
     * {@inheritdoc}
     */
    public function add($auto_date = true, $null_values = false)
    {
        return parent::add($auto_date, $null_values);
    }

    /**
     * getAll
     *
     * @return array
     */
    public static function getAll()
    {
        $query = (new DbQuery())
            ->select('*')
            ->from('yandex_direct_ads')
        ;

        $result = Db::getInstance()->executes($query);

        return $result ?: [];
    }

    /**
     * validateEntityUrl
     *
     * @param  array  $entity
     * @param  string $directUrl
     * @return bool
     */
    public static function validateEntityUrl($entity, $directUrl, &$error = '')
    {
        if (!isset($entity['entity_url']) || !$entity['entity_url']) {
            $error = 'ERROR_EMPTY_ENTITY_URL';
            return false;
        }

        if (!$directUrl) {
            $error = 'ERROR_EMPTY_DIRECT_URL';
            return false;
        }

        $directUrlParts = parse_url($directUrl);

        if ($entity['entity_type'] === 'product' && $entity['id_entity']) {
            $product = new Product($entity['id_entity']);
            $status  = Product::getProductStatus($product);
            $link    = new Link();

            switch ($status) {
                case 'Товар недоступен для заказа':
                    $error = 'ERROR_PRODUCT_UNAVAILABLE';
                    return false;
                case 'Ожидается':
                    $error = 'ERROR_PRODUCT_OUT_OF_STOCK';
                    return false;
                case 'На заказ':
                    $error = 'ERROR_PRODUCT_ON_REQUEST';
                    return false;
            }

            /**
             * @see EtsAwuDispatcher::checkUrl()
             */
            if ($product->active) {
                $currentUrl = $link->getProductLink($product->id);
                if ($currentUrl != $directUrlParts['path'] && strpos($currentUrl, 'index.php?controller=') === false) {
                    $error = 'ERROR_PRODUCT_REDIRECT_TO_OTHER';
                    return false;
                }
            } else {
                $error = 'ERROR_PRODUCT_REDIRECT_TO_CATEGORY';
                return false;
            }
        }

        if ($entity['entity_url'] !== $directUrlParts['path']) {
            $error = 'ERROR_URL_MISMATCH';
            return false;
        }

        return true;
    }

    /**
     * getErrorChoices
     *
     * @return array
     */
    public static function getErrorChoices()
    {
        return [
            'ERROR_EMPTY_ENTITY_URL'             => self::ERROR_EMPTY_ENTITY_URL,
            'ERROR_EMPTY_DIRECT_URL'             => self::ERROR_EMPTY_DIRECT_URL,
            'ERROR_URL_MISMATCH'                 => self::ERROR_URL_MISMATCH,
            'ERROR_PRODUCT_OUT_OF_STOCK'         => self::ERROR_PRODUCT_OUT_OF_STOCK,
            'ERROR_PRODUCT_ON_REQUEST'           => self::ERROR_PRODUCT_ON_REQUEST,
            'ERROR_PRODUCT_UNAVAILABLE'          => self::ERROR_PRODUCT_UNAVAILABLE,
            'ERROR_PRODUCT_REDIRECT_TO_CATEGORY' => self::ERROR_PRODUCT_REDIRECT_TO_CATEGORY,
            'ERROR_PRODUCT_REDIRECT_TO_OTHER'    => self::ERROR_PRODUCT_REDIRECT_TO_OTHER,
        ];
    }

    /**
     * getEntityChoices
     *
     * @return array
     */
    public static function getEntityChoices()
    {
        return [
            'none'     => 'Не определено',
            'product'  => 'Товар',
            'category' => 'Категория',
            'cms'      => 'Веерка',
            'brand'    => 'Бренд',
        ];
    }

    /**
     * getStatusChoices
     *
     * @return void
     */
    public static function getStatusChoices()
    {
        return [
            'DRAFT'       => 'Черновик',
            'MODERATION'  => 'На модерации',
            'PREACCEPTED' => 'Допущено к показам ',
            'ACCEPTED'    => 'Принято ',
            'REJECTED'    => 'Отклонено ',
        ];
    }

    /**
     * getState Choices
     *
     * @return void
     */
    public static function getStateChoices()
    {
        return [
            'SUSPENDED'         => 'Остановлено',
            'OFF_BY_MONITORING' => 'Остановлено мониторингом',
            'ON'                => 'Активно',
            'OFF'               => 'Неактивно',
            'ARCHIVED'          => 'Архив',
        ];
    }

    /**
     * toArray
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'id_group'      => $this->id_group,
            'id_entity'     => $this->id_entity,
            'ad_url'        => $this->ad_url,
            'entity_url'    => $this->entity_url,
            'status'        => $this->status,
            'state'         => $this->state,
            'title'         => $this->title,
            'entity_type'   => $this->entity_type,
            'error'         => $this->error,
            'id_shop_group' => $this->id_shop_group,
            'date_update'   => $this->date_update,
        ];
    }
}
