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
    '#title' => t('GatherContent username'),
    '#required' => TRUE,
    '#default_value' => variable_get('gc_username', ''),
  );
  $form['gc_api_key'] = array(
    '#type' => 'textfield',
    '#title' => t('GatherContent API key'),
    '#required' => TRUE,
    '#default_value' => variable_get('gc_api_key', ''),
  );

  if (!isset($_SESSION['accounts'])) {
    if (!empty($account = variable_get('gc_account'))) {
      $form['current_account'] = array(
        '#prefix' => '<div>',
        '#markup' => t('Current account is <strong>@account</strong>.', array('@account' => array_pop($account))),
        '#suffix' => '</div>',
      );
    }

    $form['verify'] = array(
      '#type' => 'submit',
      '#value' => t('Verify'),
      '#submit' => array('gc_authentication_form_verify'),
    );
  }

  if (isset($_SESSION['accounts'])) {
    $form['account'] = array(
      '#type' => 'select',
      '#options' => $_SESSION['accounts'],
      '#title' => t('Account'),
      '#required' => TRUE,
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Submit'),
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
