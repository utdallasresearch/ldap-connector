# Ldap-connector
Forked from Dsdevbe/ldap-connector

Provides an solution for authentication users with LDAP for Laravel 5.0.x. It uses ADLDAP library to create a bridge between Laravel and LDAP

## Installation
1. Install this package through Composer for Laravel v5.0:
    ```js
    composer require dsdevbe/ldap-connector:3.*
    ```

1. Change the authentication driver in the Laravel config to use the ldap driver. You can find this in the following file `config/auth.php`

    ```php
    'driver' => 'ldap',
    ```
1. Create a new configuration file `ldap.php` in the configuration folder of Laravel `app/config/ldap.php` and modify to your needs. For more detail of the configuration you can always check on [ADLAP documentation](https://github.com/adldap/adLDAP/wiki/adLDAP-Developer-API-Reference)
    
    ```
    return array(
    	'account_suffix'=>  "@domain.local",
    	'domain_controllers'=>  array("192.168.0.1", "dc02.domain.local"), // Load balancing domain controllers
    	'base_dn'   =>  'DC=domain,DC=local',

        'admin_username' => '', // Setting these to blank does anonymous bind
        'admin_password' => '',

        'user_id_key' => 'samaccountname', // AD attribute uniquely identifying users (usually 'sAMAccountname')

        'search_filter' => 'objectClass=person', // search filter for finding people

        'real_primary_group' => false,    // Returns the primary group (an educated guess).
        'use_ssl' => false,               // If TLS is true this MUST be false.
        'use_tls' => false,               // If SSL is true this MUST be false.
        'recursive_groups' => true,
        'ad_port' => 389,
        'sso' => ''                       // Use Single Sign-On
    );
    ```
1. Once this is done you arrived at the final step and you will need to add a service provider. Open `config/app.php`, and add a new item to the providers array.
	
	```
	'Dsdevbe\LdapConnector\LdapConnectorServiceProvider'
	```

## Usage
The LDAP plugin is an extension of the AUTH class and will act the same as normal usage with Eloquent driver.
    
    ```
    if (Auth::attempt(array('name' => $name, 'password' => $password)))
    {
        return Redirect::intended('dashboard');
    }
    ```

You can find more examples on [Laravel Auth Documentation](http://laravel.com/docs/master/authentication) on using the `Auth::` function.

### Login with Name Instead of Email
In a vanilla Laravel 5.0 install, the included user model and associated database migration has a 'name' and 'email' field with a default included view for 'email'-based login. If you prefer to login via 'name' (if that is more appropriate for LDAP-based login), edit the resources/views/auth/login.blade.php and modify the form appropriately. You will also have to override the postLogin() method from the trait included in Http/Controllers/Auth/AuthController.php to have it look for 'name' instead of 'email' in the request.
