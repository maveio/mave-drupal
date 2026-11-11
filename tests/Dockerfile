FROM drupal:11-apache@sha256:0e3f1001a265ccc3629a81b5889a2ecf61a0908152fbc0c772722965b7a81dae

ARG DRUPAL_VERSION=11.4.12

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
