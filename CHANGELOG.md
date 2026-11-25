# Changelog

All notable changes to this module. Versions follow calendar versioning `YY.WW.PATCH`.

## 26.48.0

First version.

- Widget "Google: Top search queries": the Google search queries for a store view from Search Console, with clicks, impressions, CTR and average position.
- Widget "Google: Search performance": Search Console clicks and impressions per day as a line chart.
- Widget "GA4 vs Magento: e-commerce check": Magento orders and revenue next to GA4 purchases, the difference in %, the list of orders missing in GA4 (linked to the order) and the GA4 transactions without a Magento order.
- One Google service account for the whole install, stored encrypted. GA4 property, hostname filter and Search Console property per store view.
- Google responses, the access token and errors are cached, so the dashboard does not use up the Google API quota.
- CLI command `bin/magento hans2103:dashboard-google:test` to check the key and every store view's configuration.
- en_US and nl_NL translations.
