# Security advisory coverage application draft

Submit only if the applicant's personal Drupal.org account does not already
have permission to opt projects into coverage. The applicant must have made
most of the commits on the project branch used for review. Publish the reviewed
source to `1.0.x` and link its passing Drupal GitLab pipeline before submitting.
This draft does not claim that review or opt-in has happened.

Queue: [Drupal.org security advisory coverage applications](https://www.drupal.org/project/projectapplications)

- Title: `[1.0.x] Mave Video`
- Category: Task
- Component: Module
- Status: Needs review, once source and CI are ready

## Proposed description

Mave Video lets Drupal editors browse, upload and embed videos through Drupal
Media Library and CKEditor 5. Drupal stores media references while the configured
Mave services handle video processing and delivery. Player and service endpoints
are configurable, including for self-hosted installations.

Project: https://www.drupal.org/project/mave

Source branch: https://git.drupalcode.org/project/mave/-/tree/1.0.x

CI pipelines: https://git.drupalcode.org/project/mave/-/pipelines

The module separates administrative configuration, library browsing and upload
permissions. The API key remains server-side, outside configuration exports and
browser settings. Upload-token requests require a session, the upload permission
and a Drupal CSRF token. Tokens use a server-selected destination. The module
validates external identifiers and upstream responses and limits cached
thumbnail resources.

Unit and functional tests cover installation, permissions and CSRF, credential
isolation, API errors, upload tokens, library pagination, thumbnail limits,
player hook discovery and editor attachments. The repository provides Drupal
GitLab CI and a GitHub matrix for Drupal 11 and Drupal 12 beta.

Security boundaries and private reporting are documented in SECURITY.md.

Before submitting, add a direct passing pipeline link and any reviews of other
applications you actually completed. Do not claim review-bonus eligibility
without the three qualifying reviews.
