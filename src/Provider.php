<?php

namespace SocialiteProviders\YouTube;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class Provider extends AbstractProvider implements ProviderInterface
{
    /**
     * {@inheritdoc}
     */
    protected $scopeSeparator = " ";
    protected $scopes = ["https://www.googleapis.com/auth/youtube.readonly", "https://www.googleapis.com/auth/yt-analytics.readonly", "https://www.googleapis.com/auth/userinfo.email", "https://www.googleapis.com/auth/userinfo.profile"];

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        $url = $this->buildAuthUrlFromBase(
            'https://accounts.google.com/o/oauth2/auth', $state
        );

        $url .= '&access_type=offline';

        return $url;
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return 'https://accounts.google.com/o/oauth2/token';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get(
            'https://www.googleapis.com/youtube/v3/channels?part=snippet&mine=true', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true)['items'][0];
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id' => $user['id'], 'nickname' => $user['snippet']['title'],
            'name' => null, 'email' => null,
            'avatar' => $user['snippet']['thumbnails']['high']['url'],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }

    /**
     * Get the refresh & acceess token for the given code.
     *
     * @param  string  $code
     * @return string
     */
    public function getRefreshAndAccessToken($code)
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => $this->getTokenFields($code),
        ]);

        $tokens = json_decode( $response->getBody(), true );
        $this->refresh_token = $tokens['refresh_token'];

        return $tokens['access_token'];
    }

    /**
     * {@inheritdoc}
     */
    public function user()
    {
        $user = $this->mapUserToObject($this->getUserByToken(
            $token = $this->getRefreshAndAccessToken($this->getCode())
        ));

        if (isset($this->refresh_token))
            $user->refresh_token = $this->refresh_token;

        return $user->setToken($token);
    }
}

