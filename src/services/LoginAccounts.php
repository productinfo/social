<?php
/**
 * @link      https://dukt.net/social/
 * @copyright Copyright (c) 2017, Dukt
 * @license   https://dukt.net/social/docs/license
 */

namespace dukt\social\services;

use Craft;
use craft\helpers\FileHelper;
use dukt\social\helpers\SocialHelper;
use yii\base\Component;
use craft\elements\User as UserModel;
use dukt\social\elements\LoginAccount;
use dukt\social\records\LoginAccount as LoginAccountRecord;
use Exception;
use craft\helpers\UrlHelper;

class LoginAccounts extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Get all social accounts.
     *
     * @return array|null
     */
    public function getLoginAccounts()
    {
        return LoginAccount::find()->all();
    }

    /**
     * Get all of the social accounts for a given user id.
     *
     * @param $userId
     *
     * @return array|null
     */
    public function getLoginAccountsByUserId($userId)
    {
        return LoginAccount::find()->userId($userId)->all();
    }

    /**
     * Get a social account by it's id.
     *
     * @param int $id
     *
     * @return LoginAccount|null
     */
    public function getLoginAccountById($id)
    {
        return Craft::$app->elements->getElementById($id);
    }

    /**
     * Get a social account by provider handle for the currently logged in user.
     *
     * @param string $providerHandle
     *
     * @return LoginAccount|null
     */
    public function getLoginAccountByLoginProvider($providerHandle)
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        // Check if there is a current user or not
        if (!$currentUser) {
            return false;
        }

        return LoginAccount::find()->userId($currentUser->id)->providerHandle($providerHandle)->one();
    }

    /**
     * Get a social account by social UID.
     *
     * @param string $providerHandle
     * @param string $socialUid
     *
     * @return LoginAccount
     */
    public function getLoginAccountByUid($providerHandle, $socialUid)
    {
        return LoginAccount::find()->providerHandle($providerHandle)->socialUid($socialUid)->one();
    }

    /**
     * Save Account
     *
     * @param LoginAccount $account
     *
     * @throws Exception
     * @return bool
     */
    public function saveLoginAccount(LoginAccount $account)
    {
        $isNewAccount = !$account->id;

        if (!$isNewAccount) {
            $accountRecord = $this->_getLoginAccountRecordById($account->id);

            if (!$accountRecord) {
                throw new Exception(Craft::t('social', 'No social user exists with the ID “{id}”', ['id' => $account->id]));
            }
        } else {
            $accountRecord = new LoginAccount;
        }

        // populate
        $accountRecord->userId = $account->userId;
        $accountRecord->providerHandle = $account->providerHandle;
        $accountRecord->socialUid = $account->socialUid;

        // validate
        $accountRecord->validate();

        $account->addErrors($accountRecord->getErrors());

        if (!$account->hasErrors()) {
            $transaction = Craft::$app->getDb()->beginTransaction();

            try {
                if (Craft::$app->elements->saveElement($account)) {
                    // Now that we have an element ID, save it on the other stuff
                    if ($isNewAccount) {
                        $accountRecord->id = $account->id;
                    }

                    $accountRecord->save(false);

                    if ($transaction !== null) {
                        $transaction->commit();
                    }

                    return true;
                }
            } catch (\Exception $e) {
                if ($transaction !== null) {
                    $transaction->rollback();
                }

                throw $e;
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * Delete a social account by provider
     *
     * @param $providerHandle
     *
     * @return bool
     */
    public function deleteLoginAccountByProvider($providerHandle)
    {
        $loginAccount = $this->getLoginAccountByLoginProvider($providerHandle);

        return $this->deleteLoginAccounts($loginAccount);
    }

    /**
     * Delete all social accounts by user ID
     *
     * @param int $userId
     *
     * @return bool
     */
    public function deleteLoginAccountByUserId($userId)
    {
        $loginAccounts = $this->getLoginAccountById($userId);

        return $this->deleteLoginAccounts($loginAccounts);
    }

    /**
     * Delete a social login account by it's ID
     *
     * @param int $id
     *
     * @return bool
     */
    public function deleteLoginAccountById($id)
    {
        $loginAccount = $this->getLoginAccountById($id);

        return $this->deleteLoginAccounts($loginAccount);
    }

    /**
     * Deletes login accounts
     *
     * @param string|array $loginAccountStringOrArray
     *
     * @return bool
     */
    public function deleteLoginAccounts($loginAccountStringOrArray)
    {
        if (!$loginAccountStringOrArray) {
            return false;
        }

        if (!is_array($loginAccountStringOrArray)) {
            $loginAccountStringOrArray = [$loginAccountStringOrArray];
        }

        foreach ($loginAccountStringOrArray as $loginAccount) {
            Craft::$app->elements->deleteElement($loginAccount);
        }

        return true;
    }

    // Former Social service methods
    // =========================================================================

    /**
     * Get login URL
     *
     * @param $providerHandle
     * @param array  $params
     *
     * @return string
     */
    public function getLoginUrl($providerHandle, array $params = [])
    {
        $params['provider'] = $providerHandle;

        if (isset($params['scope']) && is_array($params['scope']))
        {
            $params['scope'] = urlencode(base64_encode(serialize($params['scope'])));
        }

        return SocialHelper::siteActionUrl('social/login-accounts/login', $params);
    }

    /**
     * Get link account URL
     *
     * @param $handle
     *
     * @return string
     */
    public function getLoginAccountConnectUrl($handle)
    {
        return UrlHelper::actionUrl('social/login-accounts/connect-login-account', [
            'provider' => $handle
        ]);
    }

    /**
     * Get unlink account URL
     *
     * @param $handle
     *
     * @return string
     */
    public function getLoginAccountDisconnectUrl($handle)
    {
        return UrlHelper::actionUrl('social/login-accounts/disconnect-login-account', [
            'provider' => $handle
        ]);
    }

    /**
     * Save remote photo
     *
     * @param string    $photoUrl
     * @param UserModel $user
     *
     * @return bool
     * @throws Exception
     */
    public function saveRemotePhoto($photoUrl, UserModel $user)
    {
        $filename = 'photo';

        $tempPath = Craft::$app->path->getTempPath().'/social/userphotos/'.$user->email.'/';

        FileHelper::createDirectory($tempPath);

        $client = new \GuzzleHttp\Client();

        $response = $client->request('GET', $photoUrl, array(
            'save_to' => $tempPath.$filename
        ));

        if ($response->getStatusCode() != 200)
        {
            return;
        }

        $contentTypes = $response->getHeader('Content-Type');

        if(is_array($contentTypes) && isset($contentTypes[0])) {
            switch($contentTypes[0]) {
                case 'image/gif':
                    $extension = 'gif';
                    break;
                case 'image/jpeg':
                    $extension = 'jpg';
                    break;
                case 'image/png':
                    $extension = 'png';
                    break;
                case 'image/svg+xml':
                    $extension = 'svg';
                    break;

                default:
                    throw new \Exception('Image type “'.$contentTypes[0].'” not supported');
            }
        } else {
            throw new \Exception('Image type not supported');
        }

        rename($tempPath.$filename, $tempPath.$filename.'.'.$extension);

        if($user->photoId) {
            Craft::$app->users->deleteUserPhoto($user);
        }

        $image = Craft::$app->images->loadImage($tempPath.$filename.'.'.$extension);
        $imageWidth = $image->getWidth();
        $imageHeight = $image->getHeight();

        $dimension = min($imageWidth, $imageHeight);
        $horizontalMargin = ($imageWidth - $dimension) / 2;
        $verticalMargin = ($imageHeight - $dimension) / 2;
        $image->crop($horizontalMargin, $imageWidth - $horizontalMargin, $verticalMargin, $imageHeight - $verticalMargin);

        Craft::$app->users->saveUserPhoto($tempPath.$filename.'.'.$extension, $user, $filename.'.'.$extension);

        return true;
    }

    // Private Methods
    // =========================================================================

    /**
     * Gets a user record by its ID.
     *
     * @param int $loginAccountId
     *
     * @return LoginAccountRecord
     * @throws UserNotFoundException if $loginAccountId is invalid
     */
    private function _getLoginAccountRecordById(int $loginAccountId): LoginAccountRecord
    {
        $loginAccountRecord = LoginAccountRecord::findOne($loginAccountId);

        if (!$loginAccountRecord) {
            // todo
            // throw new UserNotFoundException("No user exists with the ID '{$loginAccountId}'");
        }

        return $loginAccountRecord;
    }
}
