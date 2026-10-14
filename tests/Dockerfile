FROM drupal:11-apache@sha256:4249d58e94b052d5601499c7d1377433b5c9a8ad7b7b2d330368ce29181aca9c

ARG DRUPAL_VERSION=11.4.9

# Drupal 12 distribution archives omit the test framework. Source installs
# retain it so both matrix versions use Drupal's own test bootstrap.
RUN apt-get update && apt-get install -y --no-install-recommends git \
    && rm -rf /var/lib/apt/lists/* \
    && composer require drupal/core:${DRUPAL_VERSION} \
      drupal/core-recommended:${DRUPAL_VERSION} \
      drupal/core-composer-scaffold:${DRUPAL_VERSION} \
      drupal/core-project-message:${DRUPAL_VERSION} \
      --with-all-dependencies --no-interaction --no-progress \
    && composer require --dev drupal/core-dev:${DRUPAL_VERSION} \
      --with-all-dependencies --no-interaction --no-progress \
    && composer reinstall drupal/core --prefer-source --no-interaction --no-progress \
    && test -f web/core/tests/bootstrap.php \
    && mkdir -p web/sites/simpletest/browser_output \
    && chown -R www-data:www-data web/sites

ENV SIMPLETEST_BASE_URL=http://localhost \
    SIMPLETEST_DB=sqlite://localhost//tmp/mave-tests.sqlite \
    BROWSERTEST_OUTPUT_DIRECTORY=/opt/drupal/web/sites/simpletest/browser_output
