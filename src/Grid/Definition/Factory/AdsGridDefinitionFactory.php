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

namespace EO\YandexDirect\Grid\Definition\Factory;

use PrestaShopBundle\Form\Admin\Type\DateRangeType;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\AbstractGridDefinitionFactory;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use PrestaShopBundle\Form\Admin\Type\SearchAndResetType;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ToggleColumn;
use PrestaShopBundle\Form\Admin\Type\YesAndNoChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DateTimeColumn;
use Symfony\Component\Validator\Constraints as Assert;
use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\BulkActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\BulkActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\SubmitRowAction;
use PrestaShopBundle\Form\Admin\Type\NumberMinMaxFilterType;
use EO\YandexDirect\Model\YandexDirectAd;
use EO\YandexDirect\Model\YandexDirectCampaign;

/**
 * Class AdsGridDefinitionFactory.
 */
final class AdsGridDefinitionFactory extends AbstractGridDefinitionFactory
{
    const GRID_ID = 'ads';

    /**
     * {@inheritdoc}
     */
    protected function getId()
    {
        return self::GRID_ID;
    }

    /**
     * {@inheritdoc}
     */
    protected function getName()
    {
        return 'Объявления';
    }

    /**
     * {@inheritdoc}
     */
    protected function getColumns()
    {
        return (new ColumnCollection())
            ->add(
                (new BulkActionColumn('bulk'))
                    ->setOptions([
                        'bulk_field' => 'id',
                    ])
            )
            ->add(
                (new DataColumn('ad_campaign'))
                    ->setName('Название кампании')
                    ->setOptions([
                        'field' => 'ad_campaign',
                    ])
            )
            ->add(
                (new DataColumn('id_ad'))
                    ->setName('ID')
                    ->setOptions([
                        'field' => 'id_ad',
                    ])
            )
            ->add(
                (new DataColumn('id_entity'))
                    ->setName('ID сущности')
                    ->setOptions([
                        'field' => 'id_entity',
                    ])
            )
            ->add(
                (new DataColumn('entity_type'))
                    ->setName('Тип сущности')
                    ->setOptions([
                        'field' => 'entity_type',
                    ])
            )
            ->add(
                (new DataColumn('ad_url'))
                    ->setName('URL объявления')
                    ->setOptions([
                        'field' => 'ad_url',
                    ])
            )
            ->add(
                (new DataColumn('entity_url'))
                    ->setName('URL сущности')
                    ->setOptions([
                        'field' => 'entity_url',
                    ])
            )
            ->add(
                (new DataColumn('error'))
                    ->setName('Ошибка соответствия')
                    ->setOptions([
                        'field' => 'error',
                    ])
            )
            ->add(
                (new DataColumn('status'))
                    ->setName('Статус объявления')
                    ->setOptions([
                        'field' => 'status',
                    ])
            )
            ->add(
                (new DataColumn('state'))
                    ->setName('Cостояние объявления')
                    ->setOptions([
                        'field' => 'state',
                    ])
            )
            ->add(
                (new DataColumn('title'))
                    ->setName('Название')
                    ->setOptions([
                        'field' => 'title',
                    ])
            )
            ->add(
                (new DateTimeColumn('date_update'))
                    ->setName('Дата обновления')
                    ->setOptions([
                        'format' => 'Y-m-d H:i',
                        'field'  => 'date_update',
                    ])
            )
            ->add(
                (new ActionColumn('actions'))
                    ->setName($this->trans('Actions', [], 'Admin.Global'))
                    ->setOptions([
                        'actions' => (new RowActionCollection())
                            ->add((new LinkRowAction('edit'))
                                ->setIcon('edit')
                                ->setName('Редактировать')
                                ->setOptions([
                                    'route'             => 'admin_yandex_direct_ads_edit',
                                    'route_param_name'  => 'id',
                                    'route_param_field' => 'id',
                            ]))
                            ->add((new SubmitRowAction('add'))
                                ->setIcon('sync')
                                ->setName('Обновить данные из директа')
                                ->setOptions([
                                    'method'            => 'POST',
                                    'route'             => 'admin_yandex_direct_ads_check',
                                    'route_param_name'  => 'id',
                                    'route_param_field' => 'id',
                            ]))
                            ->add((new SubmitRowAction('sync'))
                                ->setIcon('sync')
                                ->setName('Синхронизировать URL')
                                ->setOptions([
                                    'method'            => 'POST',
                                    'route'             => 'admin_yandex_direct_ads_sync',
                                    'route_param_name'  => 'id',
                                    'route_param_field' => 'id',
                            ]))
                            ->add((new SubmitRowAction('resume'))
                                ->setIcon('play_arrow')
                                ->setName('Запустить')
                                ->setOptions([
                                    'method'            => 'POST',
                                    'route'             => 'admin_yandex_direct_ads_resume',
                                    'route_param_name'  => 'id',
                                    'route_param_field' => 'id',
                                    'confirm_message'   => 'Запустить объявление?',
                            ]))
                            ->add((new SubmitRowAction('suspend'))
                                ->setIcon('pause')
                                ->setName('Остановить')
                                ->setOptions([
                                    'method'            => 'POST',
                                    'route'             => 'admin_yandex_direct_ads_suspend',
                                    'route_param_name'  => 'id',
                                    'route_param_field' => 'id',
                                    'confirm_message'   => 'Остановить объявление?',
                            ]))

                        ,
                    ])
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFilters()
    {
        return (new FilterCollection())
            ->add(
                (new Filter('id_shop_group', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => [
                            'Express' => '1',
                            'Home24'  => '3',
                        ],
                        'empty_data'  => '1',
                        'placeholder' => 'Магазин',
                    ])
                    ->setAssociatedColumn('id_shop_group')
            )
            ->add(
                (new Filter('ad_campaign', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => array_flip(array_column(YandexDirectCampaign::getAll(), 'name', 'id_campaign')),
                    ])
                    ->setAssociatedColumn('ad_campaign')
            )
            ->add(
                (new Filter('id_ad', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => 'ID',
                        ],
                    ])
                    ->setAssociatedColumn('id_ad')
            )
            ->add(
                (new Filter('id_entity', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => 'ID сущности',
                        ],
                    ])
                    ->setAssociatedColumn('id_entity')
            )
            ->add(
                (new Filter('entity_type', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => array_flip(YandexDirectAd::getEntityChoices()),
                    ])
                    ->setAssociatedColumn('entity_type')
            )
            ->add(
                (new Filter('ad_url', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => 'URL объявления',
                        ],
                    ])
                    ->setAssociatedColumn('ad_url')
            )
            ->add(
                (new Filter('entity_url', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => 'URL сущности',
                        ],
                    ])
                    ->setAssociatedColumn('entity_url')
            )
            ->add(
                (new Filter('error', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => array_flip(YandexDirectAd::getErrorChoices()),
                    ])
                    ->setAssociatedColumn('error')
            )
            ->add(
                (new Filter('status', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => array_flip(YandexDirectAd::getStatusChoices()),
                    ])
                    ->setAssociatedColumn('status')
            )
            ->add(
                (new Filter('state', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => array_flip(YandexDirectAd::getStateChoices()),
                    ])
                    ->setAssociatedColumn('state')
            )
            ->add(
                (new Filter('title', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => 'Название',
                        ],
                    ])
                    ->setAssociatedColumn('title')
            )
            ->add(
                (new Filter('date_update', DateRangeType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => 'Дата',
                        ],
                    ])
                    ->setAssociatedColumn('date_update')
            )
            ->add(
                (new Filter('actions', SearchAndResetType::class))
                    ->setAssociatedColumn('actions')
                    ->setTypeOptions([
                        'reset_route' => 'admin_common_reset_search_by_filter_id',
                        'reset_route_params' => [
                            'filterId' => self::GRID_ID,
                        ],
                        'redirect_route' => 'admin_yandex_direct_ads_list',
                    ])
                    ->setAssociatedColumn('actions')
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function getBulkActions()
    {
        return (new BulkActionCollection())
            ->add(
                (new SubmitBulkAction('bulk_export'))
                    ->setName('Экспорт в excel')
                    ->setOptions([
                        'submit_route' => 'admin_yandex_direct_ads_bulk_export',
                    ])
            )
            ->add(
                (new SubmitBulkAction('bulk_check'))
                    ->setName('Обновить данные из директа')
                    ->setOptions([
                        'submit_route' => 'admin_yandex_direct_ads_bulk_check',
                    ])
            )
            ->add(
                (new SubmitBulkAction('bulk_sync'))
                    ->setName('Синхронизировать URL\'ы')
                    ->setOptions([
                        'submit_route' => 'admin_yandex_direct_ads_bulk_sync',
                    ])
            )
            ->add(
                (new SubmitBulkAction('bulk_resume'))
                    ->setName('Запустить')
                    ->setOptions([
                        'submit_route' => 'admin_yandex_direct_ads_bulk_resume',
                    ])
            )
            ->add(
                (new SubmitBulkAction('bulk_suspend'))
                    ->setName('Остановить')
                    ->setOptions([
                        'submit_route' => 'admin_yandex_direct_ads_bulk_suspend',
                    ])
            )
        ;
    }
}
