<?php

namespace InetProcess\SugarAPI;

use Webmozart\Assert\Assert;

class SugarClient extends AbstractRequest
{
    /**
     * @var Client
     */
    protected $username;
    protected $password;
    protected $platform = 'inetprocess';
    protected $token;
    protected $tokenExpiration;

    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    public function setPlatform($platform)
    {
        $this->platform = $platform;

        return $this;
    }

    public function login()
    {
        Assert::stringNotEmpty($this->username, 'You must call setUsername() or setToken() before doing any action');
        Assert::stringNotEmpty($this->password, 'You must call setPassword() or setToken() before doing any action');

        $this->logger->debug('SugarAPIWrapper Client: Login');

        $body = $this->request('oauth2/token', [], [
            'grant_type' => 'password',
            'client_id' => 'sugar',
            'client_secret' => '',
            'username' => $this->username,
            'password' => $this->password,
            'platform' => $this->platform,
        ], 'post', 200);

        if (empty($body['access_token'])) {
            throw new Exception\SugarAPIException("No Token in the returned body");
        }

        $this->token = $body['access_token'];
        $this->tokenExpiration = new \DateTime("+{$body['expires_in']} seconds");

        $this->logger->debug('SugarAPIWrapper Client: Token is ' . $this->token);
        $this->logger->debug('SugarAPIWrapper Client: Expiration is ' . $this->tokenExpiration->format('Y-m-d H:i:s'));
    }

    public function post($url, array $data, $expectedStatus = 201)
    {
        return $this->baseRequest('post', $url, $expectedStatus, $data);
    }

    public function put($url, array $data, $expectedStatus = 200)
    {
        foreach ($data as $field => $value) {
            if (is_null($value)) {
                $data[$field] = '';
            }
        }

        return $this->baseRequest('put', $url, $expectedStatus, $data);
    }

    public function get($url, $expectedStatus = 200)
    {
        return $this->baseRequest('get', $url, $expectedStatus);
    }

    public function delete($url, $expectedStatus = 204)
    {
        return $this->baseRequest('delete', $url, $expectedStatus);
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    public function getTokenExpiration()
    {
        return $this->tokenExpiration;
    }

    public function setTokenExpiration(\DateTime $tokenExpiration)
    {
        $this->tokenExpiration = $tokenExpiration;

        return $this;
    }

    public function baseRequest($method, $url, $expectedStatus = 200, array $data = [], array $headers = [])
    {
        Assert::oneOf($method, ['get', 'post', 'put', 'delete'], 'You can only post, put or get');

        $now = new \DateTime;
        if (empty($this->token) || $this->tokenExpiration < $now) {
            $this->logger->debug('SugarAPIWrapper Client: Token ' . empty($this->token) ? 'Empty' : 'Expired');
            $this->login();
        }

        $headers = array_merge(['OAuth-Token' => $this->token], $headers);

        return $this->request($url, $headers, $data, $method, $expectedStatus);
    }
}
