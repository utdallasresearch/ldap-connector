<?php


/**
 * Configuration for LDAP
 * 
 */

return array(
    /***********************************
    * LDAP Connection Options (adLDAP)
    ************************************/
    'account_suffix' => "",

    'domain_controllers' => array("domain.edu"), // An array of domains may be provided for load balancing.

    'base_dn' => 'ou=people,dc=domain,dc=edu',

    'admin_username' => '', // Setting these to blank does anonymous bind
    'admin_password' => '',

    'user_id_key' => 'uid', // AD attribute uniquely identifying users (usually 'sAMAccountname')

    'search_filter' => 'objectClass=person', // search filter for finding people

    //'real_primary_group' => false, 	// Returns the primary group (an educated guess).
    //'use_ssl' => false, 				// If TLS is true this MUST be false.
    //'use_tls' => false, 				// If SSL is true this MUST be false.
    //'recursive_groups' => true,
    //'ad_port' => 389,
    //'sso' => '' 						// Use Single Sign-On
    
    /************************************
    * LDAP-Laravel Configuration Options
    *************************************/
    'login_key' => 'email',          // the key in the login form POST data used as username
    'password_key' => 'password',   // the key in the login form POST data used as password

    'attribute_map' => array(   // Map LDAP attribute => User Model field
        'uid' => 'name',
        'cn' => 'display_name',
        'dept' => 'department',
        'givenname' => 'firstname',
        'sn' => 'lastname',
        'title' => 'title',
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