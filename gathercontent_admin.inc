<?php
/**
 * Form builder function for module settings.
 */
function gathercontent_api_admin() {
  // Create a new GatherContent object.
  $account_name = variable_get('gathercontent_account_name');
  $api_key = variable_get('gathercontent_api_key');
  $resp = gathercontent_get_command('get_projects', array(), $account_name, $api_key);
  
  if ($resp) {
    drupal_set_message('We have succesfully contacted the GatherContent API.');
  }
  $form['gathercontent_account_name'] = array(
    '#type' => 'textfield',
    '#title' => t('GatherContent account name'),
    '#default_value' => variable_get('gathercontent_account_name', ''),
    '#description' => t('Your GatherContent account name. It\'s the first part of the GatherContent url when you\'re logged in, eg. https://[accountname].gathercontent.com. '),
  );
  $form['gathercontent_api_key'] = array(
    '#type' => 'textfield',
    '#title' => t('GatherContent API key'),
    '#default_value' => variable_get('gathercontent_api_key', ''),
    '#description' => t('Your GatherContent API key, required to fetch data from the GatherContent service. You can find it by clicking on the "API" tab of the GatherContent settings page.'),
  );

  return system_settings_form($form);
}

function gathercontent_api_admin_validate($form, &$form_state){
  $account_name = $form_state['values']['gathercontent_account_name'];
  $api_key = $form_state['values']['gathercontent_api_key'];
  if ($account_name != '' && $api_key != '') {
    $resp = gathercontent_get_command('get_projects', array(), $account_name, $api_key);
    if (isset($resp->is_error)) {
      form_set_error('gathercontent_api_key', $resp->error);
    }
  }
}