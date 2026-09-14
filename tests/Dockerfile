FROM drupal:11-apache@sha256:4249d58e94b052d5601499c7d1377433b5c9a8ad7b7b2d330368ce29181aca9c

RUN composer require --dev drupal/core-dev:11.4.6 \
      --with-all-dependencies --no-interaction --no-progress \
    && mkdir -p web/sites/simpletest/browser_output \
    && chown -R www-data:www-data web/sites

ENV SIMPLETEST_BASE_URL=http://localhost \
    SIMPLETEST_DB=sqlite://localhost//tmp/mave-tests.sqlite \
    BROWSERTEST_OUTPUT_DIRECTORY=/opt/drupal/web/sites/simpletest/browser_output
