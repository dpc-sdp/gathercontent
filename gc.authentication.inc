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
    '#default_value' => \Drupal::config('gc.settings')->get('gc_username'),
    '#description' => t('This is the email address you use to login to GatherContent. Your permissions will determine what accounts, projects and content is available.'),
  );
  $form['gc_api_key'] = array(
    '#type' => 'textfield',
    '#title' => t('GatherContent API key'),
    '#required' => TRUE,
    '#default_value' => \Drupal::config('gc.settings')->get('gc_api_key'),
    '#description' => \Drupal::l(t('Click to find out where you can generate your API Key'), \Drupal\Core\Url::fromUri('https://gathercontent.com/developers/authentication/')),
  );

  if (!$form_state['submitted']) {
    // @FIXME
// Could not extract the default value because it is either indeterminate, or
// not scalar. You'll need to provide a default value in
// config/install/gc.settings.yml and config/schema/gc.schema.yml.
$account = \Drupal::config('gc.settings')->get('gc_account');
    if (!empty($account)) {
      $account = array_pop($account);
      $form['current_account'] = array(
        '#prefix' => '<div>',
        '#markup' => t('Current account is <strong>@account</strong>.', array('@account' => $account)),
        '#suffix' => '</div>',
      );
    }
  }

  if ($form_state['submitted']) {
    $account_obj = new Account();
    $data = $account_obj->getAccounts();
    $accounts = array();
    if (!is_null($data)) {
      foreach ($data as $account) {
        $accounts[$account->id] = $account->name;
      }

      $form['account'] = array(
        '#type' => 'select',
        '#options' => $accounts,
        '#title' => t('Select GatherContent Account'),
        '#required' => TRUE,
        '#description' => t('Multiple accounts will be listed if the GatherContent
       user has more than one account. Please select the account you want to
       import and update content from.'),
      );
    }
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => (is_null($data)) ? t('Verify') : t('Save'),
    );
  }
  else {
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => (!empty($account) ? t('Change GatherContent Account') : t('Verify')),
    );
  }

  if (!empty($account)) {
    $form['reset'] = array(
      '#type' => 'submit',
      '#value' => t('Reset credentials'),
    );
  }

  return $form;
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
  if ($form_state['triggering_element']['#id'] === 'edit-submit') {
    if (!isset($form_state['values']['account'])) {
      \Drupal::configFactory()->getEditable('gc.settings')->set('gc_username', $form_state['values']['gc_username'])->save();
      \Drupal::configFactory()->getEditable('gc.settings')->set('gc_api_key', $form_state['values']['gc_api_key'])->save();
      $form_state['submitted'] = TRUE;
      $form_state['rebuild'] = TRUE;
    }
    else {
      $account_obj = new Account();
      $data = $account_obj->getAccounts();
      foreach ($data as $account) {
        if ($account->id === $form_state['values']['account']) {
          $account_name = $account->name;
          \Drupal::configFactory()->getEditable('gc.settings')->set('gc_urlkey', $account->slug)->save();
          break;
        }
      }
      \Drupal::configFactory()->getEditable('gc.settings')->set('gc_account', array($form_state['values']['account'] => $account_name))->save();
      drupal_set_message(t("Credentials and project were saved."));
    }
  }
  elseif ($form_state['triggering_element']['#id'] === 'edit-reset') {
    \Drupal::config('gc.settings')->clear('gc_username')->save();
    \Drupal::config('gc.settings')->clear('gc_api_key')->save();
    \Drupal::config('gc.settings')->clear('gc_account')->save();
    \Drupal::config('gc.settings')->clear('gc_urlkey')->save();
  }
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
    return t("Connection wasn't successful");
  }
}
