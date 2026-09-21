This is an experimental community package.

1. In Cloudron's File Manager, open `/app/data/initial-setup.txt`. It contains the installation URL, the separate HTTP setup password, and database connection details.
2. Open `/setup/`, authenticate as `setup`, and complete iTop's wizard. Select the existing database. Choose your own iTop administrator credentials and use `/usr/bin/dot` for Graphviz.
3. Create a dedicated local iTop user with the Administrator profile for background tasks. Create `/app/data/cron.params` outside the public directory:

   ```text
   auth_user = YOUR_CRON_ADMIN_LOGIN
   auth_pwd = YOUR_CRON_ADMIN_PASSWORD
   ```

   The upstream parameter format treats `#` as a comment; choose a strong password without `#` or newlines for this account. Restart the app to apply file permissions, then check its cron logs. Cloudron invokes the runner every minute.
4. Configure email notifications with the sender address assigned in Cloudron's Email settings and test delivery. Database, application URL, and SMTP connection settings are managed by the package.

Setup authentication is separate from your iTop login. Keep the setup credentials for future extension changes. See the repository README for validation requirements and limitations.
