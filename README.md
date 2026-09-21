# iTop Community for Cloudron

Independent, community-maintained packaging of [Combodo iTop](https://github.com/Combodo/iTop). Contributions and bug reports are welcome.

**Status: experimental development package.** The upstream release is iTop Community **3.3.0 (build 21411)** with PHP 8.4. No community release is published yet. The version catalog is deliberately empty and marked unstable. Cloudron installation, completed setup, email delivery, backup/restore and migrations must be validated before a stable release.

## What is included

- Digest-pinned Cloudron PHP base and SHA-256-verified upstream release archive.
- Apache, Graphviz, required PHP extensions and optional LDAP support in PHP.
- Cloudron MySQL and outgoing SMTP configuration refreshed at runtime.
- Persistent configuration, compiled environments, extensions and application data.
- A unique password protecting the setup wizard, separate from iTop user accounts.
- Cloudron scheduler integration for iTop background tasks.
- CI container build and smoke checks; no automatic publishing from CI.

Cloudron SSO and incoming mail collection are not configured. Users initially authenticate with iTop accounts. PHP LDAP support alone does not configure LDAP authentication.

## Install for testing

Use Cloudron 10 or newer and the current [Cloudron CLI](https://docs.cloudron.io/packaging/cli/). The manifest requires 10.0.0 for current community-package metadata. The package requests a 2 GiB memory limit; size the server for the database and other apps too.

```sh
git clone https://github.com/ananda-bhatta/itop-cloudron.git
cd itop-cloudron
cloudron login my.example.com
cloudron install
```

Current Cloudron CLI can build on the server. Follow [POSTINSTALL.md](POSTINSTALL.md) to finish setup and enable cron. Use a dedicated test domain while the package is experimental. **Do not use the empty catalog URL to install yet.**

MySQL is supported by iTop, but upstream recommends MariaDB for performance. This package chooses Cloudron's managed MySQL service so database lifecycle and backups remain integrated with Cloudron. Benchmark your expected CMDB workload before production use.

## Files and configuration

| Path | Purpose |
| --- | --- |
| `/app/data/public` | iTop installation, including compiled environments |
| `/app/data/public/conf/production/config-itop.php` | iTop settings created by setup |
| `/app/data/public/extensions` | User-installed extensions |
| `/app/data/initial-setup.txt` | Setup credentials and current database details; outside the web root |
| `/app/data/setup-password` | Setup HTTP password, generated once |
| `/app/data/cron.params` | Dedicated cron account credentials; outside the web root |
| `/run/php/sessions` | Temporary PHP sessions |

iTop renames its generated `env-*` directories during compilation. The working tree therefore lives in `/app/data/public`, rather than using symlinks for those directories. On restart, package code is synchronized from the image while configuration, data, logs, extensions and generated environments are retained. **Edits to other upstream files are replaced on restart.** Put customizations in extensions.

The source patch in [scripts/patch-itop.php](scripts/patch-itop.php) adds one managed configuration override after iTop evaluates its settings. [cloudron-settings.php](cloudron-settings.php) reads database, SMTP and public URL values from Cloudron's environment each time. This avoids stale passwords after addon reprovisioning or restoration. The patch build fails if the upstream insertion point changes.

The health route verifies PHP and a database connection. It does not certify completion of the iTop wizard or successful cron processing.

## Updates and backups

Cloudron backs up `/app/data` and the MySQL addon. Test a restore onto a second instance, including changed database credentials and domain, before relying on this package.

Package revisions using the same upstream release can be installed with `cloudron update`. **Migrations to another iTop release are intentionally blocked at startup** until an upgrade procedure has been implemented and tested. Do not delete the `.cloudron-upstream-version` marker to bypass this check. Do not use iTop's core updater in this package; code comes from the package image. Extension installation uses iTop's setup workflow and should be tested against this version.

## Development checks

On a Linux machine with Docker:

```sh
docker build -t itop-cloudron:test .
bash tests/smoke.sh itop-cloudron:test
```

The smoke check starts an isolated MySQL container, runs the app with a read-only root filesystem, checks the protected setup page and database health, recreates the app with the same data volume, then verifies persisted data. Its test containers and volume are removed on exit. It does not complete the iTop setup wizard or simulate Cloudron's backup implementation.

## Publish a community release

Follow [docs/RELEASING.md](docs/RELEASING.md). Releases require a publicly pullable image, real screenshots, and a populated `CloudronVersions.json`. Sharing the source repository already allows others to review, improve and test the package.

## License and attribution

Package scripts and changes are licensed under **AGPL-3.0-or-later**, as is iTop. See [LICENSE](LICENSE). Upstream copyright notices are retained. The Docker build downloads the exact official release archive and includes its license. The package's small configuration-loader change is fully available in this repository. Third-party components retain their own licenses.

The package icon is a simple text identifier, not an official Combodo or Cloudron logo. This project is not endorsed by either company.

## References

- [Cloudron packaging](https://docs.cloudron.io/packaging/tutorial/)
- [Cloudron publishing](https://docs.cloudron.io/packaging/publishing/)
- [iTop 3.3 requirements](https://www.itophub.io/wiki/page?id=3_3_0:install:requirements)
- [iTop background tasks](https://www.itophub.io/wiki/page?id=3_3_0:admin:cron)
