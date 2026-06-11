# Release Policy

## Versioning

Before `1.0.0`, this package is allowed to make breaking changes between minor
versions while the public contracts are validated in real Laravel applications.

After `1.0.0`, the package follows semantic versioning:

- Patch releases fix bugs without changing public APIs.
- Minor releases add backward-compatible features.
- Major releases may change migrations, contracts, value objects, events, or
  documented behavior.

## Supported Matrix

The CI matrix tests:

- PHP 8.2 with Laravel 11
- PHP 8.3 with Laravel 11
- PHP 8.3 with Laravel 12
- PHP 8.4 with Laravel 12

The `composer.json` constraints may allow newer Illuminate versions before CI can
test them. A release should only claim support for combinations that pass CI.

## Release Checklist

1. Run `composer validate --strict`.
2. Run `composer test`.
3. Run `composer format -- --test`.
4. Update `CHANGELOG.md`.
5. Confirm README and `docs/INSTALLATION.md` match the current API.
6. Push `main` and confirm GitHub Actions is green.
7. Create an annotated tag:

```bash
git tag -a v0.1.0-alpha -m "v0.1.0-alpha"
git push origin v0.1.0-alpha
```

8. Submit or update the package on Packagist using
   `https://github.com/fissible/phone`.

Do not tag `v1.0.0` until at least one real application has used inbound SMS,
outbound SMS, inbound voice forwarding, voicemail, and webhook validation in
production.
