# SocialSignIn PipeDrive CRM integration

Allows you to integrate your Pipedrive account with SocialSignIn.

The documentation uses 'myserver.example.com' - replace this with a valid hostname for where you are hosting this code. It's recommended you secure it with a valid SSL certificate.


## SocialSignIn App Configuration

Within the SocialSignIn application, head to https://app.socialsignin.net/#/settings/inbox and add a Custom CRM integration.

 * Name - something of your choosing
 * Search Endpoint URL - https://myserver.example.com/search
 * Search Endpoint Secret - LongStringlyThingOfYourChoosing (aka SECRET)
 * Iframe Endpoint URL - https://myserver.example.com/iframe
 * Iframe Endpoint Secret - LongStringlyThingOfYourChoosing (aka SECRET)

( For this integration, the Search and Iframe Endpoint Secrets need to be the same )

### SocialSignIn Secret 

When SocialSignIn make requests on your integration, the requests are signed with a shared secret (SECRET) which you can check against, to ensure a third party isn't trying to access your pipedrive data.

You define this secret when adding the CRM integration within SocialSignIn. It can be a string of any length (although as with all passwords, longer is generally better).


## PipeDrive Configuration

 * You need to get a PipeDrive API key (from PipeDrive). This is 40 character string.  (aka PIPE\_DRIVE\_API\_KEY)

The above two variables need to be added to an Apache (or similar) configuration (or hard coded into public/index.php, but this isn't ideal). An Apache example is below.


# Installation

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

## Software Dependencies

You need to have :

 * A webserver (e.g. Apache 2.4)
 * PHP 7.0+
 * Composer ( https://getcomposer.org/composer.phar )


Within the root of the application, run :

```bash
php composer.phar install
```


