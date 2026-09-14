# Configuration

Open **Configuration → Media → Mave Video** (`/admin/config/media/mave`).
A key entered here is stored in Drupal State, separate from configuration
exports. It is still present in the site's database and backups.

For deployments, inject the key through the environment and `settings.php`:

```php
$settings['mave_api_key'] = getenv('MAVE_API_KEY') ?: '';
```

This overrides the stored key and disables editing it in the form. Both a raw
credential pair and an encoded API key are accepted. Do not commit real keys.

## Permissions and editing

Editors need `browse mave videos`, optionally `upload mave videos`,
`create mave_video media`, and the Drupal media/content permissions appropriate
to their role. Only trusted administrators should receive `administer mave`.

For CKEditor 5, enable Media Library in the toolbar, the Embed media text filter,
and the Mave Video media type. The module leaves existing text formats unchanged.
Media reference fields can use Drupal's Media Library widget as well.

Drupal stores media references only when an editor selects a video. The remote
library is not bulk-imported. Reusing a Drupal media item reuses its theme/color
overrides. The precedence is media item, field formatter, global default.

## Upload target and services

Leave **Upload target** empty to use the authenticated API key's space, or set a
space/collection ID. A collection also becomes the initial library folder.
This is navigation and upload configuration, not a restriction on library access.

Configure the API, component module, CDN, upload, WebSocket and metrics URLs for
your Mave installation. Defaults point to Mave's hosted services. Use HTTPS/WSS
in production. The component URL must be a trusted ES module; prefer a tested,
version-pinned release or a build you host yourself.

The CDN template supports `${this.spaceId}`, `${spaceId}` or `{spaceId}`. Browser
endpoints must be reachable from visitors' browsers. Containers have their own
`localhost`; local HTTPS and certificates must work in both Drupal and the browser.

## Data flow

Drupal requests library metadata using the server-side API key. The browser
receives only public endpoint settings and, for authorized uploads, a temporary
upload token. Uploads go directly to Mave rather than through Drupal file storage.

When a player loads, browsers contact the configured component/CDN services and
send playback analytics to the metrics endpoint. Those services receive network
metadata such as the IP address. Account for this in your site's privacy and
consent setup; the module does not include a consent manager.

Thumbnails are cached in `public://mave-thumbnails/` for Drupal image styles;
video files stay in Mave. Downloads are limited to 2 MiB and images to 16 million
pixels. Changed thumbnails are not automatically refreshed yet. The cache is
public, so it must not be used to store private preview images.
