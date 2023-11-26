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

namespace EO\YandexDirect\Grid\Query;

use Exception;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Adapter\Configuration;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicatorInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

/**
 * Class AdsQueryBuilder.
 */
final class AdsQueryBuilder extends AbstractDoctrineQueryBuilder
{
    /**
     * @var DoctrineSearchCriteriaApplicatorInterface
     */
    private $searchCriteriaApplicator;

    /**
     * @var int
     */
    private $contextLanguageId;

    /**
     * @var int
     */
    private $contextShopId;

    /**
     * @var int
     */
    private $contextShopGroupId;

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var string
     */
    private $currentGrid;

    private const CASE_BOTH_FIELDS_EXIST = 1;
    private const CASE_ONLY_MIN_FIELD_EXISTS = 2;
    private const CASE_ONLY_MAX_FIELD_EXISTS = 3;

    public function __construct(
        Connection $connection,
        string $dbPrefix,
        DoctrineSearchCriteriaApplicatorInterface $searchCriteriaApplicator,
        int $contextLanguageId,
        int $contextShopId,
        int $contextShopGroupId,
        Configuration $configuration
    ) {
        parent::__construct($connection, $dbPrefix);

        $this->searchCriteriaApplicator = $searchCriteriaApplicator;
        $this->contextLanguageId = $contextLanguageId;
        $this->contextShopId = $contextShopId;
        $this->contextShopGroupId = $contextShopGroupId;
        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $this->currentGrid = $searchCriteria->getFilterId();
        $qb = $this->getQueryBuilder($searchCriteria->getFilters());

        $qb->select('yda.*, id_ad as id, ydc.name as ad_campaign');

        $this->searchCriteriaApplicator
            ->applyPagination($searchCriteria, $qb)
            ->applySorting($searchCriteria, $qb)
        ;

        return $qb;
    }

    /**
     * {@inheritdoc}
     */
    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->getQueryBuilder($searchCriteria->getFilters());
        $qb->select('COUNT(*)');

        return $qb;
    }

    /**
     * Gets query builder.
     *
     * @param array $filterValues
     *
     * @return QueryBuilder
     */
    private function getQueryBuilder(array $filterValues): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->setParameter('id_shop', $this->contextShopId);
        $qb->from($this->dbPrefix . 'yandex_direct_ads', 'yda');
        $qb->leftJoin('yda', $this->dbPrefix . 'yandex_direct_campaigns', 'ydc', 'yda.id_campaign = ydc.id_campaign');

        foreach ($filterValues as $filterName => $filter) {
            if (
                in_array($filterName, [
                    'id_ad',
                    'id_entity',
                    'entity_type',
                    'ad_url',
                    'entity_url',
                    'status',
                    'state',
                    'title',
                    'error',
                    'id_shop_group',
                ])
            ) {
                $qb->andWhere("{$filterName} = :{$filterName}");
                $qb->setParameter($filterName, $filter);
                continue;
            }

            if ($filterName === 'ad_campaign') {
                $qb->andWhere("yda.id_campaign = :{$filterName}");
                $qb->setParameter($filterName, $filter);
                continue;
            }

            if (in_array($filterName, ['recommend_price', 'base_price'])) {
                $minFieldSqlCondition = sprintf('%s >= :%s_min', $filterName, $filterName);
                $maxFieldSqlCondition = sprintf('%s <= :%s_max', $filterName, $filterName);

                switch ($this->computeMinMaxCase($filter, ['min_field', 'max_field'])) {
                    case self::CASE_BOTH_FIELDS_EXIST:
                        $qb->andWhere(sprintf('%s AND %s', $minFieldSqlCondition, $maxFieldSqlCondition));
                        $qb->setParameter(sprintf('%s_min', $filterName), $filter['min_field']);
                        $qb->setParameter(sprintf('%s_max', $filterName), $filter['max_field']);
                        break;
                    case self::CASE_ONLY_MIN_FIELD_EXISTS:
                        $qb->andWhere($minFieldSqlCondition);
                        $qb->setParameter(sprintf('%s_min', $filterName), $filter['min_field']);
                        break;
                    case self::CASE_ONLY_MAX_FIELD_EXISTS:
                        $qb->andWhere($maxFieldSqlCondition);
                        $qb->setParameter(sprintf('%s_max', $filterName), $filter['max_field']);
                        break;
                }
                continue;
            }
        }

        return $qb;
    }

    /**
     * @param array<string, int> $value
     *
     * @return int
     */
    private function computeMinMaxCase(array $value, array $keys): int
    {
        $minFieldExists = isset($value[$keys[0]]);
        $maxFieldExists = isset($value[$keys[1]]);

        if ($minFieldExists && $maxFieldExists) {
            return self::CASE_BOTH_FIELDS_EXIST;
        }
        if ($minFieldExists) {
            return self::CASE_ONLY_MIN_FIELD_EXISTS;
        }

        if ($maxFieldExists) {
            return self::CASE_ONLY_MAX_FIELD_EXISTS;
        }

        throw new Exception('Min max filter wasn\'t applied correctly');
    }
}
