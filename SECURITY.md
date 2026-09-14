# Security

Report suspected vulnerabilities privately to [cert@mave.io](mailto:cert@mave.io).
Follow [Mave's responsible-disclosure policy](https://www.mave.io/docs/responsible-disclosure/).
Include the module and Drupal versions, required permissions, reproduction steps
and impact. Remove keys, signed URLs and customer data from reports and logs.

This module is preparing its first release. It does not currently claim Drupal
Security Team advisory coverage. A source audit or passing tests do not establish
Drupal.org coverage; check the published project's status when available.

## Security boundaries

- Only trusted administrators may configure Mave endpoints or the API key.
  The component URL loads executable JavaScript into the site.
- The API key belongs on the server, outside exported configuration and rendered
  HTML. State storage is separate from config export, but is not encrypted storage.
- Library access requires `browse mave videos`. Upload credentials require
  `upload mave videos`, a session and Drupal's CSRF token.
- Upload tokens are short-lived bearer credentials with a server-chosen target.
  Mave's upload service must verify them and enforce scope and expiry.
- Video IDs, upstream responses and metadata are untrusted. They must not enable
  script injection, credential disclosure or arbitrary file writes.
- Cached thumbnails are public. Published video access is governed by Mave's
  delivery configuration; Drupal media permissions do not privatize a CDN URL.

An upload collection selects the destination and initial folder. It is not a
folder access-control rule. Use Mave API-key permissions to limit the connected
library. Keep Drupal core and external Mave services/components updated, and
use HTTPS/WSS for production endpoints.
