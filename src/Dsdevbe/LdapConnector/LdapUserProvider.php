<?php namespace Dsdevbe\LdapConnector;

use App\User;
use adLDAP\adLDAP;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider as UserProviderInterface;

class LdapUserProvider implements UserProviderInterface {

    /**
     * Stores connection to LDAP.
     *
     * @var adLDAP
     */
    protected $adldap;

    /**
     * The key in the login form POST data used as username
     * (like 'email','name','username', and etc.)
     * @var string
     */
    protected $loginKey = 'email';

    /**
     * The key in the login form POST data used as password
     * @var string
     */
    protected $passKey = 'password';

    /**
     * Stores the mapping of LDAP Attributes => User Model fields
     * @var array
     */
    protected $attributeMap;
    
    /**
     * Defines an LDAP user attribute for which to save only the part before the @
     * @var string
     */
    protected $emailPartial;

    /**
     * The Entrust user roles that will be set
     * @var string
     */
    protected $ldapRole;

    /**
     * Stores the mapping of LDAP roles => Entrust Role::name
     * @var [type]
     */
    protected $roleMap;

    /**
     * Should we sync Entrust roles to LDAP roles on login?
     * @var boolean
     */
    protected $roleRefresh = false;

    /**
     * Should we auto-connect to the server on construct?
     * @var boolean
     */
    protected $autoConnect = true;

    /**
     * Creates a new LdapUserProvider and connect to Ldap
     *
     * @param array $config
     * @return void
     */
    public function __construct($config)
    {
        if (array_key_exists('login_key', $config)) $this->loginKey = $config['login_key'];
        if (array_key_exists('password_key', $config)) $this->passKey = $config['password_key'];
        if (array_key_exists('attribute_map', $config)) $this->attributeMap = $config['attribute_map'];
        if (array_key_exists('email_partial', $config)) $this->emailPartial = $config['email_partial'];
        if (array_key_exists('role_attribute', $config)) $this->ldapRole = strtolower($config['role_attribute']);
        if (array_key_exists('role_map', $config)) $this->roleMap = $config['role_map'];
        if (array_key_exists('role_refresh', $config)) $this->roleRefresh = $config['role_refresh'];
        if (array_key_exists('auto_connect', $config)) $this->autoConnect = $config['auto_connect'];

        $this->adldap = new adLDAP($config, $this->autoConnect);
    }

    public function connect()
    {
        if (!$this->autoConnect) {
            $this->adldap->connect();
        }
    }

    public function close()
    {
        if (!$this->autoConnect) {
            $this->adldap->close();
        }
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed $identifier
     * @return Authenticatable
     */
    public function retrieveById($identifier)
    {
        return User::find($identifier);
    }

    /**
     * Retrieve a user by by their unique identifier and "remember me" token.
     *
     * @param  mixed $identifier
     * @param  string $token
     * @return Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        $model = new User();

        return $model->newQuery()
                        ->where($model->getKeyName(), $identifier)
                        ->where($model->getRememberTokenName(), $token)
                        ->first();
    }

    /**
     * @param Authenticatable $user
     * @param string $token
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        $user->setRememberToken($token);

        $user->save();
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array $credentials
     * @return Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        $this->connect();
        $distinguishedName = $this->adldap->user()->dn($credentials[$this->loginKey]);

        if ($this->adldap->authenticate($distinguishedName, $credentials[$this->passKey])) {
            $user = $this->existingOrNew($credentials);
            $this->close();
            return $user;
        }
        $this->close();
    }

    /**
     * Retreive an array of users that match the search criteria.
     * 
     * @param  array      $searchFor keyed 'search-field'=>'search-value'
     * @param  array|null $fields    LDAP fields to return along with each user result
     * @return array|false
     */
    public function findLdapUsers(array $searchFor, array $fields = null)
    {
        $this->connect();
        if (array_key_exists('displayname',$searchFor)) {
            $users = $this->adldap->user()->findDetailed('displayname',$searchFor['displayname'],$fields);
            $this->close();
            return $users;
        }
        $this->close();
        return false;
    }
    
    /**
     * Return either an existing user with the given credentials
     * or create a new user with those credentials.
     * @param  array  $credentials
     * @return User
     */
    public function existingOrNew(array $credentials)
    {
        $user = User::where($this->loginKey,'=',$credentials[$this->loginKey]);
        if ($user->exists()) {
            $existinguser = $user->first();
            if ($this->roleRefresh) $this->setRoles($existinguser);
            return $existinguser;
        }
        else {
            return $this->createNewUser($credentials);
        }
    }

    /**
     * Create a new User with the given credentials
     * @param  array  $credentials
     * @return User
     */
    public function createNewUser(array $credentials)
    {
        $ldapAttributes = isset($this->attributeMap) ? array_keys($this->attributeMap) : ['*'];
        array_push($ldapAttributes, $this->ldapRole);
        $userInfo = $this->adldap->user()->info($credentials[$this->loginKey], $ldapAttributes)[0];

        foreach($userInfo as $key => $value){
            $credentials[$key] = $value[0];
        }
        $credentials = $this->modCredentials($credentials);
        $newuser = new User($credentials);
        $newuser->save();
        $this->setRoles($newuser);
        return $newuser;
    }

    /**
     * Modify the credential keys/values based on any config settings
     * @param  array  $credentials
     * @return array  with keys corrected to those in attributeMap
     */
    public function modCredentials(array $credentials) {
        if (isset($this->emailPartial)) {
            $credentials[$this->emailPartial] = strstr($credentials[$this->emailPartial], '@', true);
        }
        if (isset($this->attributeMap)) {
            foreach ($this->attributeMap as $ldapAttribute => $userField) {
                if (array_key_exists($ldapAttribute,$credentials)) {
                    $credentials[$userField] = $credentials[$ldapAttribute];
                }
            }
        }
        return $credentials;
    }

    /**
     * Syncs the roles in the LDAP for the user to the Entrust roles.
     * Only changes those roles listed in roleMap.
     * @param User $user [description]
     */
    public function setRoles(User $user) {
        if (isset($this->ldapRole) && isset($this->roleMap)) {
            $userInfo = $this->adldap->user()->info($user->name, [$this->ldapRole])[0];
            $userRolesFromLdap = array_slice($userInfo[$this->ldapRole],1);
            foreach ($this->roleMap as $role => $entrust_role) {
                $userHasRole = User::find($user->id)->hasRole($entrust_role);
                $ldapHasRole = in_array($role,$userRolesFromLdap);
                $entrust_role = \App\Role::where('name','=',$entrust_role)->first();
                if ($userHasRole && !$ldapHasRole) {
                    $user->detachRole($entrust_role);
                }
                if (!$userHasRole && $ldapHasRole) {
                    $user->attachRole($entrust_role);
                }
            }
        }
    }

    /**
     * Validate User credentials
     * @param  Authenticatable $user        
     * @param  array           $credentials 
     * @return boolean
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        $this->connect();
        $distinguishedName = $this->adldap->user()->dn($credentials[$this->loginKey]);
        $valid = $this->adldap->authenticate($distinguishedName, $credentials[$this->passKey]);
        $this->close();
        return $valid;
    }

}
