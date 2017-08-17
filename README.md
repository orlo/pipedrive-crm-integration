# SocialSignIn PipeDrive CRM integration

## Setup 

 * You need to get a PipeDrive API key (from PipeDrive). This is 40 character string.  (PIPE\_DRIVE\_API\_KEY)
 * You need to have a shared secret with SocialSignIn (see below). (SECRET)

The above two variables need to be added to an Apache (or similar) configuration (or hard coded into public/index.php, but this isn't ideal). An Apache example is below.


## SocialSignIn Secret 

When SocialSignIn make requests on your integration, the requests are signed with a shared secret (SECRET) which you can check against, to ensure a third party isn't trying to access your pipedrive data.

You define this secret when adding the CRM integration within SocialSignIn. It can be a string of any length (although as with all passwords, longer is generally better).

## Apache Configuration Example
 
 * Code is checked out/deployed to /sites/pipedrive.crm-integration and it's to respond to the domain name myserver.example.com

```raw
<VirtualHost *:443>
    ServerName myserver.example.com
    SSLEngine on
    SSLCertificateFile /path/to/ssl_certificate.pem
    SSLCertificateChainFile /path/to/ssl_intermediate.crt

    DocumentRoot /sites/pipedrive.crm-integration/public

    SetEnv PIPE_DRIVE_API_KEY MyPipeDriveAPIKeyGoesHere
    SetEnv SECRET MySocialSignInSecretGoesHere
    
    <Directory "/sites/pipedrive.crm-integration/public">
        AllowOverride All
    </Directory>

    CustomLog /var/log/apache2/pipedrive-access.log combined
    ErrorLog /var/log/apache2/pipedrive-errorlog
</VirtualHost>
```

