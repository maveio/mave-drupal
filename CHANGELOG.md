# Changelog

## Unreleased

- Limit per-video themes to the built-in choices. Unsupported saved themes fall
  back to the default player. Theme selectors offer only Default, Dolphin and
  Synthwave, with Default selected for empty or unsupported stored values.

- Address the follow-up Drupal advisory coverage review: remove the committed
  license text (Drupal.org adds it to release packages), translate player theme
  labels and document the trusted serialization round-trips in tests.
- Enforce both Drupal and DrupalPractice coding standards in CI, including tests.
- Clarify that Mave generates API credentials, document the
  two-hour token lifetime and add the reporting handover required at security
  advisory coverage opt-in.
- Use the complete API key copied from Mave directly for requests and uploads;
  remove automatic encoding of raw credential pairs.
- Exclude maintainer release/application guides from distribution archives.
- Update the Drupal 11 test target to 11.4.12; retain Drupal 12.0.0-beta2 coverage.

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
