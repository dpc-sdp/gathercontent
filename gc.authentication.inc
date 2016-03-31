<?php

/**
 * @file
 * Administration code.
 */

include_once "includes/Account.inc";

use GatherContent\Account;

/**
 * Callback function for GC administration.
 *
 * @param array $form
 *   Array with form elements.
 * @param array $form_state
 *   Array with form values and state.
 *
 * @return array
 *   Array with form elements.
 */
function gc_authentication_form($form, &$form_state) {
  $form = array();
  $form['gc_username'] = array(
    '#type' => 'textfield',
    '#title' => t('GatherContent User Email Address'),
    '#required' => TRUE,
    '#default_value' => variable_get('gc_username', ''),
    '#description' => t('This is the email address you use to login to 
    GatherContent. Your permissions will determine what accounts, 
    projects and content is available.'),
  );
  $form['gc_api_key'] = array(
    '#type' => 'textfield',
    '#title' => t('GatherContent API key'),
    '#required' => TRUE,
    '#default_value' => variable_get('gc_api_key', ''),
    '#description' => l(t('Click to find out where you can generate your API Key'),
      'https://gathercontent.com/developers/authentication/'),
  );

  if (!isset($_SESSION['accounts'])) {
    $account = variable_get('gc_account');
    $account = array_pop($account);
    if (!empty($account)) {
      $form['current_account'] = array(
        '#prefix' => '<div>',
        '#markup' => t('Current account is <strong>@account</strong>.', array('@account' => $account)),
        '#suffix' => '</div>',
      );
    }

    $form['verify'] = array(
      '#type' => 'submit',
      '#value' => (!empty($account) ? t('Change GatherContent Account') : t('Verify')),
      '#submit' => array('gc_authentication_form_verify'),
    );
  }

  if (isset($_SESSION['accounts'])) {
    $form['account'] = array(
      '#type' => 'select',
      '#options' => $_SESSION['accounts'],
      '#title' => t('Select GatherContent Account'),
      '#required' => TRUE,
      '#description' => t('Multiple accounts will be listed if the GatherContent
       user has more than one account. Please select the account you want to 
       import and update content from.'),
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
    );
  }
  return $form;
}

/**
 * Submit callback for verify action of `gc_authentication_form`.
 *
 * @param array $form
 *   Array with form elements.
 * @param array $form_state
 *   Array with form values and state.
 */
function gc_authentication_form_verify($form, &$form_state) {
  variable_set('gc_username', $form_state['values']['gc_username']);
  variable_set('gc_api_key', $form_state['values']['gc_api_key']);

  $account_obj = new Account();
  $data = $account_obj->getAccounts();
  $accounts = array();

  foreach ($data as $account) {
    $accounts[$account->id] = $account->name;
  }

  $_SESSION['accounts'] = $accounts;
}

/**
 * Submit callback for `gc_authentication_form`.
 *
 * @param array $form
 *   Array with form elements.
 * @param array $form_state
 *   Array with form values and state.
 */
function gc_authentication_form_submit($form, &$form_state) {
  $account_name = $_SESSION['accounts'][$form_state['values']['account']];
  variable_set('gc_account', array($form_state['values']['account'] => $account_name));
  drupal_set_message(t("Credentials and project were saved."));
  unset($_SESSION['accounts']);
}

/**
 * Page callback for connection testing page.
 *
 * @return string
 *   Content of the page.
 */
function gc_authentication_test_page() {
  $acc_obj = new Account();
  $success = $acc_obj->testConnection();

  if ($success === TRUE) {
    return t('Connection successful');
  }
  else {
    return t('Connection wasn\'t successful');
  }
}
