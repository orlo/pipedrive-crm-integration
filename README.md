# Orlo PipeDrive CRM integration

## Purpose of this project

Allows you to integrate your Pipedrive account with Orlo.

Orlo is a social media management application (and a bit more). By integrating PipeDrive with it, you can build a link
between
social media users and records within your internal CRM system.

## Disclaimer

This code hasn't been checked for correctness with Pipedrive's API for a few years.

It's entirely possible it does not work any longer.

We hope this code can be used as a basis of an integration with Orlo, but we'd strongly suggest some extensive testing
before putting it into production :-)

## SocialSignIn App Configuration

Within the Orlo application, head to https://www.orlo.app/#/settings/inbox and add a Custom CRM
integration.

* Name - something of your choosing
* Search Endpoint URL - https://myserver.example.com/search
* Search Endpoint Secret - LongStringlyThingOfYourChoosing (aka SECRET)
* Iframe Endpoint URL - https://myserver.example.com/iframe
* Iframe Endpoint Secret - LongStringlyThingOfYourChoosing (aka SECRET)

( For this integration, the Search and Iframe Endpoint Secrets need to be the same )

### SocialSignIn Secret

When Orlo makes requests on your integration, the requests are signed with a shared secret (SECRET) which you can
check against, to ensure a third party isn't trying to access your pipedrive data.

You define this secret when adding the CRM integration within Orlo. It can be a string of any length (although
as with all passwords, longer is generally better).

## PipeDrive Configuration

* You need to get a PipeDrive API key (from PipeDrive). This is 40 character string.  (aka PIPE\_DRIVE\_API\_KEY)

The above two variables need to be added to an Apache (or similar) configuration (or hard coded into public/index.php,
but this isn't ideal). An Apache example is below.

# Installation

The documentation uses 'myserver.example.com' - replace this with a valid hostname for where you are hosting this code.
It's recommended you secure it with a valid SSL certificate.

## Apache Configuration Example

* Code is checked out/deployed to /sites/pipedrive.crm-integration and it's to respond to the domain name
  myserver.example.com

```raw
<VirtualHost *:443>
    ServerName myserver.example.com
    SSLEngine on
    SSLCertificateFile /path/to/ssl_certificate.pem
    SSLCertificateChainFile /path/to/ssl_intermediate.crt

    DocumentRoot /sites/pipedrive.crm-integration/public

    SetEnv PIPE_DRIVE_API_KEY MyPipeDriveAPIKeyGoesHere
    SetEnv SECRET MySecretGoesHere
    
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
* PHP 7.4+ (ideally 8.x+)
* Composer ( https://getcomposer.org/composer.phar )

Within the root of the application, run :

```bash
php composer.phar install
```
