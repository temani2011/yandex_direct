/**
 * 2007-2018 PrestaShop
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
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2018 PrestaShop SA
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

import Grid from '@components/grid/grid';
import LinkRowActionExtension from '@components/grid/extension/link-row-action-extension';
import SubmitRowActionExtension from '@components/grid/extension/action/row/submit-row-action-extension';
import SortingExtension from "@components/grid/extension/sorting-extension";
import PositionExtension from "@components/grid/extension/position-extension";
import ColumnTogglingExtension from '@components/grid/extension/column-toggling-extension';
import FiltersResetExtension from '@components/grid/extension/filters-reset-extension';
import SubmitBulkExtension from '@components/grid/extension/submit-bulk-action-extension';
import BulkActionCheckboxExtension from '@components/grid/extension/bulk-action-checkbox-extension';
import FiltersSubmitButtonEnablerExtension from '@components/grid/extension/filters-submit-button-enabler-extension';

const $ = window.$;

$(() => {
    let gridDivs = document.querySelectorAll('.js-grid');
    gridDivs.forEach((gridDiv) => {
        const assemblyGrid = new Grid(gridDiv.dataset.gridId);
        assemblyGrid.addExtension(new SortingExtension());
        assemblyGrid.addExtension(new LinkRowActionExtension());
        assemblyGrid.addExtension(new SubmitRowActionExtension());
        assemblyGrid.addExtension(new PositionExtension());
        assemblyGrid.addExtension(new ColumnTogglingExtension());
        assemblyGrid.addExtension(new FiltersResetExtension());
        assemblyGrid.addExtension(new SubmitBulkExtension());
        assemblyGrid.addExtension(new BulkActionCheckboxExtension());
        assemblyGrid.addExtension(new FiltersSubmitButtonEnablerExtension());

        initShopSelectEvent()
        initBulkSelectAllEvent()
    });
});

const initShopSelectEvent = () => {
    const shopFilter = document.querySelector('[name="ads[id_shop_group]"]')
    const searchBtn = document.querySelector('.grid-search-button')

    if (!shopFilter || !searchBtn) return void 0

    shopFilter.addEventListener('change', () => {
        if (!shopFilter.value) return void 0

        shopFilter.childNodes.forEach(el => {
            shopFilter.value === el.value ? el.setAttribute('selected', 'selected') : el.removeAttribute('selected')
        })

        searchBtn.removeAttribute('disabled')
    })
}

const initBulkSelectAllEvent = () => {
    const bulkSelectAll = document.querySelector('.js-bulk-action-select-all')

    if (!bulkSelectAll) return void 0

    bulkSelectAll.addEventListener('change', updateBulkUrls)

    updateBulkUrls()
}

const updateBulkUrls = () => {
    const bulkActionBtns = document.querySelectorAll('.js-bulk-action-submit-btn')
    const bulkSelectAll  = document.querySelector('.js-bulk-action-select-all')

    if (!bulkActionBtns.length || !bulkSelectAll) return void 0

    bulkActionBtns.forEach(element => {
        const url = new URL(element.dataset.formUrl, window.location.origin)

        bulkSelectAll.checked ? url.searchParams.set('select-all', true) : url.searchParams.set('select-all', false)

        element.dataset.formUrl = url.toString()
    })
}
