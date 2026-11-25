# Hyvä Dashboard Google for Magento 2

Google Analytics 4 and Google Search Console widgets for the [Hyvä Admin Dashboard](https://www.hyva.io/).

Shop admins land on the Hyvä Admin Dashboard. It shows Magento's own data, but not what people search for in Google, and not whether the e-commerce data in GA4 matches Magento. This module adds three widgets that answer those questions:

| Widget | Source | What it shows |
|---|---|---|
| **Google: Top search queries** | Search Console | Queries people typed in Google that showed or led to the shop: clicks, impressions, CTR, average position. |
| **Google: Search performance** | Search Console | Clicks and impressions per day as a line chart. |
| **GA4 vs Magento: e-commerce check** | GA4 + Magento orders | Orders and revenue in Magento next to what GA4 measured, the difference in %, the list of Magento orders GA4 has **no** purchase for (each linked to the order), and how many GA4 transactions have no Magento order. |

## Requirements

- Magento 2.4.x (developed and tested on 2.4.9), PHP 8.2 or newer, `ext-openssl`
- **Hyvä Admin Dashboard** (`hyva-themes/commerce-module-admin-dashboard` ^2.0 with `hyva-themes/commerce-module-admin-dashboard-api` ^3.0.1). The dashboard is a commercial Hyvä product and is licensed separately. This module only adds widgets to it.
- A Google Cloud project with a service account (see below)

No Google SDK is installed. The module talks to Google's REST APIs with the Guzzle client that Magento already ships.

## Installation

```bash
composer require hans2103/magento2-hyva-dashboard-google
bin/magento module:enable Hans2103_HyvaDashboardGoogle
bin/magento setup:upgrade
bin/magento cache:flush
```

## Google Cloud setup

1. Open the [Google Cloud console](https://console.cloud.google.com/) and create a project, or pick an existing one.
2. Under **APIs & Services > Library**, enable these three APIs:
   - **Google Analytics Data API**
   - **Google Analytics Admin API** (used to read the property's timezone and currency)
   - **Google Search Console API**
3. Under **IAM & Admin > Service Accounts**, create a service account. It needs no project roles.
4. Open the service account, go to **Keys > Add key > Create new key > JSON**, and download the file. Treat it like a password.
5. Copy the service account's e-mail address (`…@….iam.gserviceaccount.com`) and give it read access:
   - **GA4:** Admin > Property > **Property access management** > add the e-mail with the **Viewer** role. Do this for every property you use.
   - **Search Console:** Settings > **Users and permissions** > Add user > the e-mail with **Restricted** permission. Do this for every property you use.

## Magento configuration

**Stores > Configuration > Services > Hyvä Dashboard Google**

| Field | Scope | Value |
|---|---|---|
| Service Account JSON Key | Default | Paste the full contents of the JSON key file. It is validated, then stored encrypted with Magento's crypt key, and never shown again. |
| Cache Lifetime for Google Responses | Default | Seconds, default 3600. `0` turns this cache off; the dashboard still caches every widget. |
| GA4 Property ID | Store view | The **numeric** property ID (GA4 Admin > Property settings), not the `G-XXXX` measurement ID. |
| Hostname Filter | Store view | Only when several store views share one GA4 property: e.g. `www.example.com`. GA4 data is then filtered on `hostName`. |
| Search Console Property | Store view | `sc-domain:example.com` for a domain property, or `https://www.example.com/` for a URL-prefix property. |

Then test the connection. The test bypasses all caches:

```bash
bin/magento hans2103:dashboard-google:test
bin/magento hans2103:dashboard-google:test --store=default
```

```
[OK] Service account key: dashboard@my-project.iam.gserviceaccount.com
[OK] Access token
[OK] default GA4 property 123456789 (Europe/Amsterdam, EUR)
[OK] default Search Console sc-domain:example.com
```

The exit code is 1 when any check fails.

## Using the widgets

On the dashboard, choose **Customise > Add widget**. The Search Console widgets are under **Marketing**, the GA4 check is under **Sales**. Each widget shows **one store view**, so for three shops you add three widgets.

### Google: Top search queries

- Period: last 7 days, 28 days or 3 months.
- The period ends **3 days ago**, because Search Console data takes about 2–3 days to become final. Search Console reports dates in Pacific Time.
- Sort by clicks or impressions. Number of rows: 1–100.
- "Open in Search Console" opens the property's performance report.

### Google: Search performance

Clicks and impressions per day. Impressions use the right-hand axis. Days without data are shown as 0.

### GA4 vs Magento: e-commerce check

The comparison always runs **up to and including yesterday**, in the **GA4 property's timezone**. Magento orders are selected with exactly the same day boundaries. GA4 needs up to about 24 hours to process purchases, so including today would flag orders that GA4 simply hasn't processed yet.

Matching is by `transactionId` = Magento order `increment_id`. That's what Yireo GoogleTagManager2 and most GTM setups send. IDs are compared as exact strings, so leading zeros matter.

Settings:

- **Order statuses to compare.** Default: everything except `canceled` and `pending_payment`. An abandoned iDEAL or card payment never reaches the success page, so GA4 can't know about it.
- **Only orders placed in the storefront** (default on). Only orders with a customer IP address (`remote_ip`) are counted. Orders created in the admin, through the API or EDI, or by converting a B2B quote never fire a browser purchase event.
- **Magento amount to compare.** Grand total incl. tax and shipping (default), subtotal incl. tax, or subtotal excl. tax. Pick the one that matches what your purchase event sends as `value`; otherwise the revenue difference is meaningless.
- **Number of missing orders to list:** 0–50.

The difference is neutral within 2%, a warning up to 10%, and an alert above 10%. Some difference is normal: visitors who decline analytics cookies, ad blockers, and customers who close the browser before the success page all cause orders that GA4 never sees. Look at the list of missing orders for patterns, such as one payment method, one store view, or one time of day.

If the store currency differs from the GA4 property currency, only the order counts are compared. Amounts are taken in the order currency, so on stores that take orders in several currencies the revenue comparison is approximate.

When Magento has no orders in the period but GA4 does report purchases, the widget shows an alert. If GA4 has grouped transactions into `(other)` (very large reports), the widget says so, because some orders listed as missing may then be in GA4. Choose a shorter period in that case.

## Caching and Google quotas

- The dashboard caches each widget's data: 6 hours for the Search Console widgets, 1 hour for the GA4 check. The Reload action in a widget's menu refreshes it.
- Every Google response is also cached by the module (default 1 hour), and the OAuth access token is cached until shortly before it expires.
- **Errors** (no access, wrong property ID, quota exceeded) are cached for only 5 minutes, and never in the dashboard cache. A fixed configuration shows up within 5 minutes, and a broken one causes at most one Google request per 5 minutes per widget.

To make this possible, the module adds a small plugin on the dashboard's internal cache class (`Hyva\AdminDashboardFramework\Model\Cache\Type\AdminDashboard::saveWidgetInstanceDisplayData`), so error messages are not stored. That class is not part of Hyvä's stable widget API, so check this plugin after major Hyvä Admin Dashboard upgrades.

## Security

- Only read-only scopes are requested: `analytics.readonly` and `webmasters.readonly`.
- The JSON key is stored encrypted in `core_config_data` and is never written to logs, exceptions, CLI output or the dashboard.
- The short-lived OAuth access token (about 1 hour) is kept in Magento's cache, like other API tokens.
- Never commit the JSON key file to a repository. If a key leaks, delete it in the Google Cloud console and create a new one.
- Access to the widgets is controlled by the ACL resource **Dashboard > Hyvä Dashboard Google widgets**; access to the configuration by **Stores > Configuration > Hyvä Dashboard Google**.

## Development

The unit tests run from a Magento root with the module in `app/code/Hans2103/HyvaDashboardGoogle`:

```bash
vendor/bin/phpunit -c app/code/Hans2103/HyvaDashboardGoogle/phpunit.xml.dist
```

## License

GPL-2.0-or-later, see [LICENSE.txt](LICENSE.txt). © 2026 Hans Kuijpers, [hkweb.nl](https://hkweb.nl)

Hyvä and the Hyvä Admin Dashboard are products of Hyvä Themes B.V. This module is not affiliated with or endorsed by Hyvä Themes or Google.
