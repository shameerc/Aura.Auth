<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @package Aura.Auth
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\Auth\Adapter;

use Aura\Auth\Exception;
use Aura\Auth\Verifier\VerifierInterface;
use Aura\Auth\Auth;

/**
 *
 * Authenticate against a file generated by htpassword.
 *
 * Format for each line is "username:hashedpassword\n";
 *
 * Automatically checks against DES, SHA, and apr1-MD5.
 *
 * SECURITY NOTE: Default DES encryption will only check up to the first
 * 8 characters of a password; chars after 8 are ignored.  This means
 * that if the real password is "atechars", the word "atecharsnine" would
 * be valid.  This is bad.  As a workaround, if the password provided by
 * the user is longer than 8 characters, and DES encryption is being
 * used, this class will *not* validate it.
 *
 * @package Aura.Auth
 *
 */
class HtpasswdAdapter extends AbstractAdapter
{
    /**
     *
     * @var string
     *
     */
    protected $file;

    /**
     *
     * @param string $file
     *
     * @param VerifierInterface $verifier
     *
     * @return void
     */
    public function __construct($file, VerifierInterface $verifier)
    {
        $this->file = $file;
        $this->verifier = $verifier;
    }

    /**
     *
     * Verifies set of credentials.
     *
     * @param array $cred A list of credentials to verify
     *
     */
    public function login(array $cred)
    {
        $this->checkCredentials($cred);
        $username = $cred['username'];
        $password = $cred['password'];
        $hashvalue = $this->fetchEncrypted($username);
        $this->verify($password, $hashvalue);
        return array($username, array());
    }

    protected function fetchEncrypted($username)
    {
        // force the full, real path to the file
        $real = realpath($this->file);
        if (! $real) {
            throw new Exception\FileNotReadable($this->file);
        }

        // find the user's line in the file
        $fp = fopen($real, 'r');
        $len = strlen($username) + 1;
        $hashvalue = false;
        while ($line = fgets($fp)) {
            if (substr($line, 0, $len) == "{$username}:") {
                // found the line, leave the loop
                $tmp = explode(':', trim($line));
                $hashvalue = $tmp[1];
                break;
            }
        }

        // close the file
        fclose($fp);

        // did we find the encrypted password for the username?
        if ($hashvalue) {
            return $hashvalue;
        }

        throw new Exception\UsernameNotFound;
    }

    protected function verify($password, $hashvalue)
    {
        if (! $this->verifier->verify($password, $hashvalue)) {
            throw new Exception\PasswordIncorrect;
        }
    }
}
