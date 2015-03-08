<?php namespace Dsdevbe\LdapConnector;

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
     * Creates a new LdapUserProvider and connect to Ldap
     *
     * @param array $config
     * @return void
     */
    public function __construct($config)
    {
        $this->adldap = new adLDAP($config);
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed $identifier
     * @return Authenticatable
     */
    public function retrieveById($identifier)
    {
        $userInfo = $this->adldap->user()->info($identifier, array('*'))[0];
        
        $credentials = array();
        $credentials['name'] = $identifier;
        
        foreach($userInfo as $key => $value){
            $credentials[$key] = $value[0];
        }

        return new LdapUser($credentials);
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
        // TODO: Implement retrieveByToken() method.
    }

    /**
     * @param Authenticatable $user
     * @param string $token
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        // TODO: Implement updateRememberToken() method.
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array $credentials
     * @return Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        $name = $credentials['name'];
        $password = $credentials['password'];
        $dn = $this->adldap->user()->dn($name);
        if ($this->adldap->authenticate($dn, $password)) {
            $userInfo = $this->adldap->user()->info($credentials['name'], array('*'))[0];

            foreach($userInfo as $key => $value){
                $credentials[$key] = $value[0];
            }

            return new LdapUser($credentials);
        }
    }

    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        $name = $credentials['name'];
        $password = $credentials['password'];
        $dn = $this->adldap->user()->dn($name);

        return $this->adldap->authenticate($dn, $password);
    }

}
