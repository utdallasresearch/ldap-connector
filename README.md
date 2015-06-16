# Ldap-connector
Forked from Dsdevbe/ldap-connector

Provides a solution for LDAP authentication of users in Laravel 5.0.x. It uses a [forked version](https://github.com/utdallasresearch/adLDAP/) of the [adLDAP library](https://github.com/adldap/adLDAP/) to create a bridge between Laravel and LDAP. It also includes support for the [Entrust (laravel-5)](https://github.com/Zizaco/entrust/tree/laravel-5) package for user roles and permissions.

## Installation
1. Install this package through Composer for Laravel v5.0:

    - Add `utdallasresearch/ldap-connector` to your composer.json file. You can specify `dev-master` for the master branch or a [particular release](https://github.com/utdallasresearch/ldap-connector/releases).

    - Add the repositories for both ldap-connector and adLDAP to your composer.json file. These are required because this repo is not yet on Packagist (#todo).
    
    ```json
    "repositories": [
        {
            "url": "https://github.com/utdallasresearch/ldap-connector",
            "type": "git"
        },
        {
            "url": "https://github.com/utdallasresearch/adLDAP",
            "type": "git"
        }
    ],
    "require": {
        "laravel/framework": "5.0.*",
        "zizaco/entrust": "dev-laravel-5",
        "utdallasresearch/ldap-connector": "dev-master"
    },
    ```
    - Run `composer update`


1. Change the authentication driver in the Laravel config to use the ldap driver. You can find this in the following file `config/auth.php`

    ```php
    'driver' => 'ldap',
    ```
1. Create a new configuration file `ldap.php` in the configuration folder of Laravel `app/config/ldap.php` and modify to your needs. For more detail of the configuration you can always check the [adDLAP documentation](https://github.com/adldap/adLDAP/wiki/adLDAP-Developer-API-Reference). Most of the available options are briefly documented below. Comment-out any options that you don't need, and they won't be used.
    
    ```php
    return array(
        /***********************************
        * LDAP Connection Options (adLDAP)
        ************************************/
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

        /************************************
        * LDAP-Laravel Configuration Options
        *************************************/
        'user_model' => '\App\User',    // the fully-qualified name of your user model

        'login_key' => 'email',          // the key in the login form POST data used as username
        'password_key' => 'password',   // the key in the login form POST data used as password

        'attribute_map' => array(   // Map LDAP attribute => User Model field
            'uid' => 'name',
            'cn' => 'display_name',
            'dept' => 'department',
            'givenname' => 'firstname',
            'sn' => 'lastname',
            'title' => 'title',
            'mail' => 'email'
        ),

        'email_partial' => 'mail',  // Saves the specified LDAP attribute as the part before the @

        'role_attribute' => 'memberOf',   // Set Entrust roles using specified LDAP attribute

        'role_map' => array(    // Map LDAP attribute values to Entrust role names
            'staff' => 'staff',
            'employee' => 'staff',
            'student' => 'student',
            'faculty' => 'faculty',
            'directory' => 'directory'
        ),

        'role_refresh' => true, // Sync Entrust roles with LDAP roles on login
    );
    ```

1. Add Ldap-connector as a service provider. Open `config/app.php`, and add a new item to the providers array.
	
	```
	'Dsdevbe\LdapConnector\LdapConnectorServiceProvider'
	```

1. Make sure you have a valid `User` model (such as `app/User.php`). A Laravel 5 install with default scaffolding includes one that works great with this package. If you are using your own user model, you can specify its name in the 'user_model' configuration setting. Your model must implement Laravel's `Authenticatable` contract in order to work with this package.

    ```php
    namespace App;
    use Illuminate\Auth\Authenticatable;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

    class User extends Model implements AuthenticatableContract {
        use Authenticatable;
    }
    ```

1. If you want to save LDAP attributes into your local user database, create a migration to add the relevant LDAP attributes to your database as User fields. The mapping of LDAP attribute to User-model fields is defined as above in the `attribute_map` configuration option, but these fields still need to exist first in the schema.

    ```php
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Database\Migrations\Migration;

    class AddLdapUserColumns extends Migration {

        /**
         * Run the migrations.
         *
         * @return void
         */
        public function up()
        {
            Schema::table('users', function(Blueprint $table)
            {
                $table->string('department')->nullable();
                $table->string('firstname')->nullable();
                $table->string('lastname')->nullable();
                $table->string('title')->nullable();
            });
        }

        /**
         * Reverse the migrations.
         *
         * @return void
         */
        public function down()
        {
            Schema::table('users', function(Blueprint $table)
            {
                $table->dropColumn('department');
                $table->dropColumn('firstname');
                $table->dropColumn('lastname');
                $table->dropColumn('title');
            });
        }

    }
    ```

    These fields also need to be present in the `$fillable` array in the User model.

## Usage

The LDAP plugin is an extension of the AUTH class and will act the same as normal usage with Eloquent driver.
    
```php
if (Auth::attempt(array('name' => $name, 'password' => $password)))
{
    return Redirect::intended('dashboard');
}
```

You can find more examples on [Laravel Auth Documentation](http://laravel.com/docs/master/authentication) on using the `Auth::` function.

### Login with Username Instead of Email
In a vanilla Laravel 5.0 install, the included user model and associated database migration has a 'name' and 'email' field with a default included view for 'email'-based login. If you prefer to login via 'name' (if that is more appropriate for your particular LDAP-based login), edit the `resources/views/auth/login.blade.php` and modify the form appropriately. You will also have to override the `postLogin()` method from the trait included in `app/Http/Controllers/Auth/AuthController.php` to have it look for 'name' instead of 'email' in the request.

```php
/**
 * Handle a login request to the application.
 * This one overrides the trait function of the same name.
 *
 * @param  \Illuminate\Http\Request  $request
 * @return \Illuminate\Http\Response
 */
public function postLogin(Request $request)
{
    $this->validate($request, [
        'name' => 'required|alpha_num', 'password' => 'required',
    ]);

    $credentials = $request->only('name', 'password');

    if ($this->auth->attempt($credentials, $request->has('remember')))
    {
        return redirect()->intended($this->redirectPath());
    }

    return redirect($this->loginPath())
                ->withInput($request->only('name', 'remember'))
                ->withErrors([
                    'name' => $this->getFailedLoginMesssage(),
                ]);
}
```

You will also have to modify the view `/resources/views/auth/login.blade.php`, and be sure to set the `login_key` in the `ldap.php` config file to 'name' or 'username' as appropriate.