<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website's Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms;

/**
 * Class for authentication user by the value
 * of fcauth cookie
 * this cookie is set by the Google Friend Connect
 * service
 *
 * @author admin
 *
 */
class ExternalAuthGfc extends ExternalAuth
{

	/**
	 * Value of fcauth cookie
	 * @var string
	 */
	protected $fcauth;

	/**
	 * Name of cookie
	 * usualy fcauth$ourID or fcauth$ourID-s for session style cookie
	 * @var string
	 */
	protected $cookieName;

	/**
	 * Object returned by Lampcms\Http
	 *
	 * @var object
	 */
	protected $oResponse;

	/**
	 * Array of data we get back from GFC server
	 * after running the response through json_decode(true);
	 * @var array
	 */
	protected $aGfcData = array();

	/**
	 * value of our friend connect site id
	 * @var string, usually numeric
	 */
	protected $gfcSiteId;

	/**
	 * Object User that we either found
	 * or created new user
	 *
	 * @var object which extends User
	 */
	protected $oUser;


	public function __construct(Registry $oRegistry, $gfcSiteId){
		if(!extension_loaded('curl')){
			throw new \Lampcms\Exception('Cannot use this class because php extension "curl" is not loaded');
		}
		parent::__construct($oRegistry);
		$this->gfcSiteId = $gfcSiteId;
		$this->getGfcCookieVal();
	}


	/**
	 * Get JSON data from the server for this user
	 * If timeout, then what? Then we will throw our own
	 * Exception and user will see a message
	 * that timeout has occured
	 *
	 *
	 * @param string $fcauth value of fcauth cookie
	 */
	protected function getGfcData()
	{
		$url = 'http://www.google.com/friendconnect/api/people/@viewer/@self?fcauth='.$this->fcauth;

		$oHTTP = new Curl();

		try{
			d('cp');
			$this->oResponse = $oHTTP->getDocument($url);
			$gfcJson = $this->oResponse->getResponseBody();

			d('gfcJson '.$gfcJson);

			$aGfcData = json_decode($gfcJson, true);
			d('$gGfcData: '.print_r($aGfcData, 1));

			if(empty($aGfcData)
			|| !is_array($aGfcData)
			|| !array_key_exists('entry', $aGfcData)
			|| empty($aGfcData['entry']['id'])){
				throw new GFCAuthException('Invalid data returned by FriendConnect server');
			}

			$this->aGfcData = $aGfcData['entry'];
			/**
			 * this->gGfcData: Array
			 (
			 [entry] => Array
			 (
			 [isViewer] => 1
			 [id] => 11683420763934692837
			 [thumbnailUrl] => http://www.google.com/friendconnect/scs/images/NoPictureDark.png
			 [photos] => Array
			 (
			 [0] => Array
			 (
			 [value] => http://www.google.com/friendconnect/scs/images/NoPictureDark.png
			 [type] => thumbnail
			 )

			 )

			 [displayName] => David Smith
			 )

			 )
			 */

		} catch (HttpTimeoutException $e ){
			d('Request to GFC server timedout');
			throw new GFCAuthException('Request to Google Friend connect server timed out. Please try again later');
		} catch (Http401Exception $e){
			d('Unauthorized to get data from gfc, most likely user unjoined the site');
			$this->revokeFcauth();

			throw new GFCAuthException('Anauthorized with Friend Connect server');
		} catch(HttpResponseCodeException $e){
			e('LampcmsError gfc response exception: '.$e->getHttpCode().' '.$e->getMessage());
			/**
			 * The non-200 response code means there is some kind
			 * of error, maybe authorization failed or something like that,
			 * or maybe Friend Connect server was acting up,
			 * in this case it is better to delete fcauth cookies
			 * so that we dont go through these steps again.
			 * User will just have to re-do the login fir GFC step
			 */
			Cookie::delete(array('fcauth'.$this->gfcSiteId.'-s', 'fcauth'.$this->gfcSiteId));

			throw new GFCAuthException('Error during authentication with Friend Connect server');
		}
	}


	/**
	 * Set fcauth to NULL
	 * in USERS_GFC table
	 * this way we still have a record in the table but we also
	 * know that user is no longer using friend connect
	 *
	 * This is not particularly useful right now because
	 * We don't do anything with friend connect API, we
	 * don't post anything to FriendConnect, so we don't
	 * really have any use for fcauth value beyound simple user login
	 *
	 *
	 */
	protected function revokeFcauth()
	{

		if(!empty($this->aGfcData['id'])){
			$this->oRegistry->Mongo->getCollection('USERS_GFC')
			->update(array('_id' => $this->aGfcData['id']), array('$set' => array('fcauth' => null)));
			d('cp');
		}
		
		$this->oUser->offsetUnset('fcauth');
		$this->oUser->save();
		$this->oRegistry->Dispatcher->post($this, 'onGfcUserDelete');
		d('cp');
		Cookie::delete(array('fcauth'.$this->gfcSiteId.'-s', 'fcauth'.$this->gfcSiteId));

	}


	/**
	 * Get object of type UserGfc which extends User Object
	 *
	 * @return object of type UserGfc which is either a newly
	 * created user or existing user found by GFC id
	 */
	public function getUserObject()
	{

		$this->getGfcData();

		/**
		 * First get userid by gfc_id, via cache
		 * even though this is usually less than 1 millisecond,
		 * still avoiding mysql call is good.
		 *
		 */

		$aGfc = $this->oRegistry->Mongo->getCollection('USERS_GFC')
		->findOne(array('_id' => $this->aGfcData['id']));

		if(empty($aGfc) || empty($aGfc['i_uid'])){
			d('cp');
			$this->createNewUser();

			return $this->oUser;
		}

		$aUser = $this->oRegistry->Mongo->getCollection('USERS')
		->findOne(array('_id' => (int)$aGfc['i_uid']));

		if(!empty($aUser)){
			$this->oUser = UserGfc::factory($this->oRegistry, $aUser);
			d('$this->oUser: '.print_r($this->oUser->getArrayCopy(), 1));
			$this->updateUser()->updateGfcUserRecord(true);
		} else {
			d('cp');
			$this->createNewUser();
		}

		return $this->oUser;
	}


	/**
	 * Get the value of fcauth cookie
	 *
	 * @return object $this
	 */
	protected function getGfcCookieVal()
	{
		$cookieName = null;
		$fcauthSession = 'fcauth'.$this->gfcSiteId.'-s';
		$fcauthRegular = 'fcauth'.$this->gfcSiteId;

		if(isset($_COOKIE[$fcauthSession])){
			$cookieName = $fcauthSession;

		} elseif(isset($_COOKIE[$fcauthRegular])){
			$cookieName = $fcauthRegular;
		}
		d('cookie name: '.$cookieName);

		if(null === $cookieName){
			throw new GFCAuthException('no_fcauth_cookie');
		}

		$this->fcauth = filter_input(INPUT_COOKIE, $cookieName, FILTER_SANITIZE_STRING);
		d('this->fcauth cookie: '.$this->fcauth);

		return $this;
	}


	/**
	 * Update user data but ONLY if name
	 * or url of thumbnail
	 * or fcauth value has changed
	 *
	 * Also update record in USERS_GFC
	 * IF anything has changed
	 *
	 * If update is necessary, then also
	 * post notification onUserUpdate
	 *
	 */
	protected function updateUser()
	{
		$oldAvatar = $this->oUser->avatar_external;
		$newAvatar = $this->aGfcData['thumbnailUrl'];

		$oldName = $this->oUser->fn;
		$newName = $this->aGfcData['displayName'];

		$oldFcauth = $this->oUser->fcauth;


		if($oldAvatar !== $newAvatar || $oldName !== $newName || $oldFcauth !== $this->fcauth){
			$aUpdate = array(
			'avatar_external' => $newAvatar,
			'fn' => $newName,
			'fcauth' => $this->fcauth,
			'i_lm_ts' => time());

			d('going to update user object '.print_r($aUpdate, 1));
			$this->oUser->addArray($aUpdate)->save();
			$this->oRegistry->Dispatcher->post($this->oUser, 'onUserUpdate');

		}

		return $this;
	}


	/**
	 * Create record in USERS collection
	 *
	 * @return object $this
	 *
	 */
	protected function createNewUser(){

		$tzo = Cookie::get('tzo', 0);
		$username = $this->makeUsername($this->aGfcData['displayName']);
		/**
		 * Create new record in USERS collection
		 * do this first because we need uid from
		 * newly created record
		 *
		 */
		$aUser = array(
		'fn' => $this->aGfcData['displayName'],
		'avatar_external' => $this->aGfcData['thumbnailUrl'],
		'i_reg_ts' => time(),
		'date_reg' => date('r'),
		'username' => $username,
		'username_lc' => \mb_strtolower($username),
		'role' => 'external_auth',
		'lang' => $this->oRegistry->getCurrentLang(),
		'fcauth' => $this->fcauth,
		'tz' => TimeZone::getTZbyoffset($tzo),
		'i_rep' => 1,
		'i_fv' => (false !== $intFv = Cookie::getSidCookie(true)) ? $intFv : time());


		$oGeoData = $this->oRegistry->Cache->{sprintf('geo_%s', Request::getIP())};
		$aProfile = array(
		'cc' => $oGeoData->countryCode,
		'country' => $oGeoData->countryName,
		'state' => $oGeoData->region,
		'city' => $oGeoData->city,
		'zip' => $oGeoData->postalCode);
		d('aProfile: '.print_r($aProfile, 1));

		$aUser = array_merge($aUser, $aProfile);

		d('aUser: '.print_r($aUser, 1));

		$this->oUser = UserGfc::factory($this->oRegistry, $aUser);

		/**
		 * This will mark this userobject is new user
		 * and will be persistent for the duration of this session ONLY
		 * This way we can know it's a newsly registered user
		 * and ask the user to provide email address but only
		 * during the same session
		 *
		 * @todo this does not really work right now
		 * because newUser field is not included in serialization
		 * of user object, thus it's not saved
		 * when session is serialized
		 * This may change if serialize()/unserialize() methods are
		 * modified in User class
		 */
		$this->oUser->setNewUser();
		$this->oUser->save();

		$this->oRegistry->Dispatcher->post($this->oUser, 'onNewUser');
		$this->oRegistry->Dispatcher->post($this->oUser, 'onNewGfcUser');

		/**
		 * Create new record in USERS_GFC
		 */
		$this->updateGfcUserRecord();

		PostRegistration::createReferrerRecord($this->oRegistry, $this->oUser);

		return $this;
	}


	/**
	 * Create a new record in USERS_GFC table
	 * or update an existing record
	 *
	 * @param unknown_type $isUpdate
	 */
	protected function updateGfcUserRecord($isUpdate = false)
	{

		/**
		 * Create new record or update in USERS_GFC collection
		 */
		$uid = $this->oUser->getUid();
		d('uid '.$uid);

		$aGfc = array(
		'_id' => $this->aGfcData['id'],
		'i_uid' => (int)$uid,
		'fcauth' => $this->fcauth,
		'a_record' => $this->aGfcData,
		'i_lm_ts' => time());

		d('$aGfc: '.print_r($aGfc, 1));

		$this->oRegistry->Mongo->getCollection('USERS_GFC')->save($aGfc, array('fsync' => true));

		return $this;
	}

}
