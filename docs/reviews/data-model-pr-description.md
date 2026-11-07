# PR draft: Affiliate catalog data model

## Title

feat: add affiliate catalog data model

## What

Adds the affiliate catalog schema and domain services for products, categories, brands, tags, images, slug reservations, and view and click events.

## Why

Implements the approved data model in [0002](../specs/0002-affiliate-data-model.md).

## Changes

- Add relational tables, foreign keys, indexes, and SQLite triggers for domain constraints.
- Add Eloquent models and catalog services for product publishing, category trees, image management, slug reservations, event recording, and analytics.
- Configure active ISO 4217 currency codes and `APP_TIMEZONE`; set MySQL connections to UTC.
- Update the scope and data model spec to reflect implementation progress.

## How to verify

- Run `php artisan migrate` with the local SQLite configuration, then inspect the `products` and `product_events` schemas with `php artisan db:table`.
- Run `php -l` on the changed PHP files.
- MySQL migration compatibility and domain behavior still need verification. No automated tests were run for this change.

## Risk and rollout

The migration creates the catalog tables and must be applied before catalog services can be used. SQLite migration was applied locally; MySQL migration compatibility remains unverified.

## Notes for reviewers

Review transactional category changes, image ordering and deletion, slug reservation behavior, and publication validation. Access policies and public catalog integration are not included in this change.
