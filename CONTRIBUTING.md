# Contributing

Keep changes focused and add regression tests for changes to permissions,
credentials, API handling, uploads or library navigation. Use synthetic data;
never commit API keys, signed upload URLs, local settings or customer media.

## Run the tests

Use a disposable Drupal 11 development installation with this module at
`web/modules/custom/mave`. Install `drupal/core-dev` at the same version as
`drupal/core-recommended`; do this in a development checkout, not a live site.
Drupal's core development package supplies PHPUnit and Drupal Coder.

From that Drupal installation's root, with its web server running:

```sh
export SIMPLETEST_BASE_URL=http://localhost
export SIMPLETEST_DB=sqlite://localhost//tmp/mave-tests.sqlite
export BROWSERTEST_OUTPUT_DIRECTORY="$PWD/web/sites/simpletest/browser_output"
mkdir -p "$BROWSERTEST_OUTPUT_DIRECTORY"
php -d zend.assertions=1 vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  --fail-on-warning --fail-on-deprecation web/modules/custom/mave/tests/src
vendor/bin/phpcs --standard=Drupal --extensions=php,module,install,inc,yml \
  web/modules/custom/mave
```

The test runner and web server need write access to the test database and
`web/sites/simpletest`. Run them as the same user where possible. Tests install
temporary Drupal sites and do not require a Mave connection. Unit tests mock
HTTP; functional tests exercise real routes, sessions and permissions.

Check JavaScript syntax with `node --check js/picker.js` (and `dialog.js` and
`player.js`) from the module directory. UI changes also need a browser check:
open Media Library, navigate/search, select a video, toggle its preview and
resize the actual window. Check the fixed footer, keyboard focus, empty results,
slow/failed requests and retry. Use a separate test browser for viewport emulation.

## CI and releases

GitHub Actions runs the unit/functional tests, Drupal PHP coding standards,
Composer validation and JavaScript syntax checks on pushes and pull requests.
Its `tests/Dockerfile` pins the tested Drupal image and matching core-dev package.
It uses a disposable SQLite site and needs no Mave credentials.

The included `.gitlab-ci.yml` uses Drupal's maintained GitLab templates. PHPUnit
and Drupal PHP coding standards are blocking checks. Previous/next minor Drupal
variants expose compatibility issues. The templates also provide their standard
JavaScript, CSS, spelling, static-analysis and secret-detection jobs.

Run `composer validate --strict composer.json` before a release. The hosted
pipeline must run after the Drupal.org repository exists; local success is not
evidence that every hosted job passed. See [releasing](docs/releasing.md).

Report security issues through [SECURITY.md](SECURITY.md), not public issues.
