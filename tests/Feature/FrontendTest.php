<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrontendTest extends TestCase
{
    // todos os elementos que os arquivos .js procuram
    private const IDS = [
        'login-view', 'login-form', 'login-email', 'login-password', 'login-error',
        'app-view', 'user-name', 'logout-btn', 'toast',

        'page-dashboard', 'page-products', 'page-purchases',
        'page-sales', 'page-categories', 'page-suppliers',

        'month', 'cards', 'top-chart', 'top-empty', 'day-chart',

        'export-xlsx', 'export-pdf', 'new-btn', 'search', 'filter-category',
        'rows', 'pager-info', 'prev-btn', 'next-btn',
        'product-dialog', 'product-form', 'dialog-title', 'f-sku', 'f-barcode', 'f-name',
        'f-category', 'f-tax', 'f-description', 'f-active', 'form-errors', 'cancel-btn',

        'new-purchase-btn', 'purchase-status', 'purchase-rows',
        'purchase-pager-info', 'purchase-prev', 'purchase-next',
        'purchase-dialog', 'purchase-form', 'p-supplier', 'p-search', 'p-results',
        'p-items', 'p-total', 'p-errors', 'p-cancel',
        'purchase-view', 'pv-title', 'pv-meta', 'pv-items', 'pv-total', 'pv-close',

        'pos-search', 'pos-grid', 'cart-items', 'pos-customer', 'pos-payment',
        'pos-discount', 'cart-gross', 'cart-discount', 'cart-total',
        'pos-error', 'pos-finish', 'pos-clear', 'sale-rows',

        'new-category-btn', 'category-search', 'category-rows',
        'category-dialog', 'category-form', 'category-title', 'c-name', 'c-errors', 'c-cancel',

        'new-supplier-btn', 'supplier-search', 'supplier-active', 'supplier-rows',
        'supplier-pager-info', 'supplier-prev', 'supplier-next',
        'supplier-dialog', 'supplier-form', 'supplier-title',
        's-name', 's-tax', 's-phone', 's-email', 's-address', 's-active', 's-errors', 's-cancel',

        'navbar-greeting', 'navbar-date', 'notif-btn', 'notif-count', 'notif-panel',
        'notif-seen', 'notif-list', 'navbar-user',

        'page-users', 'new-user-btn', 'user-search', 'user-role', 'user-active', 'user-rows',
        'user-pager-info', 'user-prev', 'user-next',
        'user-dialog', 'user-form', 'user-title', 'u-name', 'u-email', 'u-role', 'u-password',
        'u-show', 'u-active', 'u-hint', 'u-errors', 'u-cancel',

        'page-config', 'cfg-profile-form', 'cfg-name', 'cfg-email', 'cfg-role', 'cfg-profile-errors',
        'cfg-password-form', 'cfg-current', 'cfg-new', 'cfg-confirm', 'cfg-password-errors',
    ];

    private const PAGES = ['dashboard', 'products', 'purchases', 'sales', 'categories', 'suppliers', 'users', 'config'];

    public function test_the_page_contains_every_element_the_scripts_depend_on(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (self::IDS as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html, "Falta o elemento #{$id}");
        }
    }

    public function test_every_page_has_a_menu_link_and_a_script(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (self::PAGES as $page) {
            $this->assertStringContainsString('data-page="'.$page.'"', $html, "Falta o link do menu: {$page}");
            $this->assertStringContainsString('id="page-'.$page.'"', $html, "Falta a página: {$page}");
        }
        foreach (['app', 'navbar', 'purchases', 'sales', 'categories', 'suppliers', 'users', 'config'] as $script) {
            $this->assertStringContainsString("js/{$script}.js", $html, "Falta o script {$script}.js");
        }
    }

    public function test_no_element_id_is_repeated(): void
    {
        $html = $this->get('/')->getContent();

        preg_match_all('/\sid="([^"]+)"/', $html, $matches);

        $repeated = array_keys(array_filter(array_count_values($matches[1]), fn ($n) => $n > 1));

        $this->assertSame([], $repeated, 'ids repetidos: '.implode(', ', $repeated));
    }
}
