ABOUT THIS MODULE
-----------------

This module integrates Drupal with GatherContent, a service to "plan, structure
and collaborate on web content" (http://gathercontent.com/).

Currently, the module allows you to import all pages from a GatherContent
project, creating a node for every GatherContent page.

INSTRUCTIONS
------------

1) Download and install the module. Note that CTools is a dependency.
2) Configure the module on /admin/config/content/gathercontent/settings.
You'll need your GatherContent account name and API key.
3) Import pages on /admin/config/content/gathercontent/nojs/import.

REQUIREMENTS
------------

Note that this module needs cURL to be installed on your server. The
GatherContent API requires Digest HTTP authentication, which
drupal_http_request() doesn't support yet
(see #289820: https://drupal.org/node/289820).

BUGS, SUGGESTIONS, SUPPORT REQUESTS
-----------------------------------

All are welcome in the issue queue: https://drupal.org/project/issues/1883798.