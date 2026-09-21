# Community releases

The source repository can be public before an installable release is ready. Do not add a fictitious image to the catalog. An empty `versions` object is intentional during development.

## Validate the candidate

Record the Cloudron version, image digest and package commit used for each check:

- Verify automatic first-run installation and login using the generated administrator password.
- Create a CMDB object and attachment, restart, and verify both remain available.
- Verify Graphviz impact analysis, extension installation and the customer portal.
- Verify the generated background task account and SLA/background task execution.
- Test actual outgoing email using the Cloudron-assigned sender.
- Back up and restore to another Cloudron app/domain; verify database credentials and links, accounts, data, attachments and cron.
- Test a package update on an installed instance. Before releasing a different upstream iTop version, implement and test database/compiled-model migration and rollback via Cloudron backup.
- Confirm anonymous requests cannot read configuration, data, logs or setup credentials.

Capture screenshots with synthetic data after setup. Commit the images under `assets/` and populate the manifest's `mediaLinks` with their public HTTPS URLs. An illustration of an untested app is not evidence of a working installation.

## Build and distribute

1. Bump the package version and add its CHANGELOG entry. Keep the upstream release/version/checksum aligned.
2. Build and push to a registry, for example `ghcr.io/ananda-bhatta/itop-cloudron`. Ensure the image is publicly pullable. Authenticate interactively; never put registry tokens into repository files.
3. Pin the published image by digest. Add a testing release with the Cloudron CLI:

   ```sh
   cloudron versions add --image ghcr.io/ananda-bhatta/itop-cloudron@sha256:ACTUAL_DIGEST --state testing
   cloudron versions verify
   ```

4. Review and commit the generated `CloudronVersions.json`. Leave `stable` false while collecting test feedback. Do not overwrite an existing image or change the contents of a released version.
5. Test anonymous image pulls and the install URL from a separate Cloudron instance:

   ```sh
   cloudron install --versions-url https://raw.githubusercontent.com/ananda-bhatta/itop-cloudron/main/CloudronVersions.json
   ```

6. After lifecycle validation, publish a new stable version and set the catalog's `stable` flag to true. Record known limitations and the test results in its release notes.

Users can then add that same catalog URL in Cloudron's **Community apps** section and receive release updates through Cloudron. Listing at [Community Apps](https://ca.cloudron.io) and announcing in the [forum](https://forum.cloudron.io) are optional separate actions.

See the [publishing guide](https://docs.cloudron.io/packaging/publishing/) and [version catalog reference](https://docs.cloudron.io/packaging/versions/).
