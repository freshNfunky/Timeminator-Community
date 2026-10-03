<?php
declare(strict_types=1);

/**
 * Community banner carousel (Issue #22).
 *
 * `ctrl_banners_feed` is the first-party endpoint the page fetches async. It
 * returns the carousel JSON (server-side proxied from the banner subdomain,
 * with a bundled offline fallback) so a strict CSP `connect-src 'self'` is
 * enough. `ctrl_banners_save` persists the admin toggles under Admin -> System.
 */

/** JSON feed consumed by assets/app.js. Never blocks the main page. */
function ctrl_banners_feed(): void
{
    // Only authenticated users ever see the slot, so the proxy stays closed.
    if (!Auth::check() || !Banners::isVisible()) {
        json_out(['enabled' => false, 'items' => []]);
    }

    $feed = Banners::feed();
    $items = [];
    foreach ($feed['items'] as $it) {
        if ($it['image'] !== '' && !preg_match('~^https?://~i', $it['image'])) {
            // Rewrite a relative creative path to a browser-resolvable asset URL.
            $it['image'] = asset($it['image']);
        }
        $items[] = $it;
    }

    json_out([
        'enabled' => true,
        'source'  => $feed['source'],
        'items'   => $items,
    ]);
}

/** Admin -> System: save banner visibility, telemetry opt-out and endpoint. */
function ctrl_banners_save(): void
{
    require_perm('admin.system');
    csrf_check();

    $endpoint = trim((string) post('banner_endpoint_override'));
    if ($endpoint !== '' && !preg_match('~^https?://~i', $endpoint)) {
        flash('Banner-Endpoint muss mit http:// oder https:// beginnen.', 'err');
        redirect_route('admin_system');
    }

    Settings::set('banner_visible', (bool) post('banner_visible'));
    Settings::set('banner_telemetry', (bool) post('banner_telemetry'));
    Settings::set('banner_endpoint_override', $endpoint);

    flash('Banner-Einstellungen gespeichert.');
    redirect_route('admin_system');
}
