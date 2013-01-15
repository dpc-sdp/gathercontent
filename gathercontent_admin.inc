<?php

/**
 * @file
 * Form builder function for module settings.
 */

/**
 * Settings form builder.
 */
function gathercontent_api_admin() {
  $account_name = variable_get('gathercontent_account_name');
  $api_key = variable_get('gathercontent_api_key');
  // @TODO: Replace this with a simple 'ping' call to the API. Is there
  // such a thing?
  // Using get_me() for now, since this is probably the fastest.
  // Since we want to display the API status everytime the user comes to this
  // page, we're doing this here  instead of the form's validation.
  $resp = gathercontent_get_command('get_me');
  if (isset($resp->is_error)) {
    drupal_set_message(t('The GatherContent API could not be reached. Please verify your API credentials (Error message: %error_message)', array('%error_message' => check_plain($resp->error))), 'error');
  }
  elseif ($resp->success == TRUE) {
    drupal_set_message(t('We have succesfully contacted the GatherContent API.'));
  }
  $form['gathercontent_account_name'] = array(
    '#type' => 'textfield',
    '#title' => t('GatherContent account name'),
    '#default_value' => variable_get('gathercontent_account_name', ''),
    '#description' => t("Your GatherContent account name. It's the first part of the GatherContent url when you\'re logged in, eg. https://[accountname].gathercontent.com."),
    '#required' => TRUE,
  );
  $form['gathercontent_api_key'] = array(
    '#type' => 'textfield',
    '#title' => t('GatherContent API key'),
    '#default_value' => variable_get('gathercontent_api_key', ''),
    '#description' => t('Your GatherContent API key, required to fetch data from the GatherContent service. You can find it by clicking on the "API" tab of the GatherContent settings page.'),
    '#required' => TRUE,
  );

  if (!empty($account_name) && !empty($api_key)) {
    $projects = gathercontent_get_command('get_projects');
    $projects_list = array();
    if (is_object($projects) && isset($projects->projects) && is_array($projects->projects)) {
      foreach ($projects->projects as $project) {
        $projects_list[$project->id] = $project->name;
      }
      asort($projects_list);
    }
    $project = variable_get('gathercontent_project');

    $form['gathercontent_project'] = array(
      '#title' => t('Select the GatherContent project from which you want to import pages:'),
      '#type' => 'radios',
      '#default_value' => ($project == '' ? key($projects_list) : $project),
      '#options' => $projects_list,
      '#required' => TRUE,
    );
  }

  return system_settings_form($form);
}
