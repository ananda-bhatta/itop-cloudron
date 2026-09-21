# iTop Community for Cloudron

Independent, community-maintained packaging of [Combodo iTop](https://github.com/Combodo/iTop). Contributions and bug reports are welcome.

**Status: community testing release.** The upstream release is iTop Community **3.3.0 (build 21411)** with PHP 8.4. Fresh installation, login, email, background tasks, package updates, HTTPS session protection, and a Cloudron backup/restore of database records, document bytes, and persistent files have been validated. Upstream iTop version migrations and third-party extensions still require release-specific testing, so the catalog remains marked unstable.

## What is included

- Digest-pinned Cloudron PHP base and SHA-256-verified upstream release archive.
- Apache, Graphviz, required PHP extensions and optional LDAP support in PHP.
- Cloudron MySQL and outgoing SMTP configuration refreshed at runtime.
- Persistent configuration, compiled environments, extensions and application data.
- Automatic first-run installation using iTop's standard module selection, English default language and no demo data.
- A unique initial administrator password, with password change required on first login.
- A separate background task account and preconfigured Cloudron scheduler.
- A unique password protecting the maintenance setup wizard, preserving iTop's directory restrictions.
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

Install the testing release by adding this URL under **Community apps** in the Cloudron dashboard:

```text
https://raw.githubusercontent.com/ananda-bhatta/itop-cloudron/main/CloudronVersions.json
```

Initialization runs automatically before Apache starts. Read `/app/data/initial-admin.txt` in Cloudron's File Manager, then log in as `admin` and change the initial password. See [POSTINSTALL.md](POSTINSTALL.md). The dashboard displays an unstable-package warning while the catalog is in testing.

MySQL is supported by iTop, but upstream recommends MariaDB for performance. This package chooses Cloudron's managed MySQL service so database lifecycle and backups remain integrated with Cloudron. Benchmark your expected CMDB workload before production use.

## Files and configuration

| Path | Purpose |
| --- | --- |
| `/app/data/public` | iTop installation, including compiled environments |
| `/app/data/public/conf/production/config-itop.php` | iTop settings created by setup |
| `/app/data/public/extensions` | User-installed extensions |
| `/app/data/initial-admin.txt` | Generated initial iTop login; outside the web root, readable by Cloudron administrators |
| `/app/data/initial-setup.txt` | Separate HTTP credentials for setup/extension maintenance |
| `/app/data/setup-password` | Setup HTTP password, generated once |
| `/app/data/cron.params` | Automatically generated cron account credentials; outside the web root |
| `/app/data/bootstrap.log` | Private unattended installation diagnostics |
| `/run/php/sessions` | Temporary PHP sessions |

iTop renames its generated `env-*` directories during compilation. The working tree therefore lives in `/app/data/public`, rather than using symlinks for those directories. On restart, package code is synchronized from the image while configuration, data, logs, extensions and generated environments are retained. **Edits to other upstream files are replaced on restart.** Put customizations in extensions.

The source patch in [scripts/patch-itop.php](scripts/patch-itop.php) adds one managed configuration override after iTop evaluates its settings. [cloudron-settings.php](cloudron-settings.php) reads database, SMTP and public URL values from Cloudron's environment each time. This avoids stale passwords after addon reprovisioning or restoration. The patch build fails if the upstream insertion point changes.

New instances are initialized using the upstream unattended installer. Generated response files are temporary; passwords are never passed as command-line arguments. Existing configurations are preserved. If a database already contains tables without a configuration, initialization stops. Interrupted schema installation is not retried automatically; investigate the private bootstrap log and restore the pre-install backup. Existing manually configured instances keep their accounts and cron settings.

The health route verifies PHP and a database connection. Apache starts only after a fresh automatic installation completes. Health checks do not certify subsequent cron or email delivery.

## Updates and backups

Cloudron backs up `/app/data` and the MySQL addon. Test a restore onto a second instance, including changed database credentials and domain, before relying on this package.

Package revisions using the same upstream release can be installed with `cloudron update`. **Migrations to another iTop release are intentionally blocked at startup** until an upgrade procedure has been implemented and tested. Do not delete the `.cloudron-upstream-version` marker to bypass this check. Do not use iTop's core updater in this package; code comes from the package image. Extension installation uses iTop's setup workflow and should be tested against this version.

## Development checks

On a Linux machine with Docker:

```sh
docker build -t itop-cloudron:test .
bash tests/smoke.sh itop-cloudron:test
```

The smoke check starts an isolated MySQL container and completes unattended iTop installation with a read-only container filesystem. It verifies initial administrator and cron authentication, checks directory restrictions even for an authenticated setup user, changes the administrator password, creates a record, recreates the app and verifies both persist. It does not simulate Cloudron's backup implementation.

## Publish a community release

Follow [docs/RELEASING.md](docs/RELEASING.md). Releases require a publicly pullable image, real screenshots, and a populated `CloudronVersions.json`. Sharing the source repository already allows others to review, improve and test the package.

## License and attribution

Package scripts and changes are licensed under **AGPL-3.0-or-later**, as is iTop. See [LICENSE](LICENSE). Upstream copyright notices are retained. The Docker build downloads the exact official release archive and includes its license. The package's small configuration-loader change is fully available in this repository. Third-party components retain their own licenses.

The package icon is the upstream iTop logo; see [asset attribution](assets/README.md). This independent packaging project is not endorsed by Combodo or Cloudron.

## References

- [Cloudron packaging](https://docs.cloudron.io/packaging/tutorial/)
- [Cloudron publishing](https://docs.cloudron.io/packaging/publishing/)
- [iTop 3.3 requirements](https://www.itophub.io/wiki/page?id=3_3_0:install:requirements)
- [iTop background tasks](https://www.itophub.io/wiki/page?id=3_3_0:admin:cron)
