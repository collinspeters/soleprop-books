<?php

use Illuminate\Support\Facades\Route;

/**
 * 'api' middleware and prefix applied to all routes
 *
 * @see \App\Providers\Route::mapApiRoutes
 */

Route::group(['as' => 'api.'], function () {
    // Ping
    Route::get('ping', 'Common\Ping@pong')->name('ping');

    // Users
    Route::get('users/{user}/enable', 'Auth\Users@enable')->name('users.enable');
    Route::get('users/{user}/disable', 'Auth\Users@disable')->name('users.disable');
    Route::apiResource('users', 'Auth\Users');

    // Companies
    Route::get('companies/{company}/owner', 'Common\Companies@canAccess')->name('companies.owner');
    Route::get('companies/{company}/enable', 'Common\Companies@enable')->name('companies.enable');
    Route::get('companies/{company}/disable', 'Common\Companies@disable')->name('companies.disable');
    Route::apiResource('companies', 'Common\Companies', ['middleware' => ['dropzone']]);

    // Dashboards
    Route::get('dashboards/{dashboard}/enable', 'Common\Dashboards@enable')->name('dashboards.enable');
    Route::get('dashboards/{dashboard}/disable', 'Common\Dashboards@disable')->name('dashboards.disable');
    Route::apiResource('dashboards', 'Common\Dashboards');

    // Items
    Route::get('items/{item}/enable', 'Common\Items@enable')->name('items.enable');
    Route::get('items/{item}/disable', 'Common\Items@disable')->name('items.disable');
    Route::apiResource('items', 'Common\Items', ['middleware' => ['dropzone']]);

    // Contacts
    Route::get('contacts/{contact}/enable', 'Common\Contacts@enable')->name('contacts.enable');
    Route::get('contacts/{contact}/disable', 'Common\Contacts@disable')->name('contacts.disable');
    Route::apiResource('contacts', 'Common\Contacts');

    // Documents
    Route::get('documents/{document}/received', 'Document\Documents@received')->name('documents.received');
    Route::apiResource('documents', 'Document\Documents', ['middleware' => ['date.format', 'money', 'dropzone']]);
    Route::apiResource('documents.transactions', 'Document\DocumentTransactions', ['middleware' => ['date.format', 'money', 'dropzone']]);

    // Accounts
    Route::get('accounts/{account}/enable', 'Banking\Accounts@enable')->name('accounts.enable');
    Route::get('accounts/{account}/disable', 'Banking\Accounts@disable')->name('accounts.disable');
    Route::apiResource('accounts', 'Banking\Accounts', ['middleware' => ['date.format', 'money', 'dropzone']]);

    // Reconciliations
    Route::apiResource('reconciliations', 'Banking\Reconciliations', ['middleware' => ['date.format', 'money', 'dropzone']]);

    // Transactions
    Route::apiResource('transactions', 'Banking\Transactions', ['middleware' => ['date.format', 'money', 'dropzone']]);

    // Transfers
    Route::apiResource('transfers', 'Banking\Transfers', ['middleware' => ['date.format', 'money', 'dropzone']]);

    // Plaid Integration
    Route::group(['prefix' => 'plaid', 'as' => 'plaid.'], function () {
        Route::post('link-token', 'Banking\PlaidController@createLinkToken')->name('link-token');
        Route::post('exchange-token', 'Banking\PlaidController@exchangeToken')->name('exchange-token');
        Route::post('accounts', 'Banking\PlaidController@getAccounts')->name('accounts');
        Route::post('import', 'Banking\PlaidController@importTransactions')->name('import');
        Route::post('import-sync', 'Banking\PlaidController@importTransactionsSync')->name('import-sync');
        Route::get('import-status', 'Banking\PlaidController@getImportStatus')->name('import-status');
        Route::post('sync-balances', 'Banking\PlaidController@syncBalances')->name('sync-balances');
        Route::get('test-credentials', 'Banking\PlaidController@testCredentials')->name('test-credentials');
        Route::get('test-openai', 'Banking\PlaidController@testOpenAiCredentials')->name('test-openai');
        Route::get('categorization-stats', 'Banking\PlaidController@getCategorizationStats')->name('categorization-stats');
        Route::delete('clear-cache', 'Banking\PlaidController@clearCategorizationCache')->name('clear-cache');
        Route::get('category-suggestions', 'Banking\PlaidController@getCategorySuggestions')->name('category-suggestions');
    });

    // Reports
    Route::resource('reports', 'Common\Reports');

    // Categories
    Route::get('categories/{category}/enable', 'Settings\Categories@enable')->name('categories.enable');
    Route::get('categories/{category}/disable', 'Settings\Categories@disable')->name('categories.disable');
    Route::apiResource('categories', 'Settings\Categories');

    // Currencies
    Route::get('currencies/{currency}/enable', 'Settings\Currencies@enable')->name('currencies.enable');
    Route::get('currencies/{currency}/disable', 'Settings\Currencies@disable')->name('currencies.disable');
    Route::apiResource('currencies', 'Settings\Currencies');

    // Taxes
    Route::get('taxes/{tax}/enable', 'Settings\Taxes@enable')->name('taxes.enable');
    Route::get('taxes/{tax}/disable', 'Settings\Taxes@disable')->name('taxes.disable');
    Route::apiResource('taxes', 'Settings\Taxes');

    // Settings
    Route::apiResource('settings', 'Settings\Settings');

    // Translations
    Route::get('translations/{locale}/all', 'Common\Translations@all')->name('translations.all');
    Route::get('translations/{locale}/{file}', 'Common\Translations@file')->name('translations.file');
});
