iTop is initialized automatically with its standard module selection and no demo data.

1. Open `/app/data/initial-admin.txt` in Cloudron's File Manager.
2. Open the app normally and log in as `admin` using the generated password in that file. iTop asks you to change it at first login.
3. Edit the default organization and administrator contact to match your organization, then begin adding your CMDB records.

Database, public URL and SMTP connection settings are managed by the package. A separate `cloudron-cron` administrator account is created for background tasks; cron is already configured. Keep that account enabled. Send email notifications with the sender address assigned in Cloudron's Email settings and test delivery.

The protected `/setup/` wizard is for later extension maintenance. Its separate HTTP login is in `/app/data/initial-setup.txt`; it is not the normal iTop login.

Existing manually completed installations are preserved and keep their existing accounts and cron configuration. If initialization fails, inspect `/app/data/bootstrap.log`; the package will not erase a nonempty database or automatically repeat interrupted schema creation.
