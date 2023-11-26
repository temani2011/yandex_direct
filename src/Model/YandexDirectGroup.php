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
use DbQuery;

class YandexDirectGroup extends \ObjectModel
{
    /** @var int */
    public $id_group;

    /** @var int */
    public $id_campaign;

    /** @var string */
    public $name;

    /** @var string */
    public $status;

    /** @var string */
    public $type;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table'     => 'yandex_direct_ad_groups',
        'primary'   => 'id_group',
        'multilang' => false,
        'fields'    => [
            'id_campaign' => ['type' => self::TYPE_INT,    'validate' => 'isInt'],
            'name'        => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'status'      => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'type'        => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
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
            ->select('id_group')
            ->from('yandex_direct_ad_groups')
        ;

        $result = Db::getInstance()->executes($query);

        return $result ?: [];
    }

    /**
     * @return void
     */
    public function toArray()
    {
        return [
            'id_group'    => $this->id_group,
            'id_campaign' => $this->id_campaign,
            'name'        => $this->name,
            'status'      => $this->status,
            'type'        => $this->type,
        ];
    }
}
