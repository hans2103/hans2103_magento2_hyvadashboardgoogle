# CLAUDE.md

Hyvä Dashboard Google for Magento 2 (`Hans2103_HyvaDashboardGoogle`, Composer package `hans2103/magento2-hyva-dashboard-google`) adds Google Analytics 4 and Google Search Console widgets to the Hyvä Admin Dashboard.

## Rules

- Never copy code from the licensed Hyvä Admin Dashboard packages into this repository; depend on them.
- Never commit a Google service account key or any other credential.
- Widgets implement `Hyva\AdminDashboardApi\Api\V1\WidgetTypeInterface`. `etc/adminhtml/hyva_dashboard_widget.xml` must validate against the dashboard's XSD (no digits in template paths): `Test/Unit/Etc/WidgetXmlTest.php` checks this.

## Tests

Run in a Magento install with the module in `app/code/Hans2103/HyvaDashboardGoogle`:

```bash
vendor/bin/phpunit -c app/code/Hans2103/HyvaDashboardGoogle/phpunit.xml.dist
```

## PR's

Do **not** add a 'Generated with Claude Code' line in a PR.

## Versioning

Calendar versioning `YY.WW.PATCH` (e.g. `26.48.0`), tagged `v{version}`. Composer reads the version from the tag; `composer.json` has no `version` field. Update `CHANGELOG.md` with every release: a pushed tag `v{version}` gets a GitHub Release (`.github/workflows/release.yml`) with the section `## {version}` of `CHANGELOG.md` as its notes.
