<?php
/**
 * @link      https://dukt.net/craft/oauth/
 * @copyright Copyright (c) 2017, Dukt
 * @license   https://dukt.net/craft/oauth/docs/license
 */

namespace Dukt\Social\LoginProviders;

use Craft\Craft;
use Guzzle\Http\Client;
use Craft\Oauth_TokenModel;

class Facebook extends BaseProvider
{
	/**
	 * Get the provider name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'Facebook';
	}

	/**
	 * Get the provider handle.
	 *
	 * @return string
	 */
	public function getOauthProviderHandle()
	{
		return 'facebook';
	}

	/**
	 * @inheritDoc
	 */
	public function getDefaultScope()
	{
		return [
			'email',
		];
	}

	public function getRemoteProfile(Oauth_TokenModel $token)
	{
		$oauthProvider = $this->getOauthProvider();
        $providerConfig = \Craft\craft()->config->get($this->getHandle(), 'social');

        $apiVersion = 'v2.12';

        if(!empty($providerConfig['apiVersion'])) {
            $apiVersion = $providerConfig['apiVersion'];
        }

		$client = new Client('https://graph.facebook.com/'.$apiVersion);
		$client->addSubscriber($oauthProvider->getSubscriber($token));

		$fields = implode(',', [
			'id', 'name', 'first_name', 'last_name',
			'email', 'hometown', 'picture.type(large){url,is_silhouette}',
			'cover{source}', 'gender', 'locale', 'link',
		]);

		$request = $client->get('/me?fields='.$fields);

		$response = $request->send();
		$json = $response->json();

		return $json;
	}

	public function getProfile(Oauth_TokenModel $token)
	{
		$remoteProfile = $this->getRemoteProfile($token);

		return [
			'id' => (isset($remoteProfile['id']) ? $remoteProfile['id'] : null ),
			'email' => (isset($remoteProfile['email']) ? $remoteProfile['email'] : null ),
			'firstName' => (isset($remoteProfile['first_name']) ? $remoteProfile['first_name'] : null ),
			'lastName' => (isset($remoteProfile['last_name']) ? $remoteProfile['last_name'] : null ),
			'photoUrl' => (isset($remoteProfile['picture']['data']['url']) ? $remoteProfile['picture']['data']['url'] : null ),

			'name' => (isset($remoteProfile['name']) ? $remoteProfile['name'] : null ),
			'hometown' => (isset($remoteProfile['hometown']) ? $remoteProfile['hometown'] : null ),
			'isDefaultPicture' => (isset($remoteProfile['picture']['data']['is_silhouette']) ? $remoteProfile['picture']['data']['is_silhouette'] : null ),
			'coverPhotoUrl' => (isset($remoteProfile['cover']['source']) ? $remoteProfile['cover']['source'] : null ),
			'gender' => (isset($remoteProfile['gender']) ? $remoteProfile['gender'] : null ),
			'locale' => (isset($remoteProfile['locale']) ? $remoteProfile['locale'] : null ),
			'link' => (isset($remoteProfile['link']) ? $remoteProfile['link'] : null ),
		];
	}
}
