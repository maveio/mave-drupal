# Changelog

## 1.0.0 — 2026-10-14

- Add Drupal 12 compatibility in Composer and module metadata.
- Convert theme and text-editor hooks to class implementations, preserving
  compatibility with Drupal 11.0 through legacy wrappers.
- Inject the Mave client into the hook service.
- Add native return types expected by Drupal 12's plugin and form APIs.
- Test hook discovery, player registration, editor attachments and credential
  isolation in the functional suite.
- Run the GitHub test matrix on Drupal 11.4.9 and Drupal 12.0.0-beta1 with PHP
  8.5, and update the local test environment to Drupal 11.4.9.
- Update test doubles for PHPUnit 12 without suppressing compatibility notices.

Validated with Drupal 11.4.9 and Drupal 12.0.0-beta1 on PHP 8.5.10. The
browser integration check on Drupal 11 covered browsing, search, upload and
processing, Media Library insertion, CKEditor preview and anonymous playback
against a local Mave installation.

No configuration or content migration is required. Rebuild Drupal caches
after updating so Drupal discovers the new hook service.

## 1.0.0-beta1 — 2026-09-14

First public beta with Mave library browsing, direct uploads, Drupal Media
Library integration, CKEditor 5 previews and player configuration for Drupal 11.
